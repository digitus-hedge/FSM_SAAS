<?php

namespace Tests\Feature\Maintenance;

use App\Models\PmSchedule;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Services\Maintenance\PmGenerator;
use App\Services\Maintenance\PmSchedules;
use Carbon\Carbon;
use Tests\Feature\Forms\FormsTestCase;

/**
 * Shared set-up for the maintenance tests (docs/specs/Preventive-Maintenance.md, section 10).
 * "Today" is 19 November 2026, 14:00 in Dubai (the test company's time zone).
 */
abstract class MaintenanceTestCase extends FormsTestCase
{
    protected const TODAY = '2026-11-19';

    protected function setUp(): void
    {
        parent::setUp();
        PmGenerator::$maxPerRun = 500;
    }

    protected function tearDown(): void
    {
        PmGenerator::$maxPerRun = 500;
        parent::tearDown();
    }

    protected function today(): Carbon
    {
        return Carbon::parse(self::TODAY);
    }

    /** A schedule through the service, with one asset of the category unless told otherwise. */
    protected function schedule(array $over = [], ?array $assetIds = null, ?ServiceCategory $cat = null): PmSchedule
    {
        $cat ??= $this->makeCategory(['approval_mode' => 'manual']);
        $assetIds ??= [$this->makeAsset($cat)->id];

        return app(PmSchedules::class)->create($this->t, $this->u['operations_manager'], array_merge([
            'name' => 'Generator service', 'project_site_id' => $this->site->id, 'service_category_id' => $cat->id,
            'frequency' => 'monthly', 'first_due_date' => '2026-11-26', 'lead_days' => 7, 'priority' => 'medium', 'skip_if_open' => true,
        ], $over), $assetIds);
    }

    protected function runJob(?string $date = null): array
    {
        return app(PmGenerator::class)->runForTenant($this->t->fresh(), Carbon::parse($date ?? self::TODAY));
    }

    /** @return \Illuminate\Support\Collection<int,ServiceRequest> */
    protected function pmRequests(?PmSchedule $s = null)
    {
        return ServiceRequest::withoutGlobalScopes()->where('tenant_id', $this->t->id)->whereNotNull('pm_schedule_id')
            ->when($s, fn ($q) => $q->where('pm_schedule_id', $s->id))->orderBy('id')->get();
    }

    protected function pmBody(array $over = []): array
    {
        $cat = $this->makeCategory();

        return array_merge([
            'name' => 'Pumps', 'project_site_id' => $this->site->id, 'service_category_id' => $cat->id,
            'asset_ids' => [$this->makeAsset($cat)->id], 'frequency' => 'monthly', 'first_due_date' => '2026-12-01',
            'lead_days' => 7, 'priority' => 'medium', 'skip_if_open' => 1,
        ], $over);
    }
}
