<?php

namespace Tests\Feature\Sla;

use App\Models\ServiceRequest;
use App\Services\Sla\SlaMonitor;
use Carbon\Carbon;

/**
 * The `sla:check` job (blueprint 7.1 and 9.2). Policy used unless stated: response 60 minutes, resolution 600 minutes, every minute counts.
 * Warning point = due time minus the last 20 percent of the target (12 minutes for the response, 120 for the resolution).
 */
class SlaMonitorTest extends SlaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->policy(60, 600);
    }

    protected function run1(?Carbon $now = null): array
    {
        return (new SlaMonitor())->runForTenant($this->t->fresh(), $now);
    }

    /** A request whose response due time is $respMin minutes from now; the resolution target is far away unless given. */
    protected function sr(?int $respMin, ?int $resoMin = 5000, array $over = []): ServiceRequest
    {
        return $this->openRequest(array_merge([
            'sla_clock_started_at'  => now()->subHour(),
            'sla_response_due_at'   => $respMin === null ? null : now()->addMinutes($respMin),
            'sla_resolution_due_at' => $resoMin === null ? null : now()->addMinutes($resoMin),
        ], $over));
    }

    /* ---------- warning ---------- */

    public function test_a_warning_goes_out_once_at_80_percent_to_the_ops_manager_and_the_requests_team_lead(): void
    {
        $sr = $this->sr(10, over: ['team_lead_user_id' => $this->u['team_lead']->id]);   // due in 10 minutes, warning point was 2 minutes ago

        $first = $this->run1();
        $second = $this->run1();
        $this->at('2026-11-19 10:05:00');
        $third = $this->run1();

        $this->assertSame(1, $first['warnings']);
        $this->assertSame(0, $second['warnings']);
        $this->assertSame(0, $third['warnings']);
        $this->assertNotNull($sr->fresh()->sla_response_warned_at);
        $this->assertSame(1, $this->notified('sla.warning', $this->u['operations_manager']));
        $this->assertSame(1, $this->notified('sla.warning', $this->u['team_lead']));
        $this->assertSame(0, $this->notified('sla.warning', $this->u['finance']));
        $this->assertSame(0, $this->notified('sla.warning', $this->u['company_admin']));
        $this->assertSame(1, $this->audits('sla.warning'));
    }

    public function test_no_warning_before_80_percent(): void
    {
        $sr = $this->sr(30);                                       // warning point is 18 minutes from now

        $this->assertSame(0, $this->run1()['warnings']);
        $this->assertNull($sr->fresh()->sla_response_warned_at);

        $this->at('2026-11-19 10:20:00');                          // 10 minutes left: past the warning point
        $this->assertSame(1, $this->run1()['warnings']);
    }

    public function test_the_resolution_target_has_its_own_warning_and_breach(): void
    {
        $sr = $this->sr(null, 100, ['first_punch_in_at' => now()->subMinutes(30)]);   // 100 minutes left of 600: inside the last 120

        $this->assertSame(1, $this->run1()['warnings']);
        $this->assertNotNull($sr->fresh()->sla_resolution_warned_at);
        $this->assertNull($sr->fresh()->sla_response_warned_at);

        $this->at('2026-11-19 12:00:00');
        $this->assertSame(1, $this->run1()['breaches']);
        $this->assertTrue($sr->fresh()->sla_resolution_breached);
        $this->assertFalse($sr->fresh()->sla_response_breached);
    }

    /* ---------- breach ---------- */

    public function test_a_breach_goes_out_once_to_the_ops_manager_and_the_company_admin(): void
    {
        $sr = $this->sr(-5, over: ['team_lead_user_id' => $this->u['team_lead']->id]);   // response due 5 minutes ago

        $first = $this->run1();
        $second = $this->run1();

        $this->assertSame(1, $first['breaches']);
        $this->assertSame(0, $second['breaches']);
        $this->assertTrue($sr->fresh()->sla_response_breached);
        $this->assertSame(1, $this->notified('sla.breached', $this->u['operations_manager']));
        $this->assertSame(1, $this->notified('sla.breached', $this->u['company_admin']));
        $this->assertSame(0, $this->notified('sla.breached', $this->u['team_lead']));
        $this->assertSame(1, $this->audits('sla.breached'));
    }

    public function test_a_missed_target_never_sends_the_warning_afterwards(): void
    {
        $sr = $this->sr(-5);

        $this->run1();
        $this->run1();

        $this->assertNotNull($sr->fresh()->sla_response_warned_at);         // marked as handled
        $this->assertSame(0, $this->notified('sla.warning', $this->u['operations_manager']));
    }

    /* ---------- no flood ---------- */

    public function test_an_old_breach_sets_the_flag_and_the_audit_row_but_sends_nothing(): void
    {
        $sr = $this->sr(-180);                                     // due 3 hours ago

        $r = $this->run1();

        $this->assertSame(0, $r['breaches']);
        $this->assertSame(1, $r['silent']);
        $this->assertTrue($sr->fresh()->sla_response_breached);
        $this->assertSame(0, $this->notified('sla.breached', $this->u['operations_manager']));
        $this->assertSame(1, $this->audits('sla.breached'));
        $this->assertSame(0, $this->run1()['silent']);             // and it is not handled twice
    }

    public function test_a_breach_just_inside_two_hours_is_still_sent(): void
    {
        $this->sr(-119);

        $this->assertSame(1, $this->run1()['breaches']);
    }

    public function test_a_warning_point_older_than_two_hours_is_flagged_and_audited_without_a_message(): void
    {
        // a target of 6000 minutes: the last 20 percent is 1200 minutes, so a due time 1000 minutes away had its warning 200 minutes ago
        $this->policy(60, 6000, priority: 'high');
        $sr = $this->openRequest(['sla_response_due_at' => null, 'sla_resolution_due_at' => now()->addMinutes(1000), 'first_punch_in_at' => now()], 'high');

        $r = $this->run1();

        $this->assertSame(0, $r['warnings']);
        $this->assertSame(1, $r['silent']);
        $this->assertNotNull($sr->fresh()->sla_resolution_warned_at);
        $this->assertSame(0, $this->notified('sla.warning', $this->u['operations_manager']));
        $this->assertSame(1, $this->audits('sla.warning'));
    }

    /* ---------- what is not checked ---------- */

    public function test_the_response_target_stops_at_first_punch_in(): void
    {
        $sr = $this->sr(-5, over: ['first_punch_in_at' => now()->subMinutes(10)]);

        $this->assertSame(0, $this->run1()['breaches']);
        $this->assertFalse($sr->fresh()->sla_response_breached);
    }

    public function test_requests_after_the_qc_pass_or_not_running_are_left_alone(): void
    {
        foreach ([['status' => 'completed'], ['status' => 'awaiting_invoice'], ['status' => 'cancelled'], ['status' => 'quoted'],
                  ['completed_at' => now()->subHour()], ['reopened_count' => 1], ['sla_paused_at' => now()->subMinutes(30)],
                  ['is_master_sr' => true], ['sla_clock_started_at' => null]] as $over) {
            $this->sr(-5, -5, $over);
        }

        $r = $this->run1();

        $this->assertSame(0, $r['checked']);
        $this->assertSame(0, $r['breaches'] + $r['warnings'] + $r['silent']);
    }

    public function test_a_request_without_a_due_time_is_ignored(): void
    {
        $sr = $this->sr(null, null);

        $r = $this->run1();

        $this->assertSame(0, $r['warnings'] + $r['breaches']);
    }

    public function test_only_the_given_company_is_touched(): void
    {
        $mine = $this->sr(-5);
        $other = $this->makeTenant(slug: 'other');
        $theirs = $mine->replicate();
        $theirs->forceFill(['tenant_id' => $other->id, 'sr_number' => 'OTHER-1'])->save();

        $this->run1();

        $this->assertTrue($mine->fresh()->sla_response_breached);
        $this->assertFalse(ServiceRequest::withoutGlobalScopes()->find($theirs->id)->sla_response_breached);
    }

    public function test_the_job_restores_the_current_company(): void
    {
        $this->sr(-5);                                       // raised through a company page, which sets the company in this test process
        \App\Models\Tenant::setCurrent(null);                // the scheduler starts without a company

        $this->run1();

        $this->assertNull(\App\Models\Tenant::current());
    }

    /* ---------- business hours and holidays ---------- */

    public function test_the_warning_is_worked_out_in_open_minutes(): void
    {
        $this->policy(120, 600, business: true, priority: 'low');
        $sr = $this->openRequest(['first_punch_in_at' => now()], 'low');   // only the resolution target: due Friday 15:00 Dubai = 11:00 UTC

        // 08:00 Dubai Friday: the company is still closed. Plain minutes would have warned at 06:48 Dubai.
        $this->at('2026-11-20 04:00:00');
        $this->assertSame(0, $this->run1()['warnings']);

        // 13:30 Dubai Friday: past 13:00 (the last 2 open hours begin)
        $this->at('2026-11-20 09:30:00');
        $this->assertSame(1, $this->run1()['warnings']);
    }

    public function test_a_holiday_moves_the_breach_out(): void
    {
        $this->policy(120, 600, business: true, priority: 'low');
        $sr = $this->openRequest(['first_punch_in_at' => now()], 'low');
        $this->holiday('2026-11-20');
        $this->clock()->recalculate($sr->fresh());               // due moves to Monday 11:00 Dubai

        $this->at('2026-11-20 12:00:00');                         // Friday 16:00 Dubai: a plain due time of 15:00 would be missed by now
        $r = $this->run1();

        $this->assertSame(0, $r['breaches']);
        $this->assertFalse($sr->fresh()->sla_resolution_breached);
    }

    /* ---------- the command ---------- */

    public function test_the_command_runs_for_every_active_company(): void
    {
        $sr = $this->sr(-5);

        $this->artisan('sla:check')->assertSuccessful();

        $this->assertTrue($sr->fresh()->sla_response_breached);
    }

    public function test_the_command_is_scheduled_every_minute_without_overlapping(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'sla:check'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_the_due_time_columns_are_indexed(): void
    {
        $indexes = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM service_requests'))->pluck('Key_name')->unique();

        $this->assertTrue($indexes->contains('idx_tenant_sla'));
        $this->assertTrue($indexes->contains('idx_tenant_sla_response'));
    }
}
