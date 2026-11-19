<?php

namespace Tests\Feature\Currency;

use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Services\Permissions\PermissionLevel;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * Quotes for a view-only holder of quote.manage (the Operations Manager, blueprint 7.7a): they can open the list, a quote and its
 * PDF, and see no button that changes anything. Every change route stays closed (403). Client users never get in.
 */
class QuoteViewOnlyTest extends BillingTestCase
{
    private Tenant $tenant;
    private $finance;
    private $ops;
    private ServiceRequest $sr;
    private Quote $draft;
    private Quote $sent;
    private Quote $accepted;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->tenant  = $this->makeTenant(slug: 'vq');
        $this->finance = $this->makeUser($this->tenant, 'finance');
        $this->ops     = $this->makeUser($this->tenant, 'operations_manager');
        $client = $this->makeClient($this->tenant);
        $this->sr = $this->makeServiceRequest($this->tenant, $client, 'pending');
        $this->draft    = $this->makeQuote($this->tenant, $client, $this->sr);
        $this->sent     = $this->makeQuote($this->tenant, $client, $this->makeServiceRequest($this->tenant, $client, 'quoted'), ['status' => 'sent']);
        $this->accepted = $this->makeQuote($this->tenant, $client, $this->makeServiceRequest($this->tenant, $client, 'approved'), ['status' => 'accepted']);
    }

    #[Test]
    public function the_permission_levels_are_what_the_signed_matrix_says(): void
    {
        $this->assertTrue(PermissionLevel::canView($this->ops, 'quote.manage'));
        $this->assertFalse(PermissionLevel::canChange($this->ops, 'quote.manage'));
        $this->assertTrue(PermissionLevel::canView($this->finance, 'quote.manage'));
        $this->assertTrue(PermissionLevel::canChange($this->finance, 'quote.manage'));

        $client = $this->makeUser($this->tenant, 'client_user'); // quote.manage is view_own for clients: not an office view level
        $this->assertTrue($client->canDo('quote.manage'));
        $this->assertFalse(PermissionLevel::canView($client, 'quote.manage'));
        $this->assertFalse(PermissionLevel::canView($this->makeUser($this->tenant, 'front_desk'), 'quote.manage'));
    }

    #[Test]
    public function the_operations_manager_opens_the_list_a_quote_and_its_pdf(): void
    {
        $this->actingAs($this->ops)->get(route('admin.quotes.index', ['tab' => 'all']))
            ->assertOk()->assertSee($this->draft->quote_number)->assertSee($this->sent->quote_number);
        $this->actingAs($this->ops)->get(route('admin.quotes.show', $this->sent))->assertOk()->assertSee($this->sent->quote_number);
        $this->actingAs($this->ops)->get(route('admin.quotes.show', $this->draft))->assertOk();
        $this->actingAs($this->ops)->get(route('admin.quotes.pdf', $this->sent))->assertOk();
    }

    #[Test]
    public function every_change_route_is_closed_to_them(): void
    {
        $u = $this->actingAs($this->ops);

        $u->get(route('admin.quotes.build', $this->sr))->assertForbidden();
        $u->get(route('admin.quotes.edit', $this->draft))->assertForbidden();
        $u->put(route('admin.quotes.update', $this->draft), $this->builderPayload())->assertForbidden();
        $u->post(route('admin.quotes.send', $this->draft), ['recipients' => ['Anyone']])->assertForbidden();
        $u->post(route('admin.quotes.revise', $this->sent))->assertForbidden();
        $u->get(route('admin.quotes.upload', $this->sr))->assertForbidden();
        $u->post(route('admin.quotes.store-upload', $this->sr), [])->assertForbidden();

        $this->assertSame('draft', $this->draft->fresh()->status);
        $this->assertSame('sent', $this->sent->fresh()->status);
    }

    #[Test]
    public function their_quote_page_has_no_edit_send_resend_revise_or_start_invoice_button(): void
    {
        $this->actingAs($this->ops)->get(route('admin.quotes.show', $this->draft))
            ->assertDontSee(route('admin.quotes.edit', $this->draft), false)->assertDontSee('onclick="openSendSheet()"', false);
        $this->actingAs($this->ops)->get(route('admin.quotes.show', $this->sent))
            ->assertDontSee('Revise')->assertDontSee('Resend')->assertDontSee(route('admin.quotes.revise', $this->sent), false);
        $this->actingAs($this->ops)->get(route('admin.quotes.show', $this->accepted))->assertOk()->assertDontSee('Start invoice from this quote');
    }

    #[Test]
    public function finance_still_sees_every_button(): void
    {
        $this->actingAs($this->finance)->get(route('admin.quotes.show', $this->draft))
            ->assertSee(route('admin.quotes.edit', $this->draft), false)->assertSee('onclick="openSendSheet()"', false);
        $this->actingAs($this->finance)->get(route('admin.quotes.show', $this->sent))->assertSee('Revise')->assertSee('Resend');
        $this->actingAs($this->finance)->get(route('admin.quotes.show', $this->accepted))->assertSee('Start invoice from this quote');
    }

    #[Test]
    public function a_draft_row_opens_the_page_for_them_and_the_builder_for_finance(): void
    {
        $this->actingAs($this->ops)->get(route('admin.quotes.index', ['tab' => 'draft']))
            ->assertSee("window.location='" . route('admin.quotes.show', $this->draft) . "'", false)
            ->assertDontSee(route('admin.quotes.edit', $this->draft), false);
        $this->actingAs($this->finance)->get(route('admin.quotes.index', ['tab' => 'draft']))
            ->assertSee("window.location='" . route('admin.quotes.edit', $this->draft) . "'", false);
    }

    #[Test]
    public function roles_with_no_quote_access_never_see_a_page(): void
    {
        $this->actingAs($this->makeUser($this->tenant, 'front_desk'))->get(route('admin.quotes.index'))->assertForbidden();
        $this->actingAs($this->makeUser($this->tenant, 'client_user'))->get(route('admin.quotes.index'))->assertForbidden();
        $this->actingAs($this->makeUser($this->tenant, 'client_user', 'c2@vq.test'))->get(route('admin.quotes.show', $this->sent))->assertForbidden();
        $this->actingAs($this->makeUser($this->tenant, 'field_technician'))->get(route('admin.quotes.index'))->assertRedirect(route('technician.jobs.index'));
    }

    #[Test]
    public function the_sidebar_shows_a_quotes_link_to_the_operations_manager_but_not_to_front_desk(): void
    {
        $link = '/admin/quotes"'; // the link's path; the host is the company's own address
        $this->actingAs($this->ops)->get($this->onHost($this->tenant, '/dashboard'))->assertOk()->assertSee($link, false);
        $this->actingAs($this->makeUser($this->tenant, 'front_desk'))->get($this->onHost($this->tenant, '/dashboard'))->assertOk()->assertDontSee($link, false);
        $this->actingAs($this->finance)->get($this->onHost($this->tenant, '/dashboard'))->assertSee($link, false); // Finance: the Money group
    }

    #[Test]
    public function the_request_pages_do_not_offer_build_quote_to_a_view_only_user(): void
    {
        $admin = $this->makeUser($this->tenant, 'company_admin');
        $this->sr->forceFill(['status' => 'quoted'])->save();
        $build = route('admin.quotes.build', $this->sr);

        $this->actingAs($admin)->get(route('service-requests.show', $this->sr))->assertOk()->assertSee($build, false);
        $this->actingAs($this->ops)->get(route('service-requests.show', $this->sr))->assertOk()->assertDontSee($build, false)->assertDontSee(route('admin.quotes.upload', $this->sr), false);
    }

    #[Test]
    public function the_quality_check_page_opens_the_quote_page_not_the_builder_for_a_view_only_user(): void
    {
        $this->sr->forceFill(['status' => 'pending', 'billing_status' => 'pending_quote'])->save();

        $this->actingAs($this->ops)->get(route('admin.requests-review.show', $this->sr))->assertOk()
            ->assertSee(route('admin.quotes.show', $this->draft), false)->assertDontSee(route('admin.quotes.edit', $this->draft), false)->assertDontSee('Make quote');
        $this->actingAs($this->makeUser($this->tenant, 'company_admin'))->get(route('admin.requests-review.show', $this->sr))->assertSee(route('admin.quotes.edit', $this->draft), false);
    }
}
