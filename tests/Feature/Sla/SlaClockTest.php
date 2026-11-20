<?php

namespace Tests\Feature\Sla;

use App\Models\HoldReason;
use App\Models\ServiceRequest;
use Carbon\Carbon;

class SlaClockTest extends SlaTestCase
{
    /* ---------- arithmetic ---------- */

    public function test_without_business_hours_every_minute_counts(): void
    {
        $due = $this->clock()->add(now(), 90, false, $this->t->id);

        $this->assertSame('2026-11-19 11:30:00', $due->utc()->format('Y-m-d H:i:s'));
    }

    public function test_business_hours_count_only_open_minutes_inside_the_day(): void
    {
        // Thursday 14:00 Dubai + 2 hours = 16:00 Dubai = 12:00 UTC
        $due = $this->clock()->add(now(), 120, true, $this->t->id);

        $this->assertSame('2026-11-19 12:00:00', $due->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_time_left_over_carries_into_the_next_open_day(): void
    {
        // Thursday 17:00 Dubai + 2 hours: 1 hour today, 1 hour on Friday from 09:00 = Friday 10:00 Dubai = 06:00 UTC
        $due = $this->clock()->add($this->dubai('2026-11-19 17:00'), 120, true, $this->t->id);

        $this->assertSame('2026-11-20 06:00:00', $due->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_start_after_closing_time_waits_for_the_next_opening(): void
    {
        // Thursday 20:00 Dubai + 1 hour = Friday 10:00 Dubai
        $due = $this->clock()->add($this->dubai('2026-11-19 20:00'), 60, true, $this->t->id);

        $this->assertSame('2026-11-20 06:00:00', $due->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_closed_sunday_is_skipped(): void
    {
        // Saturday 12:00 Dubai + 2 hours: 1 hour until 13:00, Sunday closed, Monday 09:00 + 1 hour = Monday 10:00 Dubai
        $due = $this->clock()->add($this->dubai('2026-11-21 12:00'), 120, true, $this->t->id);

        $this->assertSame('2026-11-23 06:00:00', $due->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_holiday_is_skipped(): void
    {
        $this->holiday('2026-11-20');

        // Thursday 17:00 Dubai + 2 hours: 1 hour today, Friday is a holiday, Saturday 09:00 + 1 hour = Saturday 10:00 Dubai
        $due = $this->clock()->add($this->dubai('2026-11-19 17:00'), 120, true, $this->t->id);

        $this->assertSame('2026-11-21 06:00:00', $due->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_company_hours_rows_replace_the_defaults(): void
    {
        $this->weekHours([4 => null, 5 => ['08:00:00', '12:00:00']]);   // Thursday closed, Friday 08:00 to 12:00 only

        // Thursday 14:00 Dubai + 1 hour: Thursday is closed, Friday opens at 08:00 = Friday 09:00 Dubai
        $due = $this->clock()->add(now(), 60, true, $this->t->id);

        $this->assertSame('2026-11-20 05:00:00', $due->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_company_with_no_open_day_counts_every_minute(): void
    {
        $this->weekHours([]);

        $this->assertSame('2026-11-19 11:00:00', $this->clock()->add(now(), 60, true, $this->t->id)->utc()->format('Y-m-d H:i:s'));
    }

    public function test_between_and_subtract_are_the_inverse_of_add(): void
    {
        $from = $this->dubai('2026-11-19 17:00');
        $to   = $this->dubai('2026-11-20 10:00');

        $this->assertEquals(120.0, $this->clock()->between($from, $to, true, $this->t->id));
        $this->assertSame($from->timestamp, $this->clock()->subtract($to, 120, true, $this->t->id)->timestamp);
        $this->assertEquals(17.0 * 60, $this->clock()->between($from, $to, false, $this->t->id));
    }

    public function test_the_warning_point_is_80_percent_of_the_target_in_open_minutes(): void
    {
        // due Friday 10:00 Dubai, target 600 open minutes: the last 20 percent (120 minutes) starts Thursday 17:00 Dubai
        $warn = $this->clock()->warnAt($this->dubai('2026-11-20 10:00'), 600, true, $this->t->id);

        $this->assertSame($this->dubai('2026-11-19 17:00')->timestamp, $warn->timestamp);
    }

    /* ---------- a request's due times ---------- */

    public function test_a_new_request_gets_due_times_from_a_business_hours_policy(): void
    {
        $this->policy(120, 600, business: true);

        $sr = $this->openRequest();

        // response: Thursday 14:00 + 2 open hours = 16:00 Dubai; resolution: 4 hours today + 6 hours on Friday = Friday 15:00 Dubai
        $this->assertSame('2026-11-19 12:00:00', $sr->sla_response_due_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-20 11:00:00', $sr->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_policy_without_business_hours_keeps_plain_minutes(): void
    {
        $this->policy(60, 240, business: false);

        $sr = $this->openRequest();

        $this->assertSame('2026-11-19 11:00:00', $sr->sla_response_due_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 14:00:00', $sr->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_priority_change_recalculates_with_the_hours(): void
    {
        $this->policy(120, 600, business: true);
        $this->policy(30, 60, business: true, priority: 'high');
        $sr = $this->openRequest();

        $this->as('operations_manager')->post(route('service-requests.change-priority', $sr), ['priority' => 'high'])->assertSessionHasNoErrors();

        // 60 open minutes from Thursday 14:00 Dubai = 15:00 Dubai = 11:00 UTC
        $this->assertSame('2026-11-19 11:00:00', $sr->fresh()->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));
    }

    /* ---------- pause and resume ---------- */

    public function test_resume_pushes_the_due_times_out_by_the_time_that_was_left(): void
    {
        $this->policy(60, 240);
        $sr = $this->openRequest();                               // resolution due 14:00 UTC

        $this->at('2026-11-19 11:00:00');
        $this->clock()->pause($sr->fresh());
        $this->assertNotNull($sr->fresh()->sla_paused_at);

        $this->at('2026-11-19 12:00:00');                         // an hour on hold: 3 hours were left at 11:00
        $this->clock()->resume($sr->fresh());

        $fresh = $sr->fresh();
        $this->assertNull($fresh->sla_paused_at);
        $this->assertSame('2026-11-19 15:00:00', $fresh->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(60, (int) $fresh->sla_paused_minutes);
    }

    public function test_a_pause_over_closing_time_counts_only_open_minutes(): void
    {
        $this->policy(60, 240, business: true);
        $sr = $this->openRequest();                               // resolution: 4 open hours from Thursday 14:00 = 18:00 Dubai = 14:00 UTC

        $this->at('2026-11-19 12:00:00');                         // 16:00 Dubai, 2 open hours left
        $this->clock()->pause($sr->fresh());
        $this->at('2026-11-20 06:00:00');                         // Friday 10:00 Dubai (the hold ran overnight)
        $this->clock()->resume($sr->fresh());

        // 2 open hours from Friday 10:00 = 12:00 Dubai = 08:00 UTC
        $fresh = $sr->fresh();
        $this->assertSame('2026-11-20 08:00:00', $fresh->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(180, (int) $fresh->sla_paused_minutes);   // 2 open hours Thursday + 1 open hour Friday
    }

    public function test_a_hold_pauses_the_sla_only_when_its_reason_says_so(): void
    {
        $this->policy(60, 240);
        $cat = $this->makeCategory();
        $pausing = HoldReason::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'label' => 'Waiting for client', 'pauses_sla' => true]);
        $plain   = HoldReason::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'label' => 'Lunch', 'pauses_sla' => false]);

        $sr = $this->jobInProgress($cat);
        $this->clock()->start($sr);                               // approved earlier: the clock is running
        $sr = $sr->fresh();
        $this->as('field_technician')->post(route('technician.jobs.hold', $sr), ['client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'hold_reason_id' => $plain->id])->assertSessionHasNoErrors();
        $this->assertNull($sr->fresh()->sla_paused_at);
        $this->as('field_technician')->post(route('technician.jobs.resume', $sr), ['client_event_uuid' => (string) \Illuminate\Support\Str::uuid()]);

        $before = $sr->fresh()->sla_resolution_due_at;
        $this->as('field_technician')->post(route('technician.jobs.hold', $sr), ['client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'hold_reason_id' => $pausing->id])->assertSessionHasNoErrors();
        $this->assertNotNull($sr->fresh()->sla_paused_at);

        $this->at('2026-11-19 10:30:00');
        $this->as('field_technician')->post(route('technician.jobs.resume', $sr), ['client_event_uuid' => (string) \Illuminate\Support\Str::uuid()]);

        $fresh = $sr->fresh();
        $this->assertNull($fresh->sla_paused_at);
        $this->assertEquals(30, $before->diffInMinutes($fresh->sla_resolution_due_at));
    }

    /* ---------- hours and holidays saved ---------- */

    public function test_saving_a_holiday_recalculates_open_business_hours_requests(): void
    {
        $this->policy(120, 600, business: true);
        $sr = $this->openRequest();                               // resolution due Friday 15:00 Dubai

        $this->as('company_admin')->post(route('admin.tenant-holidays.store'), ['holiday_date' => '2026-11-20', 'name' => 'National day'])->assertSessionHasNoErrors();

        // Thursday 4 open hours, Friday is a holiday, Saturday 4 open hours, 2 hours on Monday from 09:00 = Monday 11:00 Dubai = 07:00 UTC
        $this->assertSame('2026-11-23 07:00:00', $sr->fresh()->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_one_time_command_recalculates_open_requests_and_leaves_plain_policies_alone(): void
    {
        $this->policy(120, 600, business: true);
        $business = $this->openRequest();
        $this->holiday('2026-11-20');                              // saved outside the screens, so nothing recalculated yet
        $this->assertSame('2026-11-20 11:00:00', $business->fresh()->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));

        $this->artisan('sla:recalculate', ['--tenant' => $this->t->id])->assertSuccessful();

        $this->assertSame('2026-11-23 07:00:00', $business->fresh()->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_response_that_stopped_at_first_punch_in_is_read_from_first_punch_in(): void
    {
        $this->policy(60, 240);
        $sr = $this->openRequest(['sla_response_due_at' => now()->subMinutes(5), 'tech_accepted_at' => null, 'first_punch_in_at' => now()->subMinutes(10)]);

        $this->assertNotSame('response_breached', $sr->slaState());
    }
}
