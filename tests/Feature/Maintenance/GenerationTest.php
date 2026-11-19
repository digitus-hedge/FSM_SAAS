<?php

namespace Tests\Feature\Maintenance;

use App\Models\Contract;
use App\Models\PmSchedule;
use App\Models\PmScheduleRun;
use App\Models\ProjectSite;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAsset;
use App\Models\Tenant;
use App\Models\UserNotification;
use App\Models\UserTimeOff;
use App\Services\Maintenance\PmGenerator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

/** Preventive-Maintenance spec 10.2 to 10.5 and 10.7: generation, batch, contract, defaults and events (PMC-010 to PMC-064). */
class GenerationTest extends MaintenanceTestCase
{
    // ───────────── 10.2 daily generation ─────────────

    #[Test]
    public function pmc_010_a_request_is_made_lead_days_before_the_due_date_and_scheduled_for_the_due_date(): void
    {
        $s = $this->schedule(['first_due_date' => '2026-11-26', 'lead_days' => 7]);   // 26 Nov minus 7 days is today

        $r = $this->runJob();
        $this->assertSame(1, $r['created']);

        $sr = $this->pmRequests($s)->first();
        $this->assertSame('pm_auto', $sr->source);
        $this->assertSame($s->id, $sr->pm_schedule_id);
        $this->assertSame('2026-11-26', $sr->pm_due_date->toDateString());
        $this->assertSame('2026-11-26', Carbon::parse($sr->scheduled_eta_at)->setTimezone('Asia/Dubai')->toDateString());
        $this->assertSame('pending', $sr->status);
        $this->assertSame('Maintenance: Generator service', $sr->title);
        $this->assertSame('2026-12-26', $s->fresh()->next_due_date->toDateString());
    }

    #[Test]
    public function a_due_date_not_yet_within_the_lead_time_creates_nothing(): void
    {
        $this->schedule(['first_due_date' => '2026-11-27', 'lead_days' => 7]);   // starts tomorrow

        $this->assertSame(0, $this->runJob()['created']);
        $this->assertCount(0, $this->pmRequests());
    }

    #[Test]
    public function pmc_011_running_the_job_twice_the_same_day_creates_one_request(): void
    {
        $s = $this->schedule();
        $this->runJob();
        $this->runJob();

        $this->assertCount(1, $this->pmRequests($s));
        $this->assertSame(1, PmScheduleRun::withoutGlobalScopes()->where('pm_schedule_id', $s->id)->count());
    }

    #[Test]
    public function pmc_012_two_workers_at_once_cannot_make_two_requests_for_the_same_due_date(): void
    {
        $s = $this->schedule();
        $this->runJob();

        // a second worker that read the schedule before the first one moved it: the date is back, the run row and the unique key stop it
        $s->forceFill(['next_due_date' => '2026-11-26'])->save();
        $this->runJob();
        $this->assertCount(1, $this->pmRequests($s));

        // and the database refuses a second request for the same schedule and date on its own
        $this->expectException(\Illuminate\Database\QueryException::class);
        ServiceRequest::withoutGlobalScopes()->create([
            'tenant_id' => $this->t->id, 'sr_number' => 'SR-DUP', 'client_id' => $this->client->id, 'project_site_id' => $this->site->id,
            'service_category_id' => $s->service_category_id, 'title' => 'dup', 'proof_rules_snapshot' => [],
            'pm_schedule_id' => $s->id, 'pm_due_date' => '2026-11-26',
        ]);
    }

    #[Test]
    public function pmc_013_the_command_acts_at_06_00_company_time_only(): void
    {
        $s = $this->schedule();

        Carbon::setTestNow(Carbon::parse('2026-11-19 01:00:00', 'UTC'));   // 05:00 in Dubai
        Artisan::call('pm:daily');
        $this->assertCount(0, $this->pmRequests($s));

        Carbon::setTestNow(Carbon::parse('2026-11-19 02:00:00', 'UTC'));   // 06:00 in Dubai
        Artisan::call('pm:daily');
        $this->assertCount(1, $this->pmRequests($s));
    }

    #[Test]
    public function pmc_014_a_paused_schedule_creates_nothing(): void
    {
        $s = $this->schedule();
        app(\App\Services\Maintenance\PmSchedules::class)->pause($s);

        $this->runJob();
        $this->assertCount(0, $this->pmRequests($s));
    }

    #[Test]
    public function pmc_015_with_skip_if_open_an_open_request_of_the_same_schedule_blocks_the_next_one(): void
    {
        $s = $this->schedule(['first_due_date' => '2026-11-20', 'frequency' => 'weekly']);
        $this->runJob();                       // 20 Nov made
        $this->runJob('2026-11-26');           // 27 Nov is due (lead 7): the first one is still open

        $this->assertCount(1, $this->pmRequests($s));
        $run = PmScheduleRun::withoutGlobalScopes()->where('pm_schedule_id', $s->id)->where('due_date', '2026-11-27')->first();
        $this->assertSame('skipped_open', $run->result);
        $this->assertSame('2026-12-04', $s->fresh()->next_due_date->toDateString(), 'the date still moves on');
    }

    #[Test]
    public function pmc_016_with_skip_if_open_off_a_new_request_is_made_anyway(): void
    {
        $s = $this->schedule(['first_due_date' => '2026-11-20', 'frequency' => 'weekly', 'skip_if_open' => false]);
        $this->runJob();
        $this->runJob('2026-11-26');

        $this->assertCount(2, $this->pmRequests($s));
    }

    #[Test]
    public function a_completed_request_does_not_block_the_next_one(): void
    {
        $s = $this->schedule(['first_due_date' => '2026-11-20', 'frequency' => 'weekly']);
        $this->runJob();
        $this->pmRequests($s)->first()->forceFill(['status' => 'completed'])->save();
        $this->runJob('2026-11-26');

        $this->assertCount(2, $this->pmRequests($s));
    }

    #[Test]
    public function pmc_017_three_missed_dates_create_one_request_for_the_earliest_and_jump_to_the_first_date_after_today(): void
    {
        // weekly from 29 Oct: 29 Oct, 5 Nov, 12 Nov, 19 Nov are all behind or on today (19 Nov)
        $s = $this->schedule(['first_due_date' => '2026-10-29', 'frequency' => 'weekly', 'lead_days' => 0]);

        $this->runJob();

        $made = $this->pmRequests($s);
        $this->assertCount(1, $made);
        $this->assertSame('2026-10-29', $made[0]->pm_due_date->toDateString());
        $this->assertSame('2026-11-26', $s->fresh()->next_due_date->toDateString());
        $this->assertStringContainsString('Created late. 3 earlier dates were missed.', $made[0]->description);
        $this->assertSame('Created late. 3 earlier dates were missed.', PmScheduleRun::withoutGlobalScopes()->first()->note);
    }

    #[Test]
    public function pmc_018_a_run_creates_at_most_the_limit_and_the_rest_wait_for_the_next_run(): void
    {
        PmGenerator::$maxPerRun = 2;
        $cat = $this->makeCategory();
        foreach (range(1, 4) as $i) {
            $this->schedule(['name' => "S{$i}"], [$this->makeAsset($cat)->id], $cat);
        }

        $r = $this->runJob();
        $this->assertSame(2, $r['created']);
        $this->assertSame(2, $r['left_over']);
        $this->assertCount(2, $this->pmRequests());

        $this->runJob();
        $this->assertCount(4, $this->pmRequests());
    }

    #[Test]
    public function pmc_019_a_schedule_whose_end_date_has_passed_creates_nothing_and_ends(): void
    {
        $s = $this->schedule(['name' => 'Past end', 'first_due_date' => '2026-11-26']);
        $s->forceFill(['end_date' => '2026-11-10'])->save();   // the due date is after the end date

        $r = $this->runJob();

        $this->assertSame(1, $r['ended']);
        $this->assertCount(0, $this->pmRequests());
        $this->assertSame('ended', $s->fresh()->status);
        $this->assertSame(0, $this->runJob()['ended'], 'an ended schedule is not looked at again');
    }

    // ───────────── 10.3 batch ─────────────

    #[Test]
    public function pmc_020_a_schedule_with_twelve_assets_makes_one_request_with_twelve_rows(): void
    {
        $cat = $this->makeCategory();
        $ids = collect(range(1, 12))->map(fn () => $this->makeAsset($cat)->id)->all();
        $s   = $this->schedule([], $ids, $cat);

        $this->runJob();

        $made = $this->pmRequests($s);
        $this->assertCount(1, $made);
        $this->assertNull($made[0]->primary_asset_id);
        $this->assertSame(12, ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $made[0]->id)->count());
    }

    #[Test]
    public function pmc_021_an_asset_retired_after_the_schedule_was_made_is_skipped_and_the_others_stay(): void
    {
        $cat = $this->makeCategory();
        $a = $this->makeAsset($cat);
        $b = $this->makeAsset($cat);
        $c = $this->makeAsset($cat);
        $s = $this->schedule([], [$a->id, $b->id, $c->id], $cat);
        $b->forceFill(['status' => 'decommissioned'])->save();

        $this->runJob();

        $rows = ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $this->pmRequests($s)[0]->id)->pluck('asset_id')->all();
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $rows);
    }

    #[Test]
    public function a_schedule_whose_assets_are_all_gone_records_an_error_and_moves_on(): void
    {
        $cat = $this->makeCategory();
        $a = $this->makeAsset($cat);
        $s = $this->schedule([], [$a->id], $cat);
        $a->forceFill(['status' => 'decommissioned'])->save();

        $r = $this->runJob();
        $this->assertSame(1, $r['errors']);
        $this->assertCount(0, $this->pmRequests($s));
        $this->assertSame('error', PmScheduleRun::withoutGlobalScopes()->first()->result);
        $this->assertSame('2026-12-26', $s->fresh()->next_due_date->toDateString());
    }

    // ───────────── 10.4 contract ─────────────

    private function contract(string $number, array $over = []): Contract
    {
        $this->t->plan->forceFill(['features' => array_merge($this->t->plan->features, ['contracts' => true])])->save();
        $c = new Contract();
        $c->forceFill(array_merge([
            'tenant_id' => $this->t->id, 'contract_number' => $number, 'client_id' => $this->client->id, 'contract_type' => 'amc',
            'start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'status' => 'active',
        ], $over))->save();

        return $c;
    }

    #[Test]
    public function pmc_030_a_pinned_contract_is_used_even_when_several_match(): void
    {
        $pin = $this->contract('CT-A');
        $this->contract('CT-B');
        $s = $this->schedule(['contract_id' => $pin->id]);

        $this->runJob();

        $sr = $this->pmRequests($s)[0];
        $this->assertSame($pin->id, $sr->contract_id);
        $this->assertFalse((bool) $sr->contract_choice_pending);
    }

    #[Test]
    public function pmc_031_with_no_pin_and_one_covering_contract_that_one_is_used(): void
    {
        $c = $this->contract('CT-ONLY');
        $s = $this->schedule(['name' => 'Fire pumps']);
        $this->runJob();

        $sr = $this->pmRequests($s)[0];
        $this->assertFalse((bool) $sr->contract_choice_pending);
        $this->assertSame('pending', $sr->status);   // the manual mode: a person approves, and that counts the call
    }

    #[Test]
    public function pmc_032_with_no_pin_and_two_covering_contracts_the_request_waits_for_a_manager(): void
    {
        $this->contract('CT-A');
        $this->contract('CT-B');
        $s = $this->schedule(['name' => 'Two contracts']);

        $r = $this->runJob();

        $sr = $this->pmRequests($s)[0];
        $this->assertSame(1, $r['needs_contract']);
        $this->assertTrue((bool) $sr->contract_choice_pending);
        $this->assertSame('pending', $sr->status);
        $this->assertNull($sr->assigned_tech_user_id);
        $this->assertSame('needs_contract', PmScheduleRun::withoutGlobalScopes()->first()->result);
        $this->assertGreaterThan(0, UserNotification::where('event_key', 'sr.contract_choice_needed')->count());
    }

    #[Test]
    public function pmc_033_with_no_covering_contract_the_request_is_out_of_coverage_and_follows_the_category_mode(): void
    {
        $cat = $this->makeCategory(['approval_mode' => 'always_auto']);
        $s   = $this->schedule(['name' => 'No contract'], null, $cat);

        $this->runJob();

        $sr = $this->pmRequests($s)[0];
        $this->assertSame('out_of_coverage', $sr->coverage_type);
        $this->assertSame('approved', $sr->status);
    }

    // ───────────── 10.5 defaults and technician ─────────────

    #[Test]
    public function pmc_040_a_site_that_always_enforces_the_geofence_keeps_it_enforced(): void
    {
        $this->site->forceFill(['lat' => 25.2, 'lng' => 55.3, 'always_enforce_geofence' => true])->save();
        $s = $this->schedule();
        $this->runJob();

        $this->assertTrue((bool) $this->pmRequests($s)[0]->enforce_geofence);
    }

    #[Test]
    public function pmc_041_a_normal_site_does_not_enforce_and_allows_the_signature_waiver(): void
    {
        $s = $this->schedule();
        $this->runJob();

        $sr = $this->pmRequests($s)[0];
        $this->assertFalse((bool) $sr->enforce_geofence);
        $this->assertTrue((bool) $sr->allow_signature_waiver);
    }

    #[Test]
    public function pmc_042_a_free_default_technician_is_assigned_once_the_request_is_approved(): void
    {
        $cat  = $this->makeCategory(['approval_mode' => 'always_auto']);
        $tech = $this->u['field_technician'];
        $s    = $this->schedule(['default_technician_user_id' => $tech->id], null, $cat);

        $this->runJob();

        $sr = $this->pmRequests($s)[0];
        $this->assertSame('assigned', $sr->status);
        $this->assertSame($tech->id, $sr->assigned_tech_user_id);
    }

    #[Test]
    public function pmc_043_a_default_technician_on_time_off_leaves_the_request_unassigned_and_tells_the_front_desk(): void
    {
        $cat  = $this->makeCategory(['approval_mode' => 'always_auto']);
        $tech = $this->u['field_technician'];
        UserTimeOff::create(['tenant_id' => $this->t->id, 'user_id' => $tech->id, 'starts_at' => '2026-11-25 00:00:00', 'ends_at' => '2026-11-27 23:59:59', 'type' => 'leave']);
        $s = $this->schedule(['default_technician_user_id' => $tech->id], null, $cat);

        $this->runJob();

        $sr = $this->pmRequests($s)[0];
        $this->assertNull($sr->assigned_tech_user_id);
        $this->assertSame('approved', $sr->status);
        $n = UserNotification::where('event_key', 'pm.generated')->where('user_id', $this->u['front_desk']->id)->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('is off on 26 Nov 2026. Assign someone.', $n->body);
        $this->assertSame(0, UserNotification::where('event_key', 'pm.generated')->where('user_id', $tech->id)->count());
    }

    #[Test]
    public function pmc_044_a_schedule_with_a_template_binds_the_request_to_its_published_version(): void
    {
        $tpl = $this->makeTemplate('PM checklist');
        $cat = $this->makeCategory();   // no category template: the schedule's wins
        $s   = $this->schedule(['form_template_id' => $tpl->id], null, $cat);

        $this->runJob();

        $this->assertSame($tpl->publishedVersion()->id, $this->pmRequests($s)[0]->form_template_version_id);
    }

    #[Test]
    public function pmc_045_and_046_the_category_approval_mode_decides_the_status(): void
    {
        $auto   = $this->schedule(['name' => 'Auto'], null, $this->makeCategory(['approval_mode' => 'always_auto']));
        $manual = $this->schedule(['name' => 'Manual'], null, $this->makeCategory(['approval_mode' => 'manual']));

        $this->runJob();

        $this->assertSame('approved', $this->pmRequests($auto)[0]->status);
        $this->assertSame('pending', $this->pmRequests($manual)[0]->status);
    }

    // ───────────── 10.7 events ─────────────

    #[Test]
    public function pmc_060_and_061_pm_generated_goes_to_the_technician_and_the_operations_manager_or_the_manager_alone(): void
    {
        $cat  = $this->makeCategory(['approval_mode' => 'always_auto']);
        $tech = $this->u['field_technician'];
        $this->schedule(['name' => 'With tech', 'default_technician_user_id' => $tech->id], null, $cat);
        $this->schedule(['name' => 'Without tech'], null, $this->makeCategory(['approval_mode' => 'manual']));

        $this->runJob();

        $to = UserNotification::where('event_key', 'pm.generated')->get();
        $this->assertTrue($to->where('user_id', $tech->id)->isNotEmpty());
        $ops = $to->where('user_id', $this->u['operations_manager']->id);
        $this->assertCount(2, $ops);                                   // one for each request
        $this->assertCount(1, $to->where('user_id', $tech->id));       // only for the job it got
    }

    #[Test]
    public function pmc_062_and_063_pm_overdue_is_sent_once_after_two_days_and_once_more_after_seven(): void
    {
        $s = $this->schedule(['first_due_date' => '2026-11-17', 'lead_days' => 0, 'frequency' => 'yearly']);
        $this->runJob('2026-11-17');                    // the request is made on its due date
        $sr = $this->pmRequests($s)[0];

        $this->runJob('2026-11-18');                    // 1 day past due: nothing yet
        $this->assertSame(0, UserNotification::where('event_key', 'pm.overdue')->count());

        $this->runJob('2026-11-19');                    // 2 days past due: the first message
        $this->assertSame(1, UserNotification::where('event_key', 'pm.overdue')->where('user_id', $this->u['operations_manager']->id)->count());

        $this->runJob('2026-11-20');                    // not again the next day
        $this->assertSame(1, UserNotification::where('event_key', 'pm.overdue')->count());

        $this->runJob('2026-11-24');                    // 7 days past due: the second and last
        $this->assertSame(2, UserNotification::where('event_key', 'pm.overdue')->count());
        $this->runJob('2026-11-30');
        $this->assertSame(2, UserNotification::where('event_key', 'pm.overdue')->count());
        $this->assertNotNull($sr->fresh()->pm_overdue_second_at);
    }

    #[Test]
    public function pmc_064_finished_work_is_never_reported_overdue(): void
    {
        $s = $this->schedule(['first_due_date' => '2026-11-17', 'lead_days' => 0, 'frequency' => 'yearly']);
        $this->runJob('2026-11-17');
        $this->pmRequests($s)[0]->forceFill(['status' => 'completed'])->save();

        $this->runJob('2026-11-25');
        $this->assertSame(0, UserNotification::where('event_key', 'pm.overdue')->count());
    }

    // ───────────── 10.8 two companies ─────────────

    #[Test]
    public function pmc_075_each_company_runs_at_its_own_local_06_00_with_its_own_data(): void
    {
        $dubai = $this->schedule(['name' => 'Dubai job']);

        // a second company in New York, with its own site, asset and schedule
        $ny = $this->makeTenant(slug: 'nyc', tz: 'America/New_York');
        $client = $this->makeClient($ny, 'NY Client');
        $site = ProjectSite::withoutGlobalScopes()->create(['tenant_id' => $ny->id, 'client_id' => $client->id, 'name' => 'NY site']);
        $cat = \App\Models\ServiceCategory::withoutGlobalScopes()->create(['tenant_id' => $ny->id, 'code' => 'NYC-1', 'name' => 'NY cat', 'approval_mode' => 'manual', 'is_active' => true]);
        $asset = \App\Models\Asset::withoutGlobalScopes()->create(['tenant_id' => $ny->id, 'client_id' => $client->id, 'project_site_id' => $site->id, 'service_category_id' => $cat->id, 'name' => 'NY pump', 'status' => 'active']);
        $nySchedule = app(\App\Services\Maintenance\PmSchedules::class)->create($ny, $this->makeUser($ny, 'operations_manager'), [
            'name' => 'NY job', 'project_site_id' => $site->id, 'service_category_id' => $cat->id, 'frequency' => 'monthly',
            'first_due_date' => '2026-11-26', 'lead_days' => 7, 'priority' => 'medium',
        ], [$asset->id]);

        // 02:00 UTC is 06:00 in Dubai but 21:00 the evening before in New York
        Carbon::setTestNow(Carbon::parse('2026-11-19 02:00:00', 'UTC'));
        Artisan::call('pm:daily');

        $this->assertCount(1, $this->pmRequests($dubai));
        $this->assertSame(0, ServiceRequest::withoutGlobalScopes()->where('tenant_id', $ny->id)->count());

        // 11:00 UTC is 06:00 in New York
        Carbon::setTestNow(Carbon::parse('2026-11-19 11:00:00', 'UTC'));
        Artisan::call('pm:daily');
        $this->assertSame(1, ServiceRequest::withoutGlobalScopes()->where('tenant_id', $ny->id)->count());
        $this->assertSame(0, ServiceRequest::withoutGlobalScopes()->where('tenant_id', $ny->id)->where('client_id', $this->client->id)->count());
        $this->assertCount(1, $this->pmRequests($dubai), 'Dubai ran at its own hour only');
    }

    #[Test]
    public function the_job_puts_the_company_context_back_when_it_finishes(): void
    {
        $this->schedule();
        $this->assertNull(Tenant::current());
        $this->runJob();
        $this->assertNull(Tenant::current());
    }

    #[Test]
    public function a_starter_company_is_not_run_by_the_command(): void
    {
        $starter = $this->makeTenant($this->starterPlan(), slug: 'sta3');
        Carbon::setTestNow(Carbon::parse('2026-11-19 02:00:00', 'UTC'));
        $this->assertSame(0, Artisan::call('pm:daily', ['--tenant' => $starter->id]));
        $this->assertSame(0, PmSchedule::withoutGlobalScopes()->where('tenant_id', $starter->id)->count());
    }
}
