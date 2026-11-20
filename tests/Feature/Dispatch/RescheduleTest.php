<?php

namespace Tests\Feature\Dispatch;

use App\Mail\JobRescheduledMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * DSP-08: drag and drop on the calendar. It writes sr_reschedules and an audit row, emails the client contact through JobRescheduledMail
 * only when the date or time really changes, and Emergency jobs sit at the top of their day. Times are UTC here; the company is in Dubai (+4).
 */
class RescheduleTest extends DispatchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function move($sr, string $to, array $extra = [], string $who = 'operations_manager')
    {
        return $this->as($who)->postJson(route('dispatch.calendar.reschedule', $sr), ['new_eta' => $to] + $extra);
    }

    /* ---------- the move ---------- */

    public function test_a_move_writes_the_reschedule_row_the_audit_row_and_the_new_time(): void
    {
        $sr = $this->assigned(null, '2026-11-20 06:00:00');

        $this->move($sr, '2026-11-20T10:00:00Z', ['reason' => 'Client asked'])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('2026-11-20 10:00:00', Carbon::parse($sr->fresh()->scheduled_eta_at)->utc()->format('Y-m-d H:i:s'));
        $row = DB::table('sr_reschedules')->where('service_request_id', $sr->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('drag_drop', $row->source);
        $this->assertSame('Client asked', $row->reason);
        $this->assertSame($this->u['operations_manager']->id, (int) $row->rescheduled_by_user_id);
        $this->assertSame(1, $this->audits('request.rescheduled'));
    }

    public function test_dropping_a_job_on_the_time_it_already_has_changes_nothing(): void
    {
        $sr = $this->assigned(null, '2026-11-20 06:00:00');
        $this->contact('client@acme.test', ['is_primary' => true]);

        $this->move($sr, '2026-11-20T06:00:00Z')->assertOk()->assertJson(['unchanged' => true]);

        $this->assertSame(0, DB::table('sr_reschedules')->where('service_request_id', $sr->id)->count());
        $this->assertSame(0, $this->audits('request.rescheduled'));
        Mail::assertNotSent(JobRescheduledMail::class);
    }

    public function test_only_an_assigned_job_can_be_moved(): void
    {
        $sr = $this->assigned();
        $sr->forceFill(['status' => 'in_progress'])->save();

        $this->move($sr, '2026-11-21T06:00:00Z')->assertStatus(422);

        $this->assertSame(0, DB::table('sr_reschedules')->where('service_request_id', $sr->id)->count());
    }

    public function test_a_time_in_the_past_is_refused(): void
    {
        $sr = $this->assigned();

        $this->move($sr, '2026-11-19T08:00:00Z')->assertStatus(422)->assertJsonValidationErrors('new_eta');
    }

    public function test_time_off_blocks_the_move(): void
    {
        $sr = $this->assigned();
        $this->timeOff($this->u['field_technician'], '2026-11-22 00:00:00', '2026-11-23 00:00:00');

        $this->move($sr, '2026-11-22T06:00:00Z')->assertStatus(422);
    }

    public function test_a_clash_asks_once_and_then_allows_the_move(): void
    {
        $sr = $this->assigned(null, '2026-11-20 06:00:00');
        $this->assigned(null, '2026-11-21 06:00:00');

        $this->move($sr, '2026-11-21T06:30:00Z')->assertStatus(409)->assertJson(['needs_confirm' => true]);
        $this->assertSame(0, DB::table('sr_reschedules')->count());

        $this->move($sr, '2026-11-21T06:30:00Z', ['confirm_clash' => true])->assertOk();
        $this->assertSame(1, DB::table('sr_reschedules')->count());
    }

    public function test_who_may_move_a_job(): void
    {
        $normal = $this->assigned();
        $urgent = $this->assigned(null, '2026-11-20 06:00:00', 'emergency');

        $this->move($normal, '2026-11-21T06:00:00Z', [], 'finance')->assertForbidden();
        $this->move($normal, '2026-11-21T06:00:00Z', [], 'front_desk')->assertForbidden();           // Front Desk: critical and emergency only
        $this->move($urgent, '2026-11-21T06:00:00Z', [], 'front_desk')->assertOk();
    }

    public function test_another_companys_request_is_a_404(): void
    {
        $other = $this->makeTenant(slug: 'other');
        $theirs = $this->assigned()->replicate();
        $theirs->forceFill(['tenant_id' => $other->id, 'sr_number' => 'OTHER-1'])->save();

        $this->move($theirs, '2026-11-21T06:00:00Z')->assertNotFound();
    }

    /* ---------- the client email ---------- */

    public function test_the_primary_contact_gets_the_email_when_the_time_changes(): void
    {
        $sr = $this->assigned(null, '2026-11-20 06:00:00');                                   // 10:00 in Dubai
        $this->contact('second@acme.test');
        $this->contact('primary@acme.test', ['is_primary' => true]);

        $this->move($sr, '2026-11-21T10:30:00Z', ['reason' => 'Parts delayed'])->assertOk()->assertJson(['client_emailed' => true]);

        Mail::assertSent(JobRescheduledMail::class, 1);
        Mail::assertSent(JobRescheduledMail::class, fn ($m) => $m->hasTo('primary@acme.test') && ! $m->hasTo('second@acme.test') && $m->reason === 'Parts delayed');
    }

    public function test_a_move_to_another_day_with_the_same_clock_time_also_sends(): void
    {
        $sr = $this->assigned(null, '2026-11-20 06:00:00');
        $this->contact('primary@acme.test', ['is_primary' => true]);

        $this->move($sr, '2026-11-21T06:00:00Z')->assertOk();

        Mail::assertSent(JobRescheduledMail::class, 1);
    }

    public function test_without_a_primary_the_first_active_contact_with_an_email_is_used(): void
    {
        $sr = $this->assigned();
        $this->contact('inactive@acme.test', ['is_active' => false]);
        $this->contact('', ['is_primary' => false]);
        $this->contact('first@acme.test');

        $this->move($sr, '2026-11-21T06:00:00Z')->assertOk();

        Mail::assertSent(JobRescheduledMail::class, fn ($m) => $m->hasTo('first@acme.test'));
        Mail::assertSent(JobRescheduledMail::class, 1);
    }

    public function test_an_inactive_primary_is_skipped(): void
    {
        $sr = $this->assigned();
        $this->contact('gone@acme.test', ['is_primary' => true, 'is_active' => false]);
        $this->contact('here@acme.test');

        $this->move($sr, '2026-11-21T06:00:00Z')->assertOk();

        Mail::assertSent(JobRescheduledMail::class, fn ($m) => $m->hasTo('here@acme.test') && ! $m->hasTo('gone@acme.test'));
    }

    public function test_a_client_without_an_email_still_gets_the_move_and_nothing_is_sent(): void
    {
        $sr = $this->assigned();

        $this->move($sr, '2026-11-21T06:00:00Z')->assertOk()->assertJson(['client_emailed' => false]);

        Mail::assertNotSent(JobRescheduledMail::class);
        $this->assertSame(1, DB::table('sr_reschedules')->where('service_request_id', $sr->id)->count());
    }

    public function test_another_clients_contact_never_gets_the_email(): void
    {
        $sr = $this->assigned();
        $other = $this->makeClient($this->t, 'Another client');
        \App\Models\ClientContact::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $other->id, 'name' => 'Other', 'email' => 'other@else.test', 'is_active' => true, 'is_primary' => true]);

        $this->move($sr, '2026-11-21T06:00:00Z')->assertOk();

        Mail::assertNotSent(JobRescheduledMail::class);
    }

    public function test_the_email_names_the_old_and_new_time_in_company_time_and_hides_internal_details(): void
    {
        $sr = $this->assigned(null, '2026-11-20 06:00:00', 'emergency');                      // Friday 10:00 Dubai
        $sr->forceFill(['title' => 'Chiller fault'])->save();
        $this->contact('primary@acme.test', ['is_primary' => true, 'name' => 'Priya']);

        $this->move($sr, '2026-11-21T10:30:00Z', ['reason' => 'Parts delayed'])->assertOk();

        Mail::assertSent(JobRescheduledMail::class, function (JobRescheduledMail $m) use ($sr) {
            $html = $m->render();
            $this->assertStringContainsString('Hello Priya', $html);
            $this->assertStringContainsString($sr->sr_number, $html);
            $this->assertStringContainsString('Chiller fault', $html);
            $this->assertStringContainsString('Fri 20 Nov 2026, 10:00', $html);                // before, in Dubai time
            $this->assertStringContainsString('Sat 21 Nov 2026, 14:30', $html);                // after, in Dubai time
            $this->assertStringContainsString('Reason: Parts delayed', $html);
            $this->assertStringContainsString('Main site', $html);
            foreach (['emergency', 'Emergency', $this->u['field_technician']->name, 'out_of_coverage', 'coverage'] as $internal) {
                $this->assertStringNotContainsString($internal, $html);
            }
            $this->assertStringContainsString('has moved to Sat 21 Nov, 14:30', $m->envelope()->subject);

            return true;
        });
    }

    public function test_a_failing_mailer_does_not_undo_the_move(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $sr = $this->assigned();
        $this->contact('primary@acme.test', ['is_primary' => true]);

        $this->move($sr, '2026-11-21T06:00:00Z')->assertOk()->assertJson(['client_emailed' => false]);

        $this->assertSame(1, DB::table('sr_reschedules')->where('service_request_id', $sr->id)->count());
        $this->assertSame('2026-11-21 06:00:00', Carbon::parse($sr->fresh()->scheduled_eta_at)->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_refused_move_sends_no_email(): void
    {
        $sr = $this->assigned();
        $this->contact('primary@acme.test', ['is_primary' => true]);

        $this->move($sr, '2026-11-19T08:00:00Z')->assertStatus(422);
        $this->move($sr, '2026-11-21T06:00:00Z', [], 'finance')->assertForbidden();

        Mail::assertNotSent(JobRescheduledMail::class);
    }

    /* ---------- the calendar: Emergency first ---------- */

    public function test_emergency_jobs_sit_at_the_top_of_their_day_whatever_their_time(): void
    {
        $tech = $this->u['field_technician'];
        $early = $this->assigned($tech, '2026-11-19 11:00:00', 'medium');
        $late  = $this->assigned($tech, '2026-11-19 14:00:00', 'emergency');
        $mid   = $this->assigned($tech, '2026-11-19 12:30:00', 'high');
        $later = $this->assigned($tech, '2026-11-19 13:00:00', 'emergency');

        $html = $this->as('operations_manager')->get($this->onHost($this->t, '/dispatch/calendar?week=2026-11-19'))->assertOk()->getContent();

        $pos = fn ($sr) => strpos($html, 'data-sr="' . $sr->sr_number . '"');
        // inside the day: the two Emergency jobs first (earliest first), then the others by time
        $order = collect([$early, $late, $mid, $later])->sortBy($pos)->pluck('id')->values()->all();
        $this->assertSame([$later->id, $late->id, $early->id, $mid->id], $order);
    }

    public function test_the_emergency_card_is_marked(): void
    {
        $this->assigned(null, '2026-11-19 11:00:00', 'emergency');

        $html = $this->as('operations_manager')->get($this->onHost($this->t, '/dispatch/calendar?week=2026-11-19'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/cal-card[^"]*emergency/', $html);
    }

    public function test_the_calendar_shows_this_companys_jobs_only(): void
    {
        $mine = $this->assigned(null, '2026-11-19 11:00:00');
        $other = $this->makeTenant(slug: 'other');
        $theirs = $mine->replicate();
        $theirs->forceFill(['tenant_id' => $other->id, 'sr_number' => 'OTHER-9'])->save();

        $html = $this->as('operations_manager')->get($this->onHost($this->t, '/dispatch/calendar?week=2026-11-19'))->assertOk()->getContent();

        $this->assertStringContainsString($mine->sr_number, $html);
        $this->assertStringNotContainsString('OTHER-9', $html);
    }
}
