<?php

namespace Tests\Feature\Dispatch;

use App\Models\DispatchSetting;
use App\Models\ServiceRequestPunch;
use App\Services\Dispatch\TechnicianSuggester;

/**
 * DSP-01 to DSP-04, DSP-06: the ranking, its reasons, proximity with its age, time off, workload and the colour limits.
 * With the default weights (35, 25, 20, 10, 10) a technician with the skill, free, with no jobs and at the site, but no finished jobs, scores
 * 35 + 25 + 20 + 10 + 5 (first-time fix counts half without history) = 95. One with nothing but being free and idle scores 25 + 20 + 5 = 50.
 */
class SuggestionsTest extends DispatchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->site->forceFill(['lat' => 25.2000000, 'lng' => 55.2700000])->save();
    }

    protected function request(array $over = [])
    {
        $cat = $this->makeCategory(['name' => 'HVAC']);
        $sr = $this->raise($cat);
        $sr->forceFill($over)->save();

        return [$sr->fresh(), $cat];
    }

    /* ---------- ranking with known inputs (DSP-01, DSP-03) ---------- */

    public function test_a_skilled_free_technician_at_the_site_scores_95_and_ranks_first(): void
    {
        [$sr, $cat] = $this->request();
        $best = $this->tech('Best', ['home_base_lat' => 25.2, 'home_base_lng' => 55.27]);
        $this->skill($best, $cat->id);
        $plain = $this->tech('Plain');

        $list = $this->suggestions($sr);

        $this->assertSame($best->id, $list[0]['id']);
        $this->assertSame(95, $this->row($list, $best)['total']);
        $this->assertSame(50, $this->row($list, $plain)['total']);
        $this->assertSame(['skill' => 100, 'availability' => 100, 'workload' => 100, 'proximity' => 100, 'first_fix' => 50], $this->row($list, $best)['parts']);
    }

    public function test_every_score_comes_with_its_reasons(): void
    {
        [$sr, $cat] = $this->request();
        $best = $this->tech('Best', ['home_base_lat' => 25.2, 'home_base_lng' => 55.27]);
        $this->skill($best, $cat->id);
        $plainUser = $this->tech('Plain');
        $list = $this->suggestions($sr);

        $reasons = implode(' | ', $this->row($list, $best)['reasons']);

        foreach (['Has the HVAC skill', 'Free at', '0 job(s) in the next 7 days, 0% of capacity', '0 km from the site (home base)', 'Not enough finished jobs yet'] as $text) {
            $this->assertStringContainsString($text, $reasons);
        }
        $plain = implode(' | ', $this->row($list, $plainUser)['reasons']);
        $this->assertStringContainsString('No HVAC skill listed', $plain);
        $this->assertStringContainsString('No last punch or home base on file', $plain);
    }

    public function test_the_weights_are_configurable_and_change_the_ranking(): void
    {
        [$sr, $cat] = $this->request();
        $skilled = $this->tech('Skilled');
        $this->skill($skilled, $cat->id);
        $near = $this->tech('Near', ['home_base_lat' => 25.2, 'home_base_lng' => 55.27]);

        $default = $this->suggestions($sr);
        $this->assertSame($skilled->id, $default[0]['id']);                                                  // skill 35 beats proximity 10

        DispatchSetting::forTenant($this->t->id)->update(['w_skill' => 0, 'w_proximity' => 100]);
        $this->assertSame($near->id, $this->suggestions($sr)[0]['id']);
    }

    public function test_a_first_time_fix_history_counts_from_three_finished_jobs(): void
    {
        [$sr] = $this->request();
        $good = $this->tech('Good');
        foreach (range(1, 3) as $i) {
            $done = $this->assigned($good);
            $done->forceFill(['status' => 'completed', 'rework_count' => 0])->save();
        }

        $row = $this->row($this->suggestions($sr), $good);

        $this->assertSame(100, $row['parts']['first_fix']);
        $this->assertStringContainsString('100% fixed first time (3 jobs)', implode(' | ', $row['reasons']));
    }

    public function test_the_ranking_only_suggests_and_assigns_nothing(): void
    {
        [$sr] = $this->request();
        $this->tech('A');

        $this->suggestions($sr);

        $this->assertNull($sr->fresh()->assigned_tech_user_id);
    }

    /* ---------- proximity and its age (DSP-04) ---------- */

    public function test_proximity_uses_the_last_punch_and_shows_its_age(): void
    {
        [$sr] = $this->request();
        $t = $this->tech('Punched', ['home_base_lat' => 24.0, 'home_base_lng' => 54.0]);            // home base is far away
        $job = $this->assigned($t);
        ServiceRequestPunch::create(['tenant_id' => $this->t->id, 'service_request_id' => $job->id, 'technician_user_id' => $t->id, 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'punch_type' => 'punch_in', 'recorded_at_device' => now()->subHours(3), 'synced_to_server_at' => now(), 'is_offline_sync' => false, 'clock_drift_seconds' => 0,
            'gps_lat' => 25.2, 'gps_lng' => 55.27, 'sync_status' => 'synced']);

        $row = $this->row($this->suggestions($sr), $t);

        $this->assertSame(100, $row['parts']['proximity']);
        $this->assertStringContainsString('last punch 3 hours ago', implode(' | ', $row['reasons']));
    }

    public function test_a_punch_older_than_30_days_is_ignored_and_the_home_base_is_used(): void
    {
        [$sr] = $this->request();
        $t = $this->tech('Old punch', ['home_base_lat' => 25.2, 'home_base_lng' => 55.27]);
        $job = $this->assigned($t);
        ServiceRequestPunch::create(['tenant_id' => $this->t->id, 'service_request_id' => $job->id, 'technician_user_id' => $t->id, 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'punch_type' => 'punch_in', 'recorded_at_device' => now()->subDays(40), 'synced_to_server_at' => now(), 'is_offline_sync' => false, 'clock_drift_seconds' => 0,
            'gps_lat' => 10.0, 'gps_lng' => 10.0, 'sync_status' => 'synced']);

        $row = $this->row($this->suggestions($sr), $t);

        $this->assertSame(100, $row['parts']['proximity']);
        $this->assertStringContainsString('(home base)', implode(' | ', $row['reasons']));
    }

    public function test_proximity_falls_to_zero_at_50_km_and_a_site_without_coordinates_is_not_scored(): void
    {
        [$sr] = $this->request();
        $far = $this->tech('Far', ['home_base_lat' => 25.2 + 0.45, 'home_base_lng' => 55.27]);      // about 50 km north

        $this->assertLessThanOrEqual(2, $this->row($this->suggestions($sr), $far)['parts']['proximity']);

        $this->site->forceFill(['lat' => null, 'lng' => null])->save();
        $row = $this->row($this->suggestions($sr), $far);
        $this->assertSame(0, $row['parts']['proximity']);
        $this->assertStringContainsString('Site has no saved coordinates', implode(' | ', $row['reasons']));
    }

    /* ---------- availability and time off (DSP-05) ---------- */

    public function test_time_off_over_the_arrival_makes_a_technician_unavailable_with_the_reason(): void
    {
        [$sr] = $this->request();
        $t = $this->tech('On leave');
        $this->timeOff($t, '2026-11-20 00:00:00', '2026-11-21 00:00:00');

        $row = $this->row($this->suggestions($sr, '2026-11-20 06:00:00'), $t);

        $this->assertSame(0, $row['parts']['availability']);
        $this->assertStringContainsString('On leave until', implode(' | ', $row['reasons']));
        $this->assertSame(100, $this->row($this->suggestions($sr, '2026-11-22 06:00:00'), $t)['parts']['availability']);      // free again afterwards
    }

    public function test_a_job_near_the_arrival_makes_a_technician_busy(): void
    {
        [$sr] = $this->request();
        $t = $this->tech('Busy');
        $this->assigned($t, '2026-11-20 06:30:00');

        $row = $this->row($this->suggestions($sr, '2026-11-20 06:00:00'), $t);
        $this->assertSame(0, $row['parts']['availability']);
        $this->assertStringContainsString('Already has a job at', implode(' | ', $row['reasons']));

        $this->assertSame(100, $this->row($this->suggestions($sr, '2026-11-20 09:00:00'), $t)['parts']['availability']);       // more than one job length away
    }

    public function test_the_time_off_page_records_and_removes_leave_for_a_full_assigner_only(): void
    {
        $t = $this->tech('Leave person');

        $this->as('operations_manager')->post(route('dispatch.time-off.store'), ['user_id' => $t->id, 'type' => 'leave', 'starts_at' => '2026-11-25 00:00', 'ends_at' => '2026-11-27 00:00'])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\UserTimeOff::where('user_id', $t->id)->count());

        $this->as('operations_manager')->post(route('dispatch.time-off.store'), ['user_id' => $t->id, 'type' => 'leave', 'starts_at' => '2026-11-27 00:00', 'ends_at' => '2026-11-25 00:00'])->assertSessionHasErrors('ends_at');
        $this->as('operations_manager')->post(route('dispatch.time-off.store'), ['user_id' => $this->u['finance']->id, 'type' => 'leave', 'starts_at' => '2026-11-25 00:00', 'ends_at' => '2026-11-27 00:00'])->assertSessionHasErrors('user_id');
        $this->as('front_desk')->post(route('dispatch.time-off.store'), ['user_id' => $t->id, 'type' => 'leave', 'starts_at' => '2026-12-01 00:00', 'ends_at' => '2026-12-02 00:00'])->assertForbidden();

        $row = \App\Models\UserTimeOff::where('user_id', $t->id)->first();
        $this->as('operations_manager')->delete(route('dispatch.time-off.destroy', $row->id))->assertSessionHasNoErrors();
        $this->assertSame(0, \App\Models\UserTimeOff::where('user_id', $t->id)->count());
    }

    /* ---------- workload and the colours (DSP-06, DSP-07) ---------- */

    public function test_the_default_colour_limits_are_60_and_90(): void
    {
        $this->assertSame('green', TechnicianSuggester::band(0));
        $this->assertSame('green', TechnicianSuggester::band(59.9));
        $this->assertSame('amber', TechnicianSuggester::band(60));
        $this->assertSame('amber', TechnicianSuggester::band(90));
        $this->assertSame('red', TechnicianSuggester::band(90.1));
    }

    public function test_a_new_company_gets_the_default_limits_in_its_settings(): void
    {
        $s = DispatchSetting::forTenant($this->t->id);

        $this->assertSame(60, (int) $s->workload_green_below);
        $this->assertSame(90, (int) $s->workload_amber_up_to);
    }

    public function test_the_limits_come_from_the_companys_settings(): void
    {
        $s = DispatchSetting::forTenant($this->t->id);
        $s->update(['workload_green_below' => 40, 'workload_amber_up_to' => 80]);

        $this->assertSame('green', TechnicianSuggester::band(39.9, $s->fresh()));
        $this->assertSame('amber', TechnicianSuggester::band(40, $s->fresh()));
        $this->assertSame('amber', TechnicianSuggester::band(80, $s->fresh()));
        $this->assertSame('red', TechnicianSuggester::band(80.1, $s->fresh()));
    }

    /** One technician with a 60 minute day (capacity 420 a week) and $n jobs of 120 minutes in the next days. */
    protected function loaded(int $n)
    {
        $t = $this->tech('Loaded', ['daily_capacity_minutes' => 60]);
        $first = $this->assigned($t, '2026-11-20 05:00:00');
        for ($i = 1; $i < $n; $i++) {
            $copy = $first->replicate();
            $copy->forceFill(['sr_number' => 'COPY-' . $i . '-' . $n, 'scheduled_eta_at' => \Carbon\Carbon::parse('2026-11-20 05:00:00', 'UTC')->addHours(3 * $i)])->save();
        }

        return $t;
    }

    public function test_the_workload_page_colours_each_technician_by_the_limits(): void
    {
        $this->loaded(3);                                                    // 360 of 420 minutes = 85.7 percent

        $html = $this->as('operations_manager')->get($this->onHost($this->t, '/dispatch/workload'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/dsp-pill amber">85\.7%/', $html);
        $this->assertStringContainsString('Green below 60 percent, amber 60 to 90, red above 90.', $html);
    }

    public function test_changing_the_limits_changes_the_colour_and_the_key(): void
    {
        $this->loaded(3);
        DispatchSetting::forTenant($this->t->id)->update(['workload_green_below' => 40, 'workload_amber_up_to' => 80]);

        $html = $this->as('operations_manager')->get($this->onHost($this->t, '/dispatch/workload'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/dsp-pill red">85\.7%/', $html);
        $this->assertStringContainsString('Green below 40 percent, amber 40 to 80, red above 80.', $html);
    }

    public function test_the_suggestions_use_the_same_limits(): void
    {
        $t = $this->loaded(3);
        [$sr] = $this->request();
        $this->assertSame('amber', $this->row($this->suggestions($sr), $t)['workload_band']);

        DispatchSetting::forTenant($this->t->id)->update(['workload_green_below' => 40, 'workload_amber_up_to' => 80]);
        $this->assertSame('red', $this->row($this->suggestions($sr), $t)['workload_band']);
    }

    public function test_the_workload_counts_scheduled_minutes_over_the_next_seven_days_only(): void
    {
        $t = $this->tech('Week', ['daily_capacity_minutes' => 480]);
        $this->assigned($t, '2026-11-21 05:00:00');                           // inside the week
        $this->assigned($t, '2026-12-05 05:00:00');                           // beyond it

        $load = app(TechnicianSuggester::class)->weekLoad($this->t->id, collect([$t->id]), DispatchSetting::forTenant($this->t->id));

        $this->assertSame(1, $load[$t->id]['jobs']);
        $this->assertSame(120, $load[$t->id]['minutes']);
    }

    /* ---------- the settings page (DSP-02, DSP-06) ---------- */

    protected function saveSettings(array $over = [], string $who = 'company_admin')
    {
        return $this->as($who)->put(route('dispatch.settings.update'), array_merge([
            'w_skill' => 35, 'w_availability' => 25, 'w_workload' => 20, 'w_proximity' => 10, 'w_first_fix' => 10, 'job_minutes' => 120,
            'workload_green_below' => 60, 'workload_amber_up_to' => 90,
        ], $over));
    }

    public function test_the_settings_page_shows_the_limits_with_their_defaults(): void
    {
        $html = $this->as('company_admin')->get($this->onHost($this->t, '/dispatch/settings'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="workload_green_below" value="60"/', $html);
        $this->assertMatchesRegularExpression('/name="workload_amber_up_to" value="90"/', $html);
    }

    public function test_the_company_admin_saves_the_limits_and_the_change_is_audited(): void
    {
        $this->saveSettings(['workload_green_below' => 50, 'workload_amber_up_to' => 85])->assertSessionHasNoErrors();

        $s = DispatchSetting::forTenant($this->t->id)->fresh();
        $this->assertSame(50, (int) $s->workload_green_below);
        $this->assertSame(85, (int) $s->workload_amber_up_to);
        $this->assertSame(1, $this->audits('dispatch.settings_updated'));
    }

    /** @dataProvider badLimits */
    public function test_bad_limits_are_refused(array $over, string $field): void
    {
        $this->saveSettings($over)->assertSessionHasErrors($field);

        $this->assertSame(60, (int) DispatchSetting::forTenant($this->t->id)->workload_green_below);
    }

    public static function badLimits(): array
    {
        return [
            'green equals amber' => [['workload_green_below' => 90, 'workload_amber_up_to' => 90], 'workload_green_below'],
            'green above amber'  => [['workload_green_below' => 95, 'workload_amber_up_to' => 90], 'workload_green_below'],
            'green zero'         => [['workload_green_below' => 0], 'workload_green_below'],
            'green over 100'     => [['workload_green_below' => 101, 'workload_amber_up_to' => 150], 'workload_green_below'],
            'amber over 200'     => [['workload_amber_up_to' => 201], 'workload_amber_up_to'],
            'amber missing'      => [['workload_amber_up_to' => ''], 'workload_amber_up_to'],
            'weights all zero'   => [['w_skill' => 0, 'w_availability' => 0, 'w_workload' => 0, 'w_proximity' => 0, 'w_first_fix' => 0], 'w_skill'],
        ];
    }

    public function test_only_the_company_admin_changes_the_dispatch_settings(): void
    {
        $this->saveSettings([], 'operations_manager')->assertForbidden();
        $this->as('operations_manager')->get($this->onHost($this->t, '/dispatch/settings'))->assertForbidden();
    }

    /* ---------- the suggestion endpoint ---------- */

    public function test_the_endpoint_returns_the_ranking_for_a_manager_and_is_closed_to_others(): void
    {
        [$sr] = $this->request();
        $this->tech('A');

        $json = $this->as('operations_manager')->getJson(route('service-requests.suggestions', $sr))->assertOk()->json();
        $this->assertNotEmpty($json);
        $this->assertArrayHasKey('reasons', $json[0]);

        $this->as('finance')->getJson(route('service-requests.suggestions', $sr))->assertForbidden();
        $this->as('front_desk')->getJson(route('service-requests.suggestions', $sr))->assertForbidden();         // not critical or emergency

        $sr->forceFill(['priority' => 'emergency'])->save();
        $this->as('front_desk')->getJson(route('service-requests.suggestions', $sr))->assertOk();
    }
}
