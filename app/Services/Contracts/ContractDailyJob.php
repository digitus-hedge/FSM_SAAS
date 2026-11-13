<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\Tenant;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily contract job (CNT-08, requirements 6.10 and 6.11). Safe to run twice on the same day.
 * Order: expire, expiring soon, contract.expiring alerts, auto-renew, roll call periods.
 * Reads and writes with explicit tenant ids because no company is signed in when it runs.
 */
class ContractDailyJob
{
    private const THRESHOLDS = [90, 60, 30];
    private const PERIOD_MONTHS = ['monthly' => 1, 'quarterly' => 3, 'annual' => 12];

    /** Run for one company. $today is the company's own date. Returns counts for the command output. */
    public function runForTenant(Tenant $tenant, Carbon $today): array
    {
        $tid = (int) $tenant->id;
        $out = ['expired' => 0, 'renewed_marked' => 0, 'expiring_soon' => 0, 'alerts' => 0, 'auto_renewed' => 0, 'periods_rolled' => 0];

        $base = fn () => Contract::withoutGlobalScopes()->where('tenant_id', $tid);

        // 1. Past the end date: Expired, or Renewed when a successor exists.
        foreach ($base()->whereIn('status', ['active', 'expiring_soon'])->whereDate('end_date', '<', $today->toDateString())->get() as $c) {
            if ($this->hasSuccessor($c)) {
                $c->forceFill(['status' => 'renewed'])->save();
                $out['renewed_marked']++;
                $this->audit($tid, 'contract.renewed', $c, ['status' => 'renewed']);
            } else {
                $c->forceFill(['status' => 'expired'])->save();
                $out['expired']++;
                $this->audit($tid, 'contract.expired', $c, ['status' => 'expired']);
            }
        }

        // 2. Active with 90 days or fewer left: Expiring soon.
        foreach ($base()->where('status', 'active')
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereDate('end_date', '<=', $today->copy()->addDays(Contract::EXPIRING_DAYS)->toDateString())->get() as $c) {
            $c->forceFill(['status' => 'expiring_soon'])->save();
            $out['expiring_soon']++;
            $this->audit($tid, 'contract.expiring_soon', $c, ['status' => 'expiring_soon']);
        }

        // 3. contract.expiring at exactly 90, 60 and 30 days, once per contract and threshold.
        foreach (self::THRESHOLDS as $days) {
            foreach ($base()->whereIn('status', ['active', 'expiring_soon'])
                ->whereDate('end_date', $today->copy()->addDays($days)->toDateString())->with('client')->get() as $c) {
                if ($this->alreadySent($tid, $c->id, $days)) {
                    continue;
                }
                $svc = app(NotificationService::class);
                $svc->notify('contract.expiring', $svc->usersWhoCan('contract.manage', $tid), [
                    'contract_number' => $c->contract_number,
                    'days'            => $days,
                    'client'          => $c->client?->name ?? '',
                    'date'            => $c->end_date->format('d M Y'),
                ], route('admin.contracts.show', $c, false), ['contract_id' => $c->id, 'threshold' => $days]);
                $out['alerts']++;
            }
        }

        // 4. Auto-renew: on the last day or later, when the flag is on and there is no successor.
        foreach ($base()->where('auto_renew', true)->whereIn('status', ['active', 'expiring_soon', 'expired'])
            ->whereDate('end_date', '<=', $today->toDateString())->get() as $c) {
            if ($this->hasSuccessor($c)) {
                continue;
            }
            if ($this->autoRenew($tid, $c, $today)) {
                $out['auto_renewed']++;
            }
        }

        // 5. Roll call periods.
        foreach ($base()->whereIn('status', ['active', 'expiring_soon'])
            ->whereIn('call_period', array_keys(self::PERIOD_MONTHS))->get() as $c) {
            if ($this->rollPeriod($tid, $c, $today)) {
                $out['periods_rolled']++;
            }
        }

        return $out;
    }

    private function hasSuccessor(Contract $c): bool
    {
        return Contract::withoutGlobalScopes()->where('renewed_from_contract_id', $c->id)
            ->where('status', '!=', 'cancelled')->exists();
    }

    private function alreadySent(int $tenantId, int $contractId, int $days): bool
    {
        return UserNotification::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('event_key', 'contract.expiring')
            ->where('data->contract_id', $contractId)->where('data->threshold', $days)->exists();
    }

    /** 6.11: same terms and length, starts the day after the old end, Active at once. */
    private function autoRenew(int $tid, Contract $old, Carbon $today): bool
    {
        $start = $old->start_date->copy();
        $end   = $old->end_date->copy();

        $months      = (int) floor($start->diffInMonths($end->copy()->addDay()));
        $wholeMonths = $months > 0 && $start->copy()->addMonthsNoOverflow($months)->equalTo($end->copy()->addDay());

        $newStart = $end->copy()->addDay();
        $newEnd   = $wholeMonths
            ? $newStart->copy()->addMonthsNoOverflow($months)->subDay()
            : $newStart->copy()->addDays((int) $start->diffInDays($end));

        // A very old contract whose next term is already over is not renewed by the job.
        if ($newEnd->lt($today)) {
            Log::warning('Contract auto-renew skipped: next term already ended', ['contract' => $old->id]);
            return false;
        }

        $status = $newEnd->lte($today->copy()->addDays(Contract::EXPIRING_DAYS)) ? 'expiring_soon' : 'active';

        try {
            $newId = DB::transaction(function () use ($tid, $old, $newStart, $newEnd, $status) {
                $now = now();
                $id = DB::table('contracts')->insertGetId([
                    'tenant_id'                 => $tid,
                    'contract_number'           => Contract::numberFor($tid),
                    'client_id'                 => $old->client_id,
                    'contract_type'             => $old->contract_type,
                    'status'                    => $status,
                    'start_date'                => $newStart->toDateString(),
                    'end_date'                  => $newEnd->toDateString(),
                    'response_sla_minutes'      => $old->response_sla_minutes,
                    'resolution_sla_minutes'    => $old->resolution_sla_minutes,
                    'max_calls'                 => $old->max_calls,
                    'call_period'               => $old->call_period,
                    'calls_used_current_period' => 0,
                    'period_started_on'         => $newStart->toDateString(),
                    'annual_value'              => $old->annual_value,
                    'payment_terms'             => $old->payment_terms,
                    'auto_renew'                => $old->auto_renew,
                    'renewed_from_contract_id'  => $old->id,
                    'notes'                     => $old->notes,
                    'activated_at'              => $now,
                    'auto_renewed_at'           => $now,
                    'created_at'                => $now,
                    'updated_at'                => $now,
                ]);

                foreach (DB::table('contract_sites')->where('contract_id', $old->id)->pluck('project_site_id') as $sid) {
                    DB::table('contract_sites')->insert(['tenant_id' => $tid, 'contract_id' => $id, 'project_site_id' => $sid, 'created_at' => $now, 'updated_at' => $now]);
                }
                foreach (DB::table('contract_categories')->where('contract_id', $old->id)->pluck('service_category_id') as $cid) {
                    DB::table('contract_categories')->insert(['tenant_id' => $tid, 'contract_id' => $id, 'service_category_id' => $cid, 'created_at' => $now, 'updated_at' => $now]);
                }

                // The old contract keeps matching until its end date passes (step 1 marks it Renewed then).
                return $id;
            });
        } catch (\Throwable $e) {
            Log::error('Contract auto-renew failed', ['contract' => $old->id, 'error' => $e->getMessage()]);
            return false;
        }

        if ($old->end_date->lt($today)) {
            $old->forceFill(['status' => 'renewed'])->save();
        }

        $this->audit($tid, 'contract.auto_renewed', $old, [
            'old_contract' => $old->contract_number, 'new_contract_id' => $newId,
            'start' => $newStart->toDateString(), 'end' => $newEnd->toDateString(),
        ]);

        return true;
    }

    /** 6.6: a new period starts on each anniversary step counted from the period start. */
    private function rollPeriod(int $tid, Contract $c, Carbon $today): bool
    {
        $step    = self::PERIOD_MONTHS[$c->call_period];
        $started = ($c->period_started_on ?? $c->start_date)->copy();

        $next = $started->copy()->addMonthsNoOverflow($step);
        if ($next->gt($today)) {
            return false;
        }

        // Jump to the latest period start that is not after today (and not after the end date).
        $guard = 0;
        while ($next->lte($today) && $guard++ < 600) {
            $started = $next;
            $next    = $started->copy()->addMonthsNoOverflow($step);
        }
        if ($started->gt($c->end_date)) {
            return false;
        }

        $before = (int) $c->calls_used_current_period;
        $c->forceFill(['period_started_on' => $started->toDateString(), 'calls_used_current_period' => 0])->save();

        $this->audit($tid, 'contract.period_rolled', $c, ['calls_before' => $before, 'period_started_on' => $started->toDateString()]);

        return true;
    }

    private function audit(int $tid, string $action, Contract $c, array $new): void
    {
        try {
            app(AuditLogger::class)->log($action, [
                'tenant_id' => $tid, 'item_type' => Contract::class, 'item_id' => $c->id,
                'new' => ['contract_number' => $c->contract_number] + $new,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Contract audit failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
