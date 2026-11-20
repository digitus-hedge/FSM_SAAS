<?php

namespace App\Services\Trades;

use App\Models\Asset;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestExpense;
use App\Models\SrStatusHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Approval\ApprovalModes;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\Checklists;
use App\Services\Forms\FormException;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * A multi-trade job: one master request with 2 to 10 trades as child requests (Multi-Trade-Vendors-Depot spec MTR-01 to MTR-05,
 * D2 to D7, D20). The master's status is derived from its children and never set by hand.
 */
class MasterRequests
{
    public const MIN = 2;
    public const MAX = 10;

    public function enabled(?Tenant $tenant): bool
    {
        return (bool) $tenant?->hasFeature('multi_trade');
    }

    /**
     * @param array<string,mixed> $data  client_id, project_site_id, priority, title, description, client_po_reference
     * @param array<int,array{service_category_id:int, planned_tech_user_id:?int, planned_at:?string, notes:?string}> $children
     */
    public function create(Tenant $tenant, User $creator, array $data, array $children): ServiceRequest
    {
        if (! $this->enabled($tenant)) {
            throw new FormException('FEATURE_NOT_IN_PLAN', 'Not in your plan.', 403);
        }
        if (count($children) < self::MIN || count($children) > self::MAX) {
            throw new FormException('MASTER_CHILD_COUNT', 'A multi-trade job needs between 2 and 10 trades.');
        }

        $categories = ServiceCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', true)
            ->whereIn('id', collect($children)->pluck('service_category_id'))->get()->keyBy('id');
        foreach ($children as $c) {
            if (! $categories->has((int) $c['service_category_id'])) {
                throw new FormException('MASTER_CHILD_COUNT', 'Choose a service category for every trade.');
            }
        }

        $master = DB::transaction(function () use ($tenant, $creator, $data, $children, $categories) {
            $first  = $categories[(int) $children[0]['service_category_id']];
            $master = $this->makeRequest($tenant, $creator, $data, $first, [
                'is_master_sr' => true, 'title' => $data['title'],
            ]);

            foreach ($children as $c) {
                $cat = $categories[(int) $c['service_category_id']];
                $child = $this->makeRequest($tenant, $creator, $data, $cat, [
                    'parent_sr_id' => $master->id,
                    'title' => $data['title'] . ' - ' . $cat->name,
                    'description' => trim(($c['notes'] ?? '') . ' ' . ($data['description'] ?? '')) ?: null,
                    'planned_tech_user_id' => $c['planned_tech_user_id'] ?? null,
                    'planned_at' => $c['planned_at'] ?? null,
                ]);
                app(Checklists::class)->bind($child, $cat, null);
            }
            $this->recompute($master);

            app(AuditLogger::class)->log('master.created', [
                'tenant_id' => $tenant->id, 'item_type' => ServiceRequest::class, 'item_id' => $master->id,
                'new' => ['sr_number' => $master->sr_number, 'trades' => count($children)],
            ]);

            return $master;
        });

        // the master has the approval mode of the first trade's category (D5): one approval for all
        app(ApprovalModes::class)->onCreated($master->fresh(), $categories[(int) $children[0]['service_category_id']]);

        return $master->fresh();
    }

    private function makeRequest(Tenant $tenant, User $creator, array $data, ServiceCategory $cat, array $over): ServiceRequest
    {
        $sr = ServiceRequest::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id, 'sr_number' => ServiceRequest::nextSrNumber($tenant->id),
            'client_id' => $data['client_id'], 'project_site_id' => $data['project_site_id'], 'service_category_id' => $cat->id,
            'priority' => $data['priority'], 'title' => $data['title'], 'description' => $data['description'] ?? null,
            'client_po_reference' => $data['client_po_reference'] ?? null,
            'service_mode' => 'on_site', 'source' => 'front_desk', 'coverage_type' => 'out_of_coverage', 'status' => 'pending',
            'billing_status' => 'not_applicable', 'invoice_mandatory' => (bool) ($cat->invoice_mandatory ?? false),
            'enforce_geofence' => false, 'allow_signature_waiver' => false, 'created_by_user_id' => $creator->id,
            'proof_rules_snapshot' => [
                'require_before_photo' => (bool) $cat->require_before_photo, 'require_after_photo' => (bool) $cat->require_after_photo,
                'require_signature' => (bool) $cat->require_signature, 'require_checklist' => (bool) $cat->require_checklist,
                'require_per_asset_checklist' => (bool) $cat->require_per_asset_checklist,
                'snapshot_at' => now()->toIso8601String(), 'category_code' => $cat->code,
            ],
        ], $over));

        app(\App\Services\Sla\SlaClock::class)->startAtCreation($sr);
        SrStatusHistory::log($sr, null, 'pending', 'user', $creator->id);

        return $sr;
    }

    /** The master's status, worked out from its children (D4). Called after every change of a child. */
    public function recompute(ServiceRequest $master): void
    {
        $kids = ServiceRequest::withoutGlobalScopes()->where('parent_sr_id', $master->id)->pluck('status');
        if ($kids->isEmpty()) {
            return;
        }

        $done = ['completed', 'cancelled'];
        $late = ['pending_review', 'awaiting_invoice', 'completed', 'cancelled'];

        if ($kids->every(fn ($s) => $s === 'cancelled')) {
            $new = 'cancelled';
        } elseif ($kids->every(fn ($s) => in_array($s, $done, true))) {
            // the invoice is the master's (D3): when one is needed the master waits for it, like any request after QC
            $new = ($master->coverage_type === 'out_of_coverage' && $master->invoice_mandatory) ? 'awaiting_invoice' : 'completed';
            if ($master->status === 'completed') {
                return;   // never goes back
            }
        } elseif ($kids->every(fn ($s) => in_array($s, $late, true))) {
            $new = 'pending_review';
        } elseif ($kids->contains(fn ($s) => in_array($s, ['in_progress', 'pending_review', 'awaiting_invoice', 'completed'], true))) {
            $new = 'in_progress';
        } else {
            // nothing started yet: Pending until the master itself is approved
            $new = ($master->approved_by_user_id || $master->auto_approved_mode) ? 'approved' : 'pending';
        }

        if ($master->status === $new) {
            return;
        }
        $from = $master->status;
        $master->forceFill(array_filter([
            'status' => $new, 'completed_at' => $new === 'completed' ? now() : null,
        ], fn ($v) => $v !== null))->save();
        SrStatusHistory::log($master, $from, $new, 'system', null, 'Worked out from its trades');

        app(AuditLogger::class)->log('master.status_changed', [
            'tenant_id' => $master->tenant_id, 'item_type' => ServiceRequest::class, 'item_id' => $master->id,
            'old' => ['status' => $from], 'new' => ['status' => $new],
        ]);

        if (in_array($new, ['completed', 'awaiting_invoice'], true)) {
            $svc = app(NotificationService::class);
            $svc->notify('master.ready_to_invoice', $svc->usersWithRole('finance', $master->tenant_id), ['sr_number' => $master->sr_number], route('service-requests.show', $master, false));
        }
    }

    /** One approval covers every trade (D5): they become Approved, and a planned technician is assigned. */
    public function onMasterApproved(ServiceRequest $master): void
    {
        foreach (ServiceRequest::withoutGlobalScopes()->where('parent_sr_id', $master->id)->where('status', 'pending')->get() as $child) {
            $child->forceFill([
                'status' => 'approved', 'approved_by_user_id' => $master->approved_by_user_id, 'coverage_type' => $master->coverage_type,
                'contract_id' => $master->contract_id, 'auto_approved_mode' => $master->auto_approved_mode,
            ])->save();
            SrStatusHistory::log($child, 'pending', 'approved', 'system', null, 'Approved with ' . $master->sr_number);
            app(\App\Services\Sla\SlaClock::class)->startOnApproval($child->fresh());   // each trade has its own clock, from the master's approval

            $tech = $child->planned_tech_user_id ? User::withoutGlobalScopes()->find($child->planned_tech_user_id) : null;
            if ($tech && $tech->deactivated_at === null) {
                $teamLead = $tech->team_lead_id ?? ($tech->role?->code === 'team_lead' ? $tech->id : null);
                $child->forceFill([
                    'status' => 'assigned', 'assigned_tech_user_id' => $tech->id, 'team_lead_user_id' => $teamLead,
                    'scheduled_eta_at' => $child->planned_at,
                ])->save();
                SrStatusHistory::log($child, 'approved', 'assigned', 'system', null, 'Planned technician', ['technician_id' => $tech->id]);
                if ($tech->vendor_id) {
                    $svc = app(NotificationService::class);
                    $svc->notify('vendor.assigned', [$tech], ['sr_number' => $child->sr_number, 'site' => $child->site?->name, 'date' => optional($child->planned_at)->format('j M Y')], route('technician.jobs.show', $child, false));
                }
            }
        }
        $this->recompute($master);
    }

    /** Cancelling a master cancels every trade that is not Completed (D20). */
    public function cancel(ServiceRequest $master, string $reason): void
    {
        DB::transaction(function () use ($master, $reason) {
            foreach (ServiceRequest::withoutGlobalScopes()->where('parent_sr_id', $master->id)->whereNotIn('status', ['completed', 'cancelled'])->get() as $child) {
                $from = $child->status;
                $child->forceFill(['status' => 'cancelled', 'cancellation_reason' => $reason])->save();
                SrStatusHistory::log($child, $from, 'cancelled', 'user', auth()->id(), $reason);
            }
            $master->forceFill(['cancellation_reason' => $reason])->save();
            $this->recompute($master->fresh());
        });
    }

    /** The request ids that belong on the master's invoice and expense totals: the master and its trades (D7). */
    public function ids(ServiceRequest $sr): array
    {
        if (! $sr->is_master_sr) {
            return [$sr->id];
        }

        return array_merge([$sr->id], ServiceRequest::withoutGlobalScopes()->where('parent_sr_id', $sr->id)->pluck('id')->all());
    }

    /** Approved expenses of all trades, by trade, for the master page (D7). */
    public function expenseTotals(ServiceRequest $master)
    {
        return ServiceRequestExpense::withoutGlobalScopes()->where('tenant_id', $master->tenant_id)
            ->whereIn('service_request_id', $this->ids($master))->where('approval_status', 'approved')
            ->selectRaw('service_request_id, SUM(total_amount) as total')->groupBy('service_request_id')->pluck('total', 'service_request_id');
    }
}
