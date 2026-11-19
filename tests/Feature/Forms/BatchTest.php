<?php

namespace Tests\Feature\Forms;

use App\Models\Asset;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAsset;
use App\Models\UserNotification;
use PHPUnit\Framework\Attributes\Test;

/** Forms-Approvals-Batch spec 10.6: batch requests (FAB-050 to FAB-060, scenario 11 of blueprint 13.3). */
class BatchTest extends FormsTestCase
{
    private ServiceCategory $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cat = $this->makeCategory();
    }

    /** @return int[] ids of $n new assets of the category at the test site */
    private function assets(int $n, ?ServiceCategory $cat = null): array
    {
        return collect(range(1, $n))->map(fn () => $this->makeAsset($cat ?? $this->cat)->id)->all();
    }

    private function raiseBatch(array $ids, array $extra = [])
    {
        return $this->as('front_desk')->post(route('service-requests.store'), $this->body($this->cat, ['asset_ids' => $ids] + $extra));
    }

    private function batchOf(int $n, bool $inProgress = true): ServiceRequest
    {
        $this->raiseBatch($this->assets($n))->assertSessionHasNoErrors();
        $sr = ServiceRequest::withoutGlobalScopes()->latest('id')->first();
        if ($inProgress) {
            $sr->forceFill(['status' => 'in_progress', 'assigned_tech_user_id' => $this->u['field_technician']->id, 'first_punch_in_at' => now()->subHour(), 'proof_rules_snapshot' => []])->save();
        }

        return $sr->fresh();
    }

    private function setStatus(ServiceRequestAsset $row, string $status, array $extra = [])
    {
        return $this->as('field_technician')->post(route('technician.jobs.batch.asset.save', [$row->service_request_id, $row]), ['inspection_status' => $status] + $extra);
    }

    #[Test]
    public function fab_050_a_batch_of_twenty_assets_is_one_request_with_twenty_pending_rows(): void
    {
        $sr = $this->batchOf(20, inProgress: false);

        $this->assertSame(1, ServiceRequest::withoutGlobalScopes()->count());
        $rows = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->get();
        $this->assertCount(20, $rows);
        $this->assertSame(['pending'], $rows->pluck('inspection_status')->unique()->all());
        $this->assertNull($sr->primary_asset_id);
    }

    #[Test]
    public function fab_051_a_batch_needs_between_2_and_200_assets(): void
    {
        $this->raiseBatch($this->assets(1))->assertSessionHas('error', 'A batch needs between 2 and 200 assets.');
        $this->assertSame(0, ServiceRequest::withoutGlobalScopes()->count());

        $this->raiseBatch(range(1, 201))->assertSessionHas('error', 'A batch needs between 2 and 200 assets.');
        $this->assertSame(0, ServiceRequest::withoutGlobalScopes()->count());
    }

    #[Test]
    public function fab_052_an_asset_from_another_site_is_refused(): void
    {
        $ids = $this->assets(2);
        $site2 = ProjectSite::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'name' => 'Other site']);
        $ids[] = $this->makeAsset($this->cat, ['project_site_id' => $site2->id])->id;

        $this->raiseBatch($ids)->assertSessionHas('error', 'All assets in a batch must be at the same site.');
        $this->assertSame(0, ServiceRequestAsset::withoutGlobalScopes()->count());
    }

    #[Test]
    public function an_asset_of_another_category_is_refused(): void
    {
        $ids = $this->assets(2);
        $ids[] = $this->makeAsset($this->makeCategory())->id;

        $this->raiseBatch($ids)->assertSessionHas('error', 'All assets must have the same service category.');
    }

    #[Test]
    public function fab_053_a_category_that_does_not_allow_batch_is_refused(): void
    {
        $this->cat->forceFill(['allow_multi_asset_batch' => false])->save();

        $this->raiseBatch($this->assets(3))->assertSessionHas('error', 'This category does not allow batch requests.');
        $this->assertSame(0, ServiceRequest::withoutGlobalScopes()->count());
    }

    #[Test]
    public function fab_054_an_asset_already_in_an_open_batch_of_the_same_category_needs_a_tick(): void
    {
        $ids = $this->assets(3);
        $first = $this->batchOf(2, inProgress: false);   // an open batch of two other assets
        $shared = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $first->id)->first()->asset_id;

        $this->raiseBatch([$shared, $ids[0], $ids[1]])->assertSessionHas('error');
        $this->assertSame(1, ServiceRequest::withoutGlobalScopes()->count());

        $this->raiseBatch([$shared, $ids[0], $ids[1]], ['confirm_open' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, ServiceRequest::withoutGlobalScopes()->count());
    }

    #[Test]
    public function fab_055_mixed_outcomes_give_the_count_line_and_tell_the_operations_manager(): void
    {
        $sr   = $this->batchOf(20);
        $rows = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->get();

        foreach ($rows as $i => $row) {
            $status = $i < 17 ? 'serviced_ok' : ($i < 19 ? 'needs_parts' : 'failed_unsafe');
            $this->setStatus($row, $status)->assertRedirect();
        }
        $this->assertSame('17 serviced, 2 need parts, 1 failed', app(\App\Services\Forms\BatchService::class)->summaryLine($sr));

        $this->punchOut($sr)->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $sr->fresh()->status);
        $this->assertSame(1, UserNotification::where('event_key', 'batch.unsafe_asset')->where('user_id', $this->u['operations_manager']->id)->count());

        $this->as('operations_manager')->get(route('service-requests.show', $sr))->assertOk()->assertSee('17 serviced, 2 need parts, 1 failed');
    }

    #[Test]
    public function fab_056_punch_out_with_one_asset_still_pending_is_refused(): void
    {
        $sr   = $this->batchOf(3);
        $rows = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->get();
        $this->setStatus($rows[0], 'serviced_ok');
        $this->setStatus($rows[1], 'repaired');

        $this->punchOut($sr)->assertSessionHasErrors(['identifier' => '1 assets are still pending. Mark each one before you finish.']);
        $this->assertSame('in_progress', $sr->fresh()->status);
    }

    #[Test]
    public function fab_057_when_every_asset_is_final_the_request_goes_to_pending_review(): void
    {
        $sr = $this->batchOf(2);
        foreach (ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->get() as $row) {
            $this->setStatus($row, 'serviced_ok');
        }
        $this->punchOut($sr)->assertSessionHasNoErrors();

        $this->assertSame('pending_review', $sr->fresh()->status);
    }

    #[Test]
    public function fab_058_completing_the_batch_writes_each_assets_service_date(): void
    {
        $sr = $this->batchOf(2);
        $rows = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->get();
        $this->setStatus($rows[0], 'serviced_ok');
        $this->setStatus($rows[1], 'skipped', ['technician_remarks' => 'Locked room']);
        $this->punchOut($sr);

        $sr->fresh()->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

        foreach ($rows as $row) {
            $this->assertNotNull($row->fresh()->completed_at);
        }

        $asset = Asset::withoutGlobalScopes()->find($rows[0]->asset_id);
        $this->t->plan->forceFill(['features' => array_merge($this->t->plan->features, ['assets_full' => true, 'assets_basic' => true])])->save();
        $this->actingAs($this->u['company_admin'])->get($this->onHost($this->t, "/admin/assets/{$asset->id}"))->assertOk()->assertSee($sr->sr_number);
    }

    #[Test]
    public function fab_059_skipping_an_asset_without_a_remark_is_refused(): void
    {
        $sr  = $this->batchOf(2);
        $row = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->first();

        $this->setStatus($row, 'skipped')->assertSessionHas('error', 'Add a remark to skip this asset.');
        $this->assertSame('pending', $row->fresh()->inspection_status);

        $this->setStatus($row, 'skipped', ['technician_remarks' => 'No access'])->assertRedirect();
        $this->assertSame('skipped', $row->fresh()->inspection_status);
    }

    #[Test]
    public function fab_060_with_a_per_asset_checklist_required_an_asset_without_one_blocks_punch_out(): void
    {
        $tpl = $this->makeTemplate();
        $this->cat->forceFill(['active_form_template_id' => $tpl->id, 'require_per_asset_checklist' => true, 'require_checklist' => true])->save();
        $this->raiseBatch($this->assets(2))->assertSessionHasNoErrors();
        $sr = ServiceRequest::withoutGlobalScopes()->latest('id')->first();
        $sr->forceFill(['status' => 'in_progress', 'assigned_tech_user_id' => $this->u['field_technician']->id, 'first_punch_in_at' => now()->subHour()])->save();
        $rows = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->get();
        $this->assertNotNull($rows[0]->form_template_version_id);

        // statuses set, but no checklist yet
        foreach ($rows as $row) {
            $this->setStatus($row, 'serviced_ok');
        }
        $this->punchOut($sr)->assertSessionHasErrors(['identifier' => 'Complete the checklist before you finish: 4 required answers are missing.']);

        // checklist for the first asset only: still blocked, now for the other asset's two answers
        $this->as('field_technician')->post(route('technician.jobs.batch.asset.save', [$sr, $rows[0]]), [
            'inspection_status' => 'serviced_ok', 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'answers' => $this->fullAnswers(),
        ])->assertRedirect();
        $this->assertSame('ok', $rows[0]->fresh()->checklist_result);
        $this->punchOut($sr)->assertSessionHasErrors(['identifier' => 'Complete the checklist before you finish: 2 required answers are missing.']);

        $this->as('field_technician')->post(route('technician.jobs.batch.asset.save', [$sr, $rows[1]]), [
            'inspection_status' => 'serviced_ok', 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'answers' => $this->fullAnswers(),
        ]);
        $this->punchOut($sr)->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $sr->fresh()->status);
    }

    #[Test]
    public function a_skipped_asset_needs_no_checklist(): void
    {
        $tpl = $this->makeTemplate();
        $this->cat->forceFill(['active_form_template_id' => $tpl->id, 'require_per_asset_checklist' => true])->save();
        $this->raiseBatch($this->assets(2));
        $sr = ServiceRequest::withoutGlobalScopes()->latest('id')->first();
        $sr->forceFill(['status' => 'in_progress', 'assigned_tech_user_id' => $this->u['field_technician']->id])->save();
        $rows = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->get();

        $this->as('field_technician')->post(route('technician.jobs.batch.asset.save', [$sr, $rows[0]]), [
            'inspection_status' => 'serviced_ok', 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'answers' => $this->fullAnswers(),
        ]);
        $this->setStatus($rows[1], 'skipped', ['technician_remarks' => 'Out of order']);

        $this->punchOut($sr)->assertSessionHasNoErrors();
    }

    #[Test]
    public function one_approval_covers_the_whole_batch_and_the_asset_screens_open(): void
    {
        $sr = $this->batchOf(2);
        $row = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->first();

        $this->as('field_technician')->get(route('technician.jobs.batch', $sr))->assertOk()->assertSee('0 of 2 done');
        $this->as('field_technician')->get(route('technician.jobs.batch.asset', [$sr, $row]))->assertOk()->assertSee('Meter reading');
        $this->as('field_technician')->get(route('technician.jobs.show', $sr))->assertOk()->assertSee(route('technician.jobs.batch', $sr, false), false);
    }

    #[Test]
    public function an_asset_row_of_another_request_is_a_404(): void
    {
        $a = $this->batchOf(2);
        $b = $this->batchOf(2);
        $rowOfB = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $b->id)->first();

        $this->as('field_technician')->get(route('technician.jobs.batch.asset', [$a, $rowOfB]))->assertNotFound();
    }
}
