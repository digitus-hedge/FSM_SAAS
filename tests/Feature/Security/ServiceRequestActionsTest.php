<?php

namespace Tests\Feature\Security;

use App\Models\Client;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\UserTimeOff;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Currency\BillingTestCase;

/**
 * The service request actions that had no permission check (approve, assign, cancel, change priority), plus dispatch and time off.
 * The matrix gives the Front Desk record_level on sr.assign and sr.cancel, so the controllers narrow it (blueprint 4 and 7.1):
 *   assign, reschedule and suggestions: critical and emergency requests only;
 *   cancel and change priority: requests the Front Desk user raised;
 *   approve: Company Admin and Operations Manager;
 *   time off: full holders only (the Front Desk may look).
 */
class ServiceRequestActionsTest extends BillingTestCase
{
    private Tenant $t;
    private Client $client;
    private array $u = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->t = $this->makeTenant(slug: 'sra');
        $this->client = $this->makeClient($this->t);
        foreach (['company_admin', 'operations_manager', 'finance', 'front_desk'] as $code) {
            $this->u[$code] = $this->makeUser($this->t, $code);
        }
    }

    private function sr(string $status = 'approved', string $priority = 'medium', ?int $createdBy = null): ServiceRequest
    {
        $sr = $this->makeServiceRequest($this->t, $this->client, $status);
        $sr->forceFill(['priority' => $priority, 'created_by_user_id' => $createdBy])->save();

        return $sr;
    }

    private function as(string $who)
    {
        return $this->actingAs($this->u[$who]);
    }

    private function assignBody(): array
    {
        return ['technician_id' => $this->makeUser($this->t, 'field_technician', 'tech' . uniqid() . '@sra.test')->id, 'arrival_time' => now()->addDay()->toDateTimeString()];
    }

    private function notForbidden($res, string $msg): void
    {
        $this->assertNotSame(403, $res->getStatusCode(), $msg);
    }

    // ───────────── approve ─────────────

    #[Test]
    public function approving_needs_sr_approve_company_admin_and_operations_manager_only(): void
    {
        $body = ['coverage' => 'goodwill', 'note' => 'ok'];

        foreach (['front_desk', 'finance'] as $who) {
            $this->as($who)->post(route('service-requests.approve', $this->sr('pending')), $body)->assertForbidden();
        }
        foreach (['company_admin', 'operations_manager'] as $who) {
            $this->notForbidden($this->as($who)->post(route('service-requests.approve', $this->sr('pending')), $body), $who);
        }
    }

    // ───────────── send for quote and submit for review ─────────────

    #[Test]
    public function send_for_quote_needs_sr_approve_and_only_works_on_a_pending_request(): void
    {
        $pending = $this->sr('pending');

        foreach (['front_desk', 'finance'] as $who) {
            $this->as($who)->post(route('service-requests.send-for-quote', $pending))->assertForbidden();
        }
        $this->assertNull($pending->fresh()->quote_requested_at);

        $this->as('operations_manager')->post(route('service-requests.send-for-quote', $pending))->assertRedirect();
        $this->assertNotNull($pending->fresh()->quote_requested_at);
        $this->assertSame('out_of_coverage', $pending->fresh()->coverage_type);

        $second = $this->sr('pending');
        $this->as('company_admin')->post(route('service-requests.send-for-quote', $second))->assertRedirect();
        $this->assertNotNull($second->fresh()->quote_requested_at);

        // any other status is refused (422), whoever asks
        foreach (['approved', 'in_progress', 'completed', 'cancelled'] as $status) {
            $other = $this->sr($status);
            $this->as('company_admin')->post(route('service-requests.send-for-quote', $other))->assertStatus(422);
            $this->assertNull($other->fresh()->quote_requested_at, $status);
        }
    }

    #[Test]
    public function submit_for_review_needs_sr_qc_company_admin_and_operations_manager_only(): void
    {
        foreach (['front_desk', 'finance'] as $who) {
            $sr = $this->sr('in_progress');
            $this->as($who)->post(route('service-requests.mark-review', $sr))->assertForbidden();
            $this->assertSame('in_progress', $sr->fresh()->status);
        }
        foreach (['operations_manager', 'company_admin'] as $who) {
            $sr = $this->sr('in_progress');
            $this->as($who)->post(route('service-requests.mark-review', $sr))->assertRedirect();
            $this->assertSame('pending_review', $sr->fresh()->status, $who);
        }
        // still refused for the wrong status, even for an allowed role
        $this->as('company_admin')->post(route('service-requests.mark-review', $this->sr('approved')))->assertStatus(422);
    }

    #[Test]
    public function the_submit_for_review_button_shows_only_to_roles_that_may_use_it(): void
    {
        $sr = $this->sr('in_progress');
        $button = route('service-requests.mark-review', $sr, false);

        $this->as('operations_manager')->get(route('service-requests.show', $sr))->assertOk()->assertSee($button, false);
        $this->as('company_admin')->get(route('service-requests.show', $sr))->assertSee($button, false);
        $this->as('front_desk')->get(route('service-requests.show', $sr))->assertOk()->assertDontSee($button, false);
    }

    // ───────────── assign ─────────────

    #[Test]
    public function the_front_desk_assigns_only_critical_and_emergency_requests(): void
    {
        foreach (['low', 'medium', 'high'] as $p) {
            $this->as('front_desk')->post(route('service-requests.assign', $this->sr('approved', $p)), $this->assignBody())->assertForbidden();
        }
        foreach (['critical', 'emergency'] as $p) {
            $this->notForbidden($this->as('front_desk')->post(route('service-requests.assign', $this->sr('approved', $p)), $this->assignBody()), $p);
        }
        // Operations Manager and Company Admin: any priority; Finance: never
        $this->notForbidden($this->as('operations_manager')->post(route('service-requests.assign', $this->sr('approved', 'low')), $this->assignBody()), 'ops');
        $this->notForbidden($this->as('company_admin')->post(route('service-requests.assign', $this->sr('approved', 'low')), $this->assignBody()), 'admin');
        $this->as('finance')->post(route('service-requests.assign', $this->sr('approved', 'critical')), $this->assignBody())->assertForbidden();
    }

    // ───────────── cancel and change priority ─────────────

    #[Test]
    public function the_front_desk_cancels_and_re_prioritises_only_requests_they_raised(): void
    {
        $desk = $this->u['front_desk']->id;
        $mine = $this->sr('pending', 'medium', $desk);
        $theirs = $this->sr('pending', 'medium', $this->u['company_admin']->id);

        $this->notForbidden($this->as('front_desk')->post(route('service-requests.change-priority', $mine), ['priority' => 'high']), 'own priority');
        $this->as('front_desk')->post(route('service-requests.change-priority', $theirs), ['priority' => 'high'])->assertForbidden();

        $this->as('front_desk')->post(route('service-requests.cancel', $theirs), ['reason' => 'not mine'])->assertForbidden();
        $this->assertNotSame('cancelled', $theirs->fresh()->status);
        $this->notForbidden($this->as('front_desk')->post(route('service-requests.cancel', $mine), ['reason' => 'raised in error']), 'own cancel');
        $this->assertSame('cancelled', $mine->fresh()->status);
    }

    #[Test]
    public function operations_manager_and_admin_may_cancel_and_re_prioritise_any_request_and_finance_may_not(): void
    {
        $sr = $this->sr('pending', 'medium', $this->u['front_desk']->id);

        $this->as('finance')->post(route('service-requests.cancel', $sr), ['reason' => 'x'])->assertForbidden();
        $this->as('finance')->post(route('service-requests.change-priority', $sr), ['priority' => 'high'])->assertForbidden();
        $this->notForbidden($this->as('operations_manager')->post(route('service-requests.change-priority', $sr), ['priority' => 'high']), 'ops priority');
        $this->notForbidden($this->as('company_admin')->post(route('service-requests.cancel', $sr), ['reason' => 'duplicate']), 'admin cancel');
    }

    // ───────────── dispatch ─────────────

    #[Test]
    public function dispatch_suggestions_and_rescheduling_follow_the_same_rule_as_assigning(): void
    {
        $urgent = $this->sr('assigned', 'emergency');
        $normal = $this->sr('assigned', 'medium');
        $move = ['new_eta' => now()->addDays(2)->toDateTimeString()];

        $this->as('front_desk')->get(route('service-requests.suggestions', $normal))->assertForbidden();
        $this->notForbidden($this->as('front_desk')->get(route('service-requests.suggestions', $urgent) . '?arrival=' . urlencode(now()->addDay()->toDateTimeString())), 'urgent suggestions');
        $this->notForbidden($this->as('operations_manager')->get(route('service-requests.suggestions', $normal)), 'ops suggestions');

        $this->as('front_desk')->postJson(route('dispatch.calendar.reschedule', $normal), $move)->assertForbidden();
        $this->notForbidden($this->as('front_desk')->postJson(route('dispatch.calendar.reschedule', $urgent), $move), 'urgent reschedule');
        $this->notForbidden($this->as('operations_manager')->postJson(route('dispatch.calendar.reschedule', $normal), $move), 'ops reschedule');
        $this->as('finance')->postJson(route('dispatch.calendar.reschedule', $urgent), $move)->assertForbidden();
    }

    #[Test]
    public function the_front_desk_may_look_at_the_workload_and_calendar_but_the_finance_may_not(): void
    {
        $this->as('front_desk')->get(route('dispatch.workload'))->assertOk();
        $this->as('front_desk')->get(route('dispatch.calendar'))->assertOk();
        $this->as('finance')->get(route('dispatch.workload'))->assertForbidden();
    }

    // ───────────── time off ─────────────

    #[Test]
    public function time_off_can_be_looked_at_by_the_front_desk_but_only_changed_by_full_holders(): void
    {
        $tech = $this->makeUser($this->t, 'field_technician', 'off@sra.test');
        $body = ['user_id' => $tech->id, 'type' => array_key_first(UserTimeOff::TYPES), 'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDays(2)->toDateTimeString()];

        $this->as('front_desk')->get(route('dispatch.time-off'))->assertOk();
        $this->as('front_desk')->post(route('dispatch.time-off.store'), $body)->assertForbidden();
        $this->assertSame(0, UserTimeOff::where('tenant_id', $this->t->id)->count());

        $this->as('operations_manager')->post(route('dispatch.time-off.store'), $body)->assertRedirect();
        $row = UserTimeOff::where('tenant_id', $this->t->id)->sole();

        $this->as('front_desk')->delete(route('dispatch.time-off.destroy', $row->id))->assertForbidden();
        $this->assertNotNull(UserTimeOff::find($row->id));
        $this->as('company_admin')->delete(route('dispatch.time-off.destroy', $row->id))->assertRedirect();
    }

    // ───────────── the page shows only the buttons the user may use ─────────────

    #[Test]
    public function the_request_page_hides_the_actions_the_user_may_not_do(): void
    {
        $normal = $this->sr('approved', 'medium', $this->u['company_admin']->id);
        $assign = "getElementById('assign-modal').classList.add";
        $cancel = "getElementById('cancel-modal').classList.add";
        $prio   = "getElementById('priority-modal').classList.add";

        $this->as('front_desk')->get(route('service-requests.show', $normal))->assertOk()
            ->assertDontSee($assign, false)->assertDontSee($cancel, false)->assertDontSee($prio, false);

        $this->as('operations_manager')->get(route('service-requests.show', $normal))->assertOk()
            ->assertSee($assign, false)->assertSee($cancel, false)->assertSee($prio, false);

        $own = $this->sr('approved', 'critical', $this->u['front_desk']->id);
        $this->as('front_desk')->get(route('service-requests.show', $own))->assertSee($assign, false)->assertSee($cancel, false)->assertSee($prio, false);

        $pending = $this->sr('pending');
        $approve = "getElementById('approve-modal').classList.add";
        $this->as('front_desk')->get(route('service-requests.show', $pending))->assertDontSee($approve, false);
        $this->as('operations_manager')->get(route('service-requests.show', $pending))->assertSee($approve, false);
    }

    // ───────────── sidebar ─────────────

    #[Test]
    public function the_terminology_link_shows_only_to_roles_that_can_open_it(): void
    {
        $link = '/admin/terminology"';

        $this->as('company_admin')->get($this->onHost($this->t, '/dashboard'))->assertOk()->assertSee($link, false);
        $this->as('operations_manager')->get($this->onHost($this->t, '/dashboard'))->assertOk()->assertDontSee($link, false); // settings.manage yes, vocabulary.manage no
        $this->as('operations_manager')->get($this->onHost($this->t, '/admin/terminology'))->assertForbidden();
        $this->as('finance')->get($this->onHost($this->t, '/dashboard'))->assertOk()->assertDontSee($link, false);
    }
}
