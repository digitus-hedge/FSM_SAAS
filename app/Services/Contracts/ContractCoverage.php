<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contract matching, coverage suggestion and call counting (requirements 6.2 to 6.7).
 * Dates use the application time zone (today()).
 */
class ContractCoverage
{
    /** Contracts are for Professional and above. Without the feature the Release A manual choice stays. */
    public function enabled(): bool
    {
        return (bool) Tenant::current()?->hasFeature('contracts');
    }

    /* ------------------------------------------------------------ matching */

    /** 6.3: contracts that can cover this request today. */
    public function candidates(ServiceRequest $sr): Collection
    {
        if (! $sr->client_id) return collect();

        return Contract::query()
            ->where('client_id', $sr->client_id)
            ->whereIn('status', ['active', 'expiring_soon'])
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->where(fn ($q) => $q->whereDoesntHave('sites')
                ->orWhereHas('sites', fn ($s) => $s->where('project_sites.id', $sr->project_site_id)))
            ->where(fn ($q) => $q->whereDoesntHave('categories')
                ->orWhereHas('categories', fn ($c) => $c->where('service_categories.id', $sr->service_category_id)))
            ->withCount(['sites', 'categories'])
            ->orderBy('end_date')->orderBy('id')
            ->get();
    }

    /**
     * 6.2: the suggested coverage.
     * Returns: type (in_contract|in_warranty|out_of_coverage), contract, candidates, reason,
     *          needs_choice, choice_reason (several|cap_reached|null).
     */
    public function resolve(ServiceRequest $sr): array
    {
        $out = ['type' => 'out_of_coverage', 'contract' => null, 'candidates' => collect(), 'reason' => '',
                'needs_choice' => false, 'choice_reason' => null];

        if (! $this->enabled()) {
            $out['reason'] = 'Choose the coverage by hand.';
            return $out;
        }

        $cands = $this->candidates($sr);
        $out['candidates'] = $cands;

        // A contract the manager chose earlier is used while it is still a candidate (6.4).
        $contract = $sr->contract_id ? $cands->firstWhere('id', $sr->contract_id) : null;
        if (! $contract && $cands->count() === 1) $contract = $cands->first();

        // CNT-05: the asset's default contract wins when several match and the pin is still a candidate.
        // The manager can still change it (change coverage or choose another contract).
        if (! $contract && $cands->count() > 1 && ! $sr->is_master_sr) {
            $pinId = $sr->primaryAsset?->default_contract_id;
            if ($pinId) $contract = $cands->firstWhere('id', $pinId);
        }

        if (! $contract && $cands->count() > 1) {
            $out['type']          = 'in_contract';
            $out['needs_choice']  = true;
            $out['choice_reason'] = 'several';
            $out['reason']        = $cands->count() . ' contracts match this request. Choose the one that applies.';
            return $out;
        }

        if ($contract) {
            $out['type']     = 'in_contract';
            $out['contract'] = $contract;

            if ($contract->capReached()) {
                $out['needs_choice']  = true;
                $out['choice_reason'] = 'cap_reached';
                $out['reason'] = "Contract {$contract->contract_number} has used {$contract->calls_used_current_period} of {$contract->max_calls} calls.";
                return $out;
            }

            $next = $contract->calls_used_current_period + 1;
            $out['reason'] = "In contract {$contract->contract_number} ({$contract->type_label}). "
                . ($contract->max_calls ? "Call {$next} of {$contract->max_calls}." : "Call {$next}, no cap.");
            return $out;
        }

        // Asset warranty (batch requests skip it).
        $asset = $sr->primaryAsset;
        if (! $sr->is_master_sr && $asset && $asset->warranty_expiry_date && $asset->warranty_expiry_date->gte(today())) {
            $out['type']   = 'in_warranty';
            $out['reason'] = "In warranty. The asset's warranty ends on " . $asset->warranty_expiry_date->format('d M Y') . '.';
            return $out;
        }

        $out['reason'] = 'Out of coverage. No contract matches and the asset is not under warranty.';
        return $out;
    }

    /* ------------------------------------------------------- request events */

    /** D3: at creation, flag the request when several contracts match. Nothing is counted. */
    public function onCreated(ServiceRequest $sr): void
    {
        if (! $this->enabled()) return;

        $cands   = $this->candidates($sr);
        $pinId   = $sr->is_master_sr ? null : $sr->primaryAsset?->default_contract_id;
        $pinned  = $pinId && $cands->contains('id', $pinId);
        $several = $cands->count() > 1 && ! $pinned;
        if ($pinned && ! $sr->contract_id) $sr->contract_id = $pinId;
        $sr->forceFill([
            'contract_choice_pending' => $several,
            'contract_choice_reason'  => $several ? 'several' : null,
        ])->save();
        if ($several) {
            $this->notifyChoice($sr, 'sr.contract_choice_needed', ['count' => $cands->count()]);
        }
    }

    /** Set the choice flag found at approval. */
    public function flagChoice(ServiceRequest $sr, array $result): void
    {
        // Notify only when the request enters this waiting state (not on every page view).
        $already = $sr->contract_choice_pending && $sr->contract_choice_reason === $result['choice_reason'];

        $sr->forceFill([
            'contract_choice_pending' => true,
            'contract_choice_reason'  => $result['choice_reason'],
            'contract_id'             => $result['choice_reason'] === 'cap_reached' ? $result['contract']?->id : $sr->contract_id,
        ])->save();

        if ($already) {
            return;
        }

        if ($result['choice_reason'] === 'cap_reached' && $result['contract']) {
            $c = $result['contract'];
            $this->notifyChoice($sr, 'contract.cap_reached', [
                'contract_number' => $c->contract_number,
                'used'            => $c->calls_used_current_period,
                'max'             => $c->max_calls,
            ]);
        } elseif ($result['choice_reason'] === 'several') {
            $this->notifyChoice($sr, 'sr.contract_choice_needed', ['count' => $result['candidates']->count()]);
        }
    }

    /** Tell the users who can choose a contract (contract.choose). */
    private function notifyChoice(ServiceRequest $sr, string $event, array $vars): void
    {
        $svc = app(NotificationService::class);
        $svc->notify(
            $event,
            $svc->usersWhoCan('contract.choose', $sr->tenant_id),
            $vars + ['sr_number' => $sr->sr_number],
            route('service-requests.show', $sr),
            ['service_request_id' => $sr->id]
        );
    }

    /** 6.4: the manager chooses one of the current candidates. Counts nothing. */
    public function choose(ServiceRequest $sr, int $contractId): Contract
    {
        $contract = $this->candidates($sr)->firstWhere('id', $contractId);
        if (! $contract) {
            throw ValidationException::withMessages(['contract_id' => 'This contract does not match the request.']);
        }

        $before = $this->candidates($sr)->pluck('contract_number')->all();

        $sr->forceFill([
            'contract_id' => $contract->id, 'coverage_type' => 'in_contract',
            'contract_choice_pending' => false, 'contract_choice_reason' => null,
        ])->save();

        app(AuditLogger::class)->log('request.contract_chosen', [
            'item_type' => ServiceRequest::class, 'item_id' => $sr->id,
            'new' => ['sr_number' => $sr->sr_number, 'contract' => $contract->contract_number, 'candidates' => $before],
        ]);

        return $contract;
    }

    /* ---------------------------------------------------------------- calls */

    /** 6.6: count one call. Call inside a transaction with the contract row locked. */
    public function countCall(ServiceRequest $sr, Contract $locked, bool $overCap = false): void
    {
        $locked->increment('calls_used_current_period');

        DB::table('contract_calls')->insert([
            'tenant_id' => $sr->tenant_id, 'contract_id' => $locked->id, 'service_request_id' => $sr->id,
            'action' => 'counted', 'over_cap' => $overCap,
            'period_started_on' => ($locked->period_started_on ?? $locked->start_date)->toDateString(),
            'user_id' => Auth::id(), 'created_at' => now(),
        ]);

        $audit = app(AuditLogger::class);
        $audit->log('contract.call_counted', ['item_type' => Contract::class, 'item_id' => $locked->id,
            'new' => ['contract_number' => $locked->contract_number, 'sr_number' => $sr->sr_number]]);
        if ($overCap) {
            $audit->log('contract.call_over_cap', ['item_type' => Contract::class, 'item_id' => $locked->id,
                'new' => ['contract_number' => $locked->contract_number, 'sr_number' => $sr->sr_number]]);
        }
    }

    /** 6.6: give the call back when a request is cancelled before the first punch in. */
    public function giveBack(ServiceRequest $sr): void
    {
        if ($sr->first_punch_in_at || ! $sr->contract_id) return;

        DB::transaction(function () use ($sr) {
            $counted = DB::table('contract_calls')->where('service_request_id', $sr->id)->where('action', 'counted')->count();
            $given   = DB::table('contract_calls')->where('service_request_id', $sr->id)->where('action', 'given_back')->count();
            if ($counted <= $given) return;

            $contract = Contract::lockForUpdate()->find($sr->contract_id);
            if (! $contract) return;

            $contract->forceFill(['calls_used_current_period' => max(0, $contract->calls_used_current_period - 1)])->save();

            DB::table('contract_calls')->insert([
                'tenant_id' => $sr->tenant_id, 'contract_id' => $contract->id, 'service_request_id' => $sr->id,
                'action' => 'given_back', 'over_cap' => false,
                'period_started_on' => ($contract->period_started_on ?? $contract->start_date)->toDateString(),
                'user_id' => Auth::id(), 'created_at' => now(),
            ]);

            app(AuditLogger::class)->log('contract.call_given_back', ['item_type' => Contract::class, 'item_id' => $contract->id,
                'new' => ['contract_number' => $contract->contract_number, 'sr_number' => $sr->sr_number]]);
        });
    }
}
