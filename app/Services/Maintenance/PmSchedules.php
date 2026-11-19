<?php

namespace App\Services\Maintenance;

use App\Models\Asset;
use App\Models\PmSchedule;
use App\Models\PmScheduleAsset;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\BatchService;
use App\Services\Forms\FormException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Schedule rules (Preventive-Maintenance spec PMC-01, D1 to D5, D12, D14, D15, D21). */
class PmSchedules
{
    public function enabled(?Tenant $tenant): bool
    {
        return (bool) $tenant?->hasFeature('pm_time');
    }

    /**
     * Checks the assets and the frequency of a schedule and returns the assets.
     *
     * @param int[] $assetIds
     * @return \Illuminate\Support\Collection<int,Asset>
     */
    public function checkAssets(int $tenantId, int $siteId, int $categoryId, array $assetIds)
    {
        $assetIds = array_values(array_unique(array_map('intval', $assetIds)));
        if (count($assetIds) < 1 || count($assetIds) > BatchService::MAX) {
            throw new FormException('PM_ASSET_COUNT', 'Choose one asset, or 2 to 200 assets for a batch.');
        }
        $assets = Asset::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $assetIds)->get();
        if ($assets->count() !== count($assetIds)) {
            throw new FormException('PM_ASSET_SITE_MISMATCH', 'All assets in a schedule must be at the same site.');
        }
        foreach ($assets as $a) {
            if ((int) $a->project_site_id !== $siteId) {
                throw new FormException('PM_ASSET_SITE_MISMATCH', 'All assets in a schedule must be at the same site.');
            }
            if ((int) $a->service_category_id !== $categoryId) {
                throw new FormException('PM_ASSET_CATEGORY_MISMATCH', 'All assets must have the same service category.');
            }
        }

        return $assets;
    }

    public function create(Tenant $tenant, User $user, array $data, array $assetIds): PmSchedule
    {
        $this->checkFrequency($data);
        $this->checkAssets($tenant->id, (int) $data['project_site_id'], (int) $data['service_category_id'], $assetIds);

        return DB::transaction(function () use ($tenant, $user, $data, $assetIds) {
            $schedule = PmSchedule::withoutGlobalScopes()->create($this->fill($data) + [
                'tenant_id' => $tenant->id, 'status' => 'active', 'next_due_date' => $data['first_due_date'], 'created_by_user_id' => $user->id,
            ]);
            $this->syncAssets($schedule, $assetIds);
            $this->audit('pm_schedule.created', $schedule, ['name' => $schedule->name, 'first_due_date' => $schedule->first_due_date->toDateString()]);

            return $schedule;
        });
    }

    public function update(PmSchedule $schedule, array $data, array $assetIds, Carbon $today): PmSchedule
    {
        if ($schedule->status === 'ended') {
            throw new FormException('PM_SCHEDULE_ENDED', 'This schedule has ended and cannot be changed.');
        }
        $this->checkFrequency($data);
        $this->checkAssets($schedule->tenant_id, (int) $data['project_site_id'], (int) $data['service_category_id'], $assetIds);

        $old = $schedule->only(['name', 'frequency', 'interval_value', 'first_due_date', 'next_due_date', 'lead_days', 'priority', 'default_technician_user_id', 'contract_id', 'skip_if_open']);
        $patternChanged = $data['frequency'] !== $schedule->frequency
            || (int) ($data['interval_value'] ?? 0) !== (int) $schedule->interval_value
            || Carbon::parse($data['first_due_date'])->toDateString() !== $schedule->first_due_date->toDateString();

        DB::transaction(function () use ($schedule, $data, $assetIds, $patternChanged, $today) {
            $schedule->fill($this->fill($data));
            if (! empty($data['next_due_date'])) {
                $schedule->next_due_date = $data['next_due_date'];              // D15: the owner asked for this date
            } elseif ($patternChanged) {
                $schedule->next_due_date = Frequency::onOrAfter($schedule->first_due_date, $schedule->frequency, $schedule->interval_value, $today);
            }
            $schedule->save();
            $this->syncAssets($schedule, $assetIds);
        });

        $this->audit('pm_schedule.updated', $schedule, $schedule->only(array_keys($old)), $old);   // D21: old and new values

        return $schedule->fresh();
    }

    public function pause(PmSchedule $schedule): void
    {
        $this->mustBeOpen($schedule);
        $schedule->forceFill(['status' => 'paused'])->save();
        $this->audit('pm_schedule.paused', $schedule, []);
    }

    /** Resume moves the next date to the next one on or after today. Nothing is caught up (D14). */
    public function resume(PmSchedule $schedule, Carbon $today): void
    {
        $this->mustBeOpen($schedule);
        $schedule->forceFill([
            'status' => 'active',
            'next_due_date' => Frequency::onOrAfter($schedule->first_due_date, $schedule->frequency, $schedule->interval_value, $today),
        ])->save();
        $this->audit('pm_schedule.resumed', $schedule, ['next_due_date' => $schedule->next_due_date->toDateString()]);
    }

    public function end(PmSchedule $schedule, Carbon $today): void
    {
        $this->mustBeOpen($schedule);
        $schedule->forceFill(['status' => 'ended', 'end_date' => $schedule->end_date && $schedule->end_date->lt($today) ? $schedule->end_date : $today->toDateString()])->save();
        $this->audit('pm_schedule.ended', $schedule, []);
    }

    /** A schedule that created a request is ended, never deleted (D14). */
    public function delete(PmSchedule $schedule): void
    {
        $made = ServiceRequest::withoutGlobalScopes()->where('pm_schedule_id', $schedule->id)->exists();
        if ($made) {
            throw new FormException('PM_HAS_REQUESTS', 'A schedule that created requests cannot be deleted. End it instead.');
        }
        $this->audit('pm_schedule.deleted', $schedule, ['name' => $schedule->name]);
        PmScheduleAsset::withoutGlobalScopes()->where('pm_schedule_id', $schedule->id)->delete();
        $schedule->delete();
    }

    private function mustBeOpen(PmSchedule $schedule): void
    {
        if ($schedule->status === 'ended') {
            throw new FormException('PM_SCHEDULE_ENDED', 'This schedule has ended and cannot be changed.');
        }
    }

    private function checkFrequency(array $data): void
    {
        if (! Frequency::valid($data['frequency'] ?? '', isset($data['interval_value']) ? (int) $data['interval_value'] : null)) {
            throw new FormException('PM_FREQUENCY_INVALID', 'Choose a frequency. Every N days needs 7 to 730, every N months 1 to 36.');
        }
    }

    private function fill(array $d): array
    {
        $isN = in_array($d['frequency'], ['every_n_days', 'every_n_months'], true);

        return [
            'name' => $d['name'], 'project_site_id' => $d['project_site_id'], 'service_category_id' => $d['service_category_id'],
            'frequency' => $d['frequency'], 'interval_value' => $isN ? (int) $d['interval_value'] : null,
            'first_due_date' => $d['first_due_date'], 'lead_days' => (int) ($d['lead_days'] ?? 0), 'priority' => $d['priority'] ?? 'medium',
            'form_template_id' => $d['form_template_id'] ?? null, 'default_technician_user_id' => $d['default_technician_user_id'] ?? null,
            'contract_id' => $d['contract_id'] ?? null, 'skip_if_open' => (bool) ($d['skip_if_open'] ?? false),
            'end_date' => $d['end_date'] ?? null, 'notes' => $d['notes'] ?? null,
        ];
    }

    private function syncAssets(PmSchedule $schedule, array $assetIds): void
    {
        PmScheduleAsset::withoutGlobalScopes()->where('pm_schedule_id', $schedule->id)->delete();
        foreach (array_unique(array_map('intval', $assetIds)) as $id) {
            PmScheduleAsset::withoutGlobalScopes()->create(['pm_schedule_id' => $schedule->id, 'asset_id' => $id, 'tenant_id' => $schedule->tenant_id]);
        }
    }

    private function audit(string $action, PmSchedule $schedule, array $new, ?array $old = null): void
    {
        app(AuditLogger::class)->log($action, [
            'tenant_id' => $schedule->tenant_id, 'item_type' => PmSchedule::class, 'item_id' => $schedule->id, 'new' => $new, 'old' => $old,
        ]);
    }
}
