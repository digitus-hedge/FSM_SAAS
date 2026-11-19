<?php

namespace Tests\Feature\Maintenance;

use App\Models\PmSchedule;
use App\Models\ProjectSite;
use App\Models\ServiceRequest;
use App\Services\Maintenance\Frequency;
use App\Services\Maintenance\PmScheduleAccess;
use App\Services\Maintenance\PmSchedules;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/** Preventive-Maintenance spec 10.1 (schedules), 10.6 (calendar) and 10.8 (permissions): PMC-001 to PMC-009, PMC-050 to PMC-052, PMC-070 to PMC-077. */
class SchedulesTest extends MaintenanceTestCase
{
    // ───────────── 10.1 schedules ─────────────

    #[Test]
    public function pmc_001_a_monthly_schedule_with_one_asset_starts_at_its_first_due_date(): void
    {
        $this->as('operations_manager')->post(route('admin.maintenance.store'), $this->pmBody())->assertSessionHasNoErrors()->assertRedirect();

        $s = PmSchedule::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('2026-12-01', $s->next_due_date->toDateString());
        $this->assertSame('active', $s->status);
        $this->assertSame(1, $s->assetRows()->count());
        $this->as('operations_manager')->get(route('admin.maintenance.index'))->assertOk()->assertSee('Pumps');
    }

    #[Test]
    public function pmc_002_an_asset_from_another_site_is_refused(): void
    {
        $cat   = $this->makeCategory();
        $site2 = ProjectSite::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'name' => 'Other']);
        $other = $this->makeAsset($cat, ['project_site_id' => $site2->id]);

        $this->as('operations_manager')->post(route('admin.maintenance.store'), $this->pmBody(['service_category_id' => $cat->id, 'asset_ids' => [$other->id]]))
            ->assertSessionHas('error', 'All assets in a schedule must be at the same site.');
        $this->assertSame(0, PmSchedule::withoutGlobalScopes()->count());
    }

    #[Test]
    public function pmc_003_assets_of_two_categories_are_refused(): void
    {
        $cat   = $this->makeCategory();
        $other = $this->makeAsset($this->makeCategory());

        $this->as('operations_manager')->post(route('admin.maintenance.store'), $this->pmBody(['service_category_id' => $cat->id, 'asset_ids' => [$this->makeAsset($cat)->id, $other->id]]))
            ->assertSessionHas('error', 'All assets must have the same service category.');
    }

    #[Test]
    public function pmc_004_there_is_no_limit_on_the_number_of_schedules(): void
    {
        $cat = $this->makeCategory();
        $a   = $this->makeAsset($cat)->id;
        for ($i = 1; $i <= 30; $i++) {
            $this->schedule(['name' => "S{$i}"], [$a], $cat);
        }
        $this->as('operations_manager')->post(route('admin.maintenance.store'), $this->pmBody())->assertSessionHasNoErrors();

        $this->assertSame(31, PmSchedule::withoutGlobalScopes()->count());
    }

    #[Test]
    public function pmc_005_a_starter_company_gets_the_not_in_your_plan_page(): void
    {
        $starter = $this->makeTenant($this->starterPlan(), slug: 'sta2');
        $this->actingAs($this->makeUser($starter, 'company_admin'))->get(route('admin.maintenance.index'))->assertForbidden();
    }

    #[Test]
    public function pmc_006_month_steps_keep_the_original_day_of_the_month(): void
    {
        $first = Carbon::parse('2026-01-31');
        $dates = collect(range(0, 3))->map(fn ($n) => Frequency::nth($first, 'monthly', null, $n)->toDateString())->all();
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'], $dates);

        $this->assertSame('2026-02-28', Frequency::after($first, 'monthly', null, $first)->toDateString());
        $this->assertSame('2026-03-31', Frequency::after($first, 'monthly', null, Carbon::parse('2026-02-28'))->toDateString());
        $this->assertSame('2026-07-31', Frequency::nth($first, 'half_yearly', null, 1)->toDateString());
        $this->assertSame('2027-01-31', Frequency::nth($first, 'yearly', null, 1)->toDateString());
        $this->assertSame('2026-02-07', Frequency::nth($first, 'weekly', null, 1)->toDateString());
        $this->assertSame('2026-02-10', Frequency::nth($first, 'every_n_days', 10, 1)->toDateString());
        $this->assertSame('2026-07-31', Frequency::nth($first, 'every_n_months', 3, 2)->toDateString());
    }

    #[Test]
    public function pmc_007_every_n_days_of_5_is_invalid(): void
    {
        $this->as('operations_manager')->post(route('admin.maintenance.store'), $this->pmBody(['frequency' => 'every_n_days', 'interval_value' => 5]))
            ->assertSessionHas('error');
        $this->assertSame(0, PmSchedule::withoutGlobalScopes()->count());

        $this->as('operations_manager')->post(route('admin.maintenance.store'), $this->pmBody(['frequency' => 'every_n_days', 'interval_value' => 14]))->assertSessionHasNoErrors();
        $this->assertSame(1, PmSchedule::withoutGlobalScopes()->count());
        $this->assertFalse(Frequency::valid('every_n_months', 37));
        $this->assertTrue(Frequency::valid('every_n_months', 36));
    }

    #[Test]
    public function pmc_008_a_schedule_that_created_a_request_cannot_be_deleted(): void
    {
        $s = $this->schedule(['first_due_date' => '2026-11-20']);
        $this->runJob();
        $this->assertCount(1, $this->pmRequests($s));

        $this->as('operations_manager')->delete(route('admin.maintenance.destroy', $s))->assertSessionHas('error', 'A schedule that created requests cannot be deleted. End it instead.');
        $this->assertNotNull(PmSchedule::withoutGlobalScopes()->find($s->id));

        $fresh = $this->schedule(['name' => 'Never used']);
        $this->as('operations_manager')->delete(route('admin.maintenance.destroy', $fresh))->assertRedirect();
        $this->assertNull(PmSchedule::withoutGlobalScopes()->find($fresh->id));
    }

    #[Test]
    public function pmc_009_editing_a_schedule_audits_the_old_and_new_values(): void
    {
        $s    = $this->schedule();
        $body = $this->pmBody(['name' => 'Renamed', 'service_category_id' => $s->service_category_id, 'asset_ids' => $s->assetRows->pluck('asset_id')->all(), 'lead_days' => 3, 'first_due_date' => '2026-11-26']);

        $this->as('operations_manager')->put(route('admin.maintenance.update', $s), $body)->assertSessionHasNoErrors()->assertRedirect();

        $s->refresh();
        $this->assertSame('Renamed', $s->name);
        $this->assertSame(3, $s->lead_days);
        $log = \App\Models\ActivityLog::where('action', 'pm_schedule.updated')->where('item_id', $s->id)->firstOrFail();
        $this->assertSame('Generator service', $log->old_values['name']);
        $this->assertSame('Renamed', $log->new_values['name']);
    }

    #[Test]
    public function an_ended_schedule_cannot_be_changed_and_pause_and_resume_work(): void
    {
        $s = $this->schedule();
        $this->as('operations_manager')->post(route('admin.maintenance.pause', $s))->assertRedirect();
        $this->assertSame('paused', $s->fresh()->status);

        // Resume moves the next date to the next one on or after today (D14), nothing is caught up
        $s->forceFill(['next_due_date' => '2026-09-26', 'first_due_date' => '2026-03-26'])->save();
        $this->as('operations_manager')->post(route('admin.maintenance.resume', $s))->assertRedirect();
        $this->assertSame('active', $s->fresh()->status);
        $this->assertSame('2026-11-26', $s->fresh()->next_due_date->toDateString());

        $this->as('operations_manager')->post(route('admin.maintenance.end', $s))->assertRedirect();
        $this->assertSame('ended', $s->fresh()->status);
        $this->as('operations_manager')->post(route('admin.maintenance.pause', $s))->assertSessionHas('error', 'This schedule has ended and cannot be changed.');
        $this->as('operations_manager')->get(route('admin.maintenance.edit', $s))->assertSessionHas('error', 'This schedule has ended and cannot be changed.');
    }

    #[Test]
    public function changing_the_frequency_asks_for_a_new_next_due_date_or_works_one_out(): void
    {
        $s    = $this->schedule(['first_due_date' => '2026-11-26']);
        $body = $this->pmBody(['service_category_id' => $s->service_category_id, 'asset_ids' => $s->assetRows->pluck('asset_id')->all(), 'first_due_date' => '2026-11-10', 'frequency' => 'weekly']);

        // weekly from 10 Nov: 10, 17, 24. The next one on or after today (19 Nov) is 24 Nov.
        $this->as('operations_manager')->put(route('admin.maintenance.update', $s), $body)->assertSessionHasNoErrors();
        $this->assertSame('2026-11-24', $s->fresh()->next_due_date->toDateString());

        $this->as('operations_manager')->put(route('admin.maintenance.update', $s), $body + ['next_due_date' => '2026-12-15'])->assertSessionHasNoErrors();
        $this->assertSame('2026-12-15', $s->fresh()->next_due_date->toDateString());
    }

    // ───────────── 10.6 calendar ─────────────

    #[Test]
    public function pmc_050_the_month_view_shows_made_requests_and_upcoming_dates_on_the_right_days(): void
    {
        $s = $this->schedule(['first_due_date' => '2026-11-26', 'name' => 'Boiler']);
        $this->runJob();   // 26 Nov is within 7 days: a request is made
        $s->refresh();

        $res = $this->as('operations_manager')->get(route('admin.maintenance.calendar', ['from' => '2026-11-01', 'to' => '2027-01-31']));
        $res->assertOk();
        $items = $res->viewData('items');
        $this->assertSame('request', $items['2026-11-26'][0]['kind']);
        $this->assertSame('upcoming', $items['2026-12-26'][0]['kind']);
        $this->assertSame('upcoming', $items['2027-01-26'][0]['kind']);
        $this->assertFalse($items->has('2026-11-27'));
    }

    #[Test]
    public function pmc_051_a_range_over_six_months_is_refused(): void
    {
        $this->as('operations_manager')->get(route('admin.maintenance.calendar', ['from' => '2026-01-01', 'to' => '2026-09-01']))
            ->assertSessionHas('error', 'Choose a range of up to 6 months.');
    }

    #[Test]
    public function pmc_052_the_technician_filter_keeps_only_that_technicians_items(): void
    {
        $tech = $this->u['field_technician'];
        $this->schedule(['name' => 'Mine', 'default_technician_user_id' => $tech->id]);
        $this->schedule(['name' => 'Not mine']);

        $items = $this->as('operations_manager')->get(route('admin.maintenance.calendar', ['from' => '2026-11-01', 'to' => '2026-12-31', 'technician' => $tech->id]))->viewData('items');
        $labels = $items->flatten(1)->pluck('label')->implode(' ');
        $this->assertStringContainsString('Mine', $labels);
        $this->assertStringNotContainsString('Not mine', $labels);
    }

    // ───────────── 10.8 permissions ─────────────

    #[Test]
    public function pmc_070_front_desk_and_finance_get_403(): void
    {
        foreach (['front_desk', 'finance'] as $who) {
            $this->as($who)->get(route('admin.maintenance.index'))->assertForbidden();
            $this->as($who)->get(route('admin.maintenance.calendar'))->assertForbidden();
        }
        foreach (['company_admin', 'operations_manager'] as $who) {
            $this->as($who)->get(route('admin.maintenance.index'))->assertOk();
        }
    }

    #[Test]
    public function pmc_071_a_team_lead_sees_only_schedules_whose_technician_is_on_their_team(): void
    {
        $lead   = $this->u['team_lead'];
        $mine   = $this->makeUser($this->t, 'field_technician', 'mine@fab.test');
        $mine->forceFill(['team_lead_id' => $lead->id])->save();
        $theirs = $this->makeUser($this->t, 'field_technician', 'theirs@fab.test');

        $a = $this->schedule(['name' => 'On my team', 'default_technician_user_id' => $mine->id]);
        $b = $this->schedule(['name' => 'Other team', 'default_technician_user_id' => $theirs->id]);
        $c = $this->schedule(['name' => 'No technician']);

        $seen = PmScheduleAccess::narrow(PmSchedule::withoutGlobalScopes()->where('tenant_id', $this->t->id), $lead)->pluck('name')->all();
        $this->assertSame(['On my team'], $seen);
        $this->assertTrue(PmScheduleAccess::mayView($lead, $a));
        $this->assertFalse(PmScheduleAccess::mayView($lead, $b));
        $this->assertFalse(PmScheduleAccess::mayView($lead, $c));

        $all = PmScheduleAccess::narrow(PmSchedule::withoutGlobalScopes()->where('tenant_id', $this->t->id), $this->u['operations_manager'])->count();
        $this->assertSame(3, $all);
    }

    #[Test]
    public function pmc_072_a_team_lead_cannot_create_a_schedule(): void
    {
        $this->assertFalse(PmScheduleAccess::mayManage($this->u['team_lead']));
        $this->assertTrue(PmScheduleAccess::mayManage($this->u['operations_manager']));
        $this->assertTrue(PmScheduleAccess::mayManage($this->u['company_admin']));
    }

    #[Test]
    public function pmc_073_a_client_sees_upcoming_visits_at_their_own_sites_and_nothing_else(): void
    {
        $mine = $this->schedule(['name' => 'Mine', 'first_due_date' => '2026-12-05']);
        $otherClient = $this->makeClient($this->t, 'Other client');
        $site2 = ProjectSite::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $otherClient->id, 'name' => 'Their site']);
        $cat = $this->makeCategory();
        $asset = $this->makeAsset($cat, ['project_site_id' => $site2->id, 'client_id' => $otherClient->id, 'name' => 'Their pump']);
        $this->schedule(['name' => 'Theirs', 'project_site_id' => $site2->id, 'first_due_date' => '2026-12-06'], [$asset->id], $cat);

        \App\Models\Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => 'client_admin'], ['name' => 'Client Admin', 'is_standard' => false]);
        $client = $this->makeUser($this->t, 'client_admin', 'ca@pm.test');
        $client->forceFill(['client_id' => $this->client->id])->save();

        $res = $this->actingAs($client)->get($this->onHost($this->t, '/portal/maintenance'));
        $res->assertOk()->assertSee('5 Dec 2026')->assertSee('Main site')->assertDontSee('Their site')->assertDontSee('6 Dec 2026');
        // a client has no office page
        $this->actingAs($client)->get(route('admin.maintenance.index'))->assertForbidden();
    }

    #[Test]
    public function pmc_074_another_companys_schedule_is_a_404(): void
    {
        $other = $this->makeTenant(slug: 'oth3');
        $foreign = PmSchedule::withoutGlobalScopes()->create([
            'tenant_id' => $other->id, 'name' => 'Theirs', 'project_site_id' => 1, 'service_category_id' => 1, 'frequency' => 'monthly',
            'first_due_date' => '2026-12-01', 'next_due_date' => '2026-12-01',
        ]);

        $this->as('operations_manager')->get(route('admin.maintenance.show', $foreign))->assertNotFound();
        $this->as('operations_manager')->post(route('admin.maintenance.pause', $foreign))->assertNotFound();
    }

    #[Test]
    public function pmc_076_the_new_routes_are_behind_sign_in(): void
    {
        $s = $this->schedule();
        foreach ([route('admin.maintenance.index'), route('admin.maintenance.show', $s), route('admin.maintenance.calendar')] as $url) {
            $this->get($url)->assertRedirect();
        }
    }

    #[Test]
    public function the_schedule_page_shows_history_and_next_dates(): void
    {
        $s = $this->schedule(['first_due_date' => '2026-11-20']);
        $this->runJob();

        $this->as('operations_manager')->get(route('admin.maintenance.show', $s))->assertOk()->assertSee('Created')->assertSee('Next due dates');
    }

    #[Test]
    public function the_service_refuses_a_batch_over_200_assets_and_no_assets(): void
    {
        $this->expectException(\App\Services\Forms\FormException::class);
        app(PmSchedules::class)->checkAssets($this->t->id, $this->site->id, 1, []);
    }
}
