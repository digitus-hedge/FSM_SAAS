<?php

namespace Tests\Feature\Sla;

use App\Models\QuoteToken;
use App\Services\Approval\RequestApprover;
use App\Services\Sla\SlaMonitor;

/**
 * Blueprint 7.1: the SLA clock starts at approval. A request that waits for approval has no clock; an Emergency request starts it at creation;
 * a quote accepted by the client starts it for a request that was waiting for the quote. Policy: response 60, resolution 240, plain minutes.
 */
class SlaClockStartTest extends SlaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->policy(60, 240);
        $this->policy(15, 60, priority: 'emergency');
    }

    protected function approve($sr): void
    {
        app(RequestApprover::class)->complete($sr->fresh(), 'out_of_coverage', null, null, false, $this->u['operations_manager']->id);
    }

    public function test_a_request_that_waits_for_approval_has_no_clock(): void
    {
        $sr = $this->raise($this->makeCategory());

        $this->assertSame('pending', $sr->status);
        $this->assertNull($sr->sla_clock_started_at);
        $this->assertNull($sr->sla_response_due_at);
        $this->assertNull($sr->sla_resolution_due_at);
        $this->assertSame('not_started', $sr->slaState());
    }

    public function test_approval_starts_the_clock(): void
    {
        $sr = $this->raise($this->makeCategory());

        $this->at('2026-11-19 11:00:00');                          // approved an hour after it was raised
        $this->approve($sr);

        $fresh = $sr->fresh();
        $this->assertSame('2026-11-19 11:00:00', $fresh->sla_clock_started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 12:00:00', $fresh->sla_response_due_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 15:00:00', $fresh->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_an_emergency_request_starts_the_clock_at_creation_and_approval_keeps_that_start(): void
    {
        $sr = $this->raise($this->makeCategory(), ['priority' => 'emergency']);

        $this->assertSame('2026-11-19 10:00:00', $sr->sla_clock_started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 10:15:00', $sr->sla_response_due_at->utc()->format('Y-m-d H:i:s'));

        $this->at('2026-11-19 11:00:00');
        $this->approve($sr);

        $this->assertSame('2026-11-19 10:00:00', $sr->fresh()->sla_clock_started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 11:00:00', $sr->fresh()->sla_resolution_due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_raising_a_waiting_request_to_emergency_starts_its_clock_then(): void
    {
        $sr = $this->raise($this->makeCategory());

        $this->at('2026-11-19 10:30:00');
        $this->as('operations_manager')->post(route('service-requests.change-priority', $sr), ['priority' => 'emergency'])->assertSessionHasNoErrors();

        $this->assertSame('2026-11-19 10:30:00', $sr->fresh()->sla_clock_started_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_check_job_ignores_a_request_that_is_still_waiting_for_approval(): void
    {
        $sr = $this->raise($this->makeCategory());
        $sr->forceFill(['sla_response_due_at' => now()->subMinutes(5)])->save();     // a due time without a clock is never acted on

        $r = (new SlaMonitor())->runForTenant($this->t->fresh());

        $this->assertSame(0, $r['checked']);
        $this->assertFalse($sr->fresh()->sla_response_breached);
    }

    public function test_a_quote_accepted_by_the_client_starts_the_clock(): void
    {
        $sr = $this->raise($this->makeCategory());
        $sr->forceFill(['status' => 'quoted', 'quote_requested_at' => now()])->save();
        $quote = $this->makeQuote($this->t, $this->client, $sr, ['status' => 'sent', 'sent_at' => now()]);
        $token = QuoteToken::create(['tenant_id' => $this->t->id, 'quote_id' => $quote->id, 'recipient_name' => 'Client', 'token' => QuoteToken::generate(), 'sent_via' => 'email', 'sent_at' => now()]);

        $this->at('2026-11-19 12:00:00');
        $this->post(route('public.quote.accept', $token->token), ['name' => 'A Client', 'confirmed' => 1])->assertSessionHasNoErrors();

        $fresh = $sr->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame('2026-11-19 12:00:00', $fresh->sla_clock_started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 13:00:00', $fresh->sla_response_due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_trades_of_a_master_start_their_clocks_when_the_master_is_approved(): void
    {
        $cat = $this->makeCategory();
        $master = app(\App\Services\Trades\MasterRequests::class)->create($this->t, $this->u['front_desk'], [
            'client_id' => $this->client->id, 'project_site_id' => $this->site->id, 'priority' => 'medium', 'title' => 'Fit-out',
        ], [['service_category_id' => $cat->id], ['service_category_id' => $this->makeCategory()->id]]);

        $children = \App\Models\ServiceRequest::withoutGlobalScopes()->where('parent_sr_id', $master->id)->get();
        $this->assertCount(2, $children);
        $this->assertTrue($children->every(fn ($c) => $c->sla_clock_started_at === null));

        $this->at('2026-11-19 11:00:00');
        $this->approve($master);

        $children = \App\Models\ServiceRequest::withoutGlobalScopes()->where('parent_sr_id', $master->id)->get();
        $this->assertTrue($children->every(fn ($c) => $c->sla_clock_started_at?->utc()->format('H:i') === '11:00'));
    }
}
