<?php

namespace App\Services\Trades;

use App\Models\Asset;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\SrStatusHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\Checklists;
use App\Services\Forms\FormException;
use App\Services\Notifications\NotificationService;

/**
 * Depot service: equipment brought to the company's own workshop (Multi-Trade-Vendors-Depot spec DEP-01 to DEP-04, D12 to D17).
 * No new request statuses: the stage is its own field (assumption 15).
 */
class Depot
{
    public const STAGES = ['received', 'in_workshop', 'ready_for_return', 'returned'];
    public const LABELS = ['received' => 'Received', 'in_workshop' => 'In workshop', 'ready_for_return' => 'Ready for return', 'returned' => 'Returned'];
    public const MAX_ASSETS = 20;

    public function enabled(?Tenant $tenant): bool
    {
        return (bool) $tenant?->hasFeature('depot');
    }

    /** A workshop is a site of this company with no client (D13). */
    public function workshop(Tenant $tenant, int $siteId): ?ProjectSite
    {
        return ProjectSite::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('site_type', 'tenant_workshop')->whereNull('client_id')->find($siteId);
    }

    /**
     * Checks a depot request before it is created and returns the workshop.
     *
     * @param int[] $assetIds
     */
    public function validate(Tenant $tenant, ServiceCategory $category, ?int $workshopId, array $assetIds): ProjectSite
    {
        if (! $this->enabled($tenant)) {
            throw new FormException('FEATURE_NOT_IN_PLAN', 'Not in your plan.', 403);
        }
        if (! $category->allow_depot_mode) {
            throw new FormException('DEPOT_NOT_ALLOWED', 'This category cannot be serviced at the workshop.');
        }
        $workshop = $workshopId ? $this->workshop($tenant, $workshopId) : null;
        if (! $workshop) {
            throw new FormException('DEPOT_NOT_ALLOWED', 'Choose a workshop for this request.');
        }
        if (count($assetIds) > self::MAX_ASSETS) {
            throw new FormException('DEPOT_NOT_ALLOWED', 'A depot request can cover up to 20 assets.');
        }

        return $workshop;
    }

    /** The request is bound to the intake and return checklists of its category (D15). */
    public function bind(ServiceRequest $sr, ServiceCategory $category): void
    {
        $checklists = app(Checklists::class);
        $sr->forceFill([
            'intake_form_version_id' => $checklists->publishedVersionOf($category->intake_form_template_id, $category->tenant_id)?->id,
            'return_form_version_id' => $checklists->publishedVersionOf($category->return_form_template_id, $category->tenant_id)?->id,
        ])->save();
    }

    /**
     * Moves the stage forward. Going back needs a reason (and sr.assign full, checked by the caller).
     * The intake checklist is needed before `in_workshop`, the return checklist before `returned` (D15).
     */
    public function changeStage(ServiceRequest $sr, string $to, User $user, ?string $backReason = null): void
    {
        if ($sr->service_mode !== 'depot' || ! in_array($to, self::STAGES, true)) {
            throw new FormException('DEPOT_NOT_ALLOWED', 'This request is not serviced at the workshop.');
        }
        $from  = $sr->depot_stage;
        $order = array_flip(self::STAGES);
        $back  = $from !== null && $order[$to] <= $order[$from];

        if ($back && trim((string) $backReason) === '') {
            throw new FormException('DEPOT_STAGE_BACK', 'Give a reason to move the stage back.');
        }
        if (! $back && $from === null && $to !== 'received') {
            throw new FormException('DEPOT_STAGE_ORDER', 'The first stage is Received.');
        }
        if (! $back && $from !== null && $order[$to] !== $order[$from] + 1) {
            throw new FormException('DEPOT_STAGE_ORDER', 'Move one stage at a time.');
        }

        // intake is filled in at receiving, so it is needed before the work starts in the workshop
        if (! $back && $to === 'in_workshop' && $sr->intake_form_version_id && ! $sr->intake_result) {
            throw new FormException('DEPOT_CHECKLIST_REQUIRED', 'Complete the intake checklist first.');
        }
        if (! $back && $to === 'returned' && $sr->return_form_version_id && ! $sr->return_result) {
            throw new FormException('DEPOT_CHECKLIST_REQUIRED', 'Complete the return checklist first.');
        }

        $sr->forceFill(array_filter([
            'depot_stage' => $to,
            'depot_received_at' => $to === 'received' ? now() : null,
            'depot_returned_at' => $to === 'returned' ? now() : null,
        ], fn ($v) => $v !== null))->save();

        app(AuditLogger::class)->log('depot.stage_changed', [
            'tenant_id' => $sr->tenant_id, 'item_type' => ServiceRequest::class, 'item_id' => $sr->id,
            'old' => ['stage' => $from], 'new' => ['stage' => $to, 'reason' => $backReason],
        ]);

        $event = match ($to) { 'received' => 'depot.received', 'ready_for_return' => 'depot.ready_for_return', default => null };
        if ($event) {
            $svc = app(NotificationService::class);
            $svc->notify($event, $svc->usersWithRole('front_desk', $sr->tenant_id), [
                'sr_number' => $sr->sr_number, 'asset' => $sr->primaryAsset?->name ?? 'equipment',
            ], route('service-requests.show', $sr, false));
            // blueprint 9.2 (client email while WhatsApp is paused): the client contact gets the same two stages
            if (! $sr->parent_sr_id) {
                app(\App\Services\Notifications\EventMessages::class)->client($sr, $event);
            }
        }
    }

    /** A depot request cannot be completed before the equipment is returned (D14). */
    public function assertReturned(ServiceRequest $sr): void
    {
        if ($sr->service_mode === 'depot' && $sr->depot_stage !== 'returned') {
            throw new FormException('DEPOT_RETURN_FIRST', 'Return the equipment before you complete this request.');
        }
    }

    /** "At workshop {name}" for an asset with an open depot request that is between received and ready (D16). */
    public function locationOf(Asset $asset): ?string
    {
        $open = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $asset->tenant_id)->where('service_mode', 'depot')
            ->whereIn('depot_stage', ['received', 'in_workshop', 'ready_for_return'])->whereNotIn('status', ['completed', 'cancelled'])
            ->where(fn ($q) => $q->where('primary_asset_id', $asset->id)
                ->orWhereIn('id', \App\Models\ServiceRequestAsset::withoutGlobalScopes()->where('asset_id', $asset->id)->select('service_request_id')))
            ->first();

        return $open ? 'At workshop ' . ($open->site?->name ?? '') : null;
    }
}
