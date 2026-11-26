<?php

namespace App\Services\Forms;

use App\Models\Asset;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAsset;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Collection;

/** A request over many assets of one site (BAT-01 to BAT-03, D13 to D17, D26). */
class BatchService
{
    public const MIN = 2;
    public const MAX = 200;

    /**
     * Checks a batch before the request is created. Returns the assets, in the order given.
     *
     * @param  int[] $assetIds
     * @return Collection<int,Asset>
     */
    public function validate(ServiceCategory $category, int $siteId, array $assetIds, bool $confirmOpen): Collection
    {
        $assetIds = array_values(array_unique(array_map('intval', $assetIds)));
        if (count($assetIds) < self::MIN || count($assetIds) > self::MAX) {
            throw new FormException('BATCH_SIZE', 'A batch needs between 2 and 200 assets.');
        }
        if (! $category->allow_multi_asset_batch) {
            throw new FormException('BATCH_CATEGORY_NOT_ALLOWED', 'This category does not allow batch requests.');
        }

        $assets = Asset::withoutGlobalScopes()->where('tenant_id', $category->tenant_id)->whereIn('id', $assetIds)->get()->keyBy('id');
        if ($assets->count() !== count($assetIds)) {
            throw new FormException('BATCH_SITE_MISMATCH', 'All assets in a batch must be at the same site.');
        }
        foreach ($assets as $a) {
            if ((int) $a->project_site_id !== $siteId) {
                throw new FormException('BATCH_SITE_MISMATCH', 'All assets in a batch must be at the same site.');
            }
            if ($a->service_category_id && (int) $a->service_category_id !== (int) $category->id) {
                throw new FormException('BATCH_CATEGORY_MISMATCH', 'All assets must have the same service category.');
            }
            if ($a->status === 'decommissioned') {
                throw new FormException('BATCH_SITE_MISMATCH', "{$a->name} is decommissioned and cannot be in a batch.");
            }
        }

        if (! $confirmOpen) {
            foreach ($assets as $a) {
                if ($open = $this->openRequestFor($a, $category)) {
                    throw new FormException('BATCH_ASSET_OPEN', "{$a->name} is already in open request {$open->sr_number}. Tick the box to include it anyway.");
                }
            }
        }

        return collect($assetIds)->map(fn ($id) => $assets[$id]);
    }

    /** An open request of the same category that already covers the asset, as primary asset or as a batch row (D16). */
    public function openRequestFor(Asset $asset, ServiceCategory $category, ?int $exceptSrId = null): ?ServiceRequest
    {
        $open = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $asset->tenant_id)
            ->where('service_category_id', $category->id)->whereNotIn('status', ['completed', 'cancelled'])
            ->when($exceptSrId, fn ($q) => $q->where('id', '!=', $exceptSrId));

        return (clone $open)->where('primary_asset_id', $asset->id)->first()
            ?? (clone $open)->whereIn('id', ServiceRequestAsset::withoutGlobalScopes()->where('asset_id', $asset->id)->select('service_request_id'))->first();
    }

    /** Creates the rows of the request, each bound to its own published checklist version. */
    public function attach(ServiceRequest $sr, Collection $assets, ServiceCategory $category, ?int $scheduleTemplateId = null): void
    {
        $checklists = app(Checklists::class);
        foreach ($assets as $asset) {
            ServiceRequestAsset::withoutGlobalScopes()->create([
                'tenant_id' => $sr->tenant_id, 'service_request_id' => $sr->id, 'asset_id' => $asset->id,
                'inspection_status' => 'pending',
                'form_template_version_id' => $checklists->versionFor($category, $asset, $scheduleTemplateId)?->id,
            ]);
        }
    }

    public function setStatus(ServiceRequestAsset $row, string $status, ?string $remarks): void
    {
        if (! in_array($status, ServiceRequestAsset::STATUSES, true)) {
            throw new FormException('BATCH_STATUS_INVALID', 'Choose a status for this asset.');
        }
        $remarks = trim((string) $remarks);
        if ($status === 'skipped' && $remarks === '') {
            throw new FormException('BATCH_REMARK_REQUIRED', 'Add a remark to skip this asset.');
        }
        $row->forceFill([
            'inspection_status'  => $status,
            'technician_remarks' => $remarks !== '' ? mb_substr($remarks, 0, 2000) : $row->technician_remarks,
        ])->save();
    }

    /** @return array<string,int> a count for each status, plus 'total' */
    public function summary(ServiceRequest $sr): array
    {
        $rows = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->get();
        $out  = ['total' => $rows->count()];
        foreach (ServiceRequestAsset::STATUSES as $s) {
            $out[$s] = $rows->where('inspection_status', $s)->count();
        }

        return $out;
    }

    /** "17 serviced, 2 need parts, 1 failed" */
    public function summaryLine(ServiceRequest $sr): string
    {
        $s = $this->summary($sr);
        $parts = [];
        foreach ([
            'serviced_ok' => 'serviced', 'repaired' => 'repaired', 'needs_parts' => 'need parts',
            'failed_unsafe' => 'failed', 'skipped' => 'skipped', 'pending' => 'pending',
        ] as $k => $label) {
            if ($s[$k] > 0) {
                $parts[] = "{$s[$k]} {$label}";
            }
        }

        return implode(', ', $parts);
    }

    /** The request completes: every finished asset gets its service date (D17). */
    public function onCompleted(ServiceRequest $sr): void
    {
        ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)
            ->where('inspection_status', '!=', 'pending')->whereNull('completed_at')
            ->update(['completed_at' => $sr->completed_at ?? now()]);
    }

    /** Punch-out of a batch with a failed asset tells the Operations Manager (D26). */
    public function onPunchedOut(ServiceRequest $sr): void
    {
        $failed = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->where('inspection_status', 'failed_unsafe')->count();
        if ($failed < 1) {
            return;
        }
        $svc = app(NotificationService::class);
        $svc->notify('batch.unsafe_asset', $svc->usersWithRole('operations_manager', $sr->tenant_id), [
            'sr_number' => $sr->sr_number, 'count' => $failed,
        ], \App\Support\TenantUrl::route($sr->tenant_id, 'service-requests.show', $sr));
    }
}
