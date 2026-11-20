<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Role;
use App\Models\User;

/**
 * Contracts spec 5.7, D16, CC-117 and CC-118: the read-only contracts page of the portal, for a Client Admin only.
 * A client sees its own company's contracts with the allowed fields and never the value, the payment terms, the notes or the documents.
 */
class ClientContractsPageTest extends ContractsTestCase
{
    protected User $admin;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['client_admin' => 'Client Admin', 'client_viewer' => 'Client Viewer'] as $code => $name) {
            Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => $code], ['name' => $name, 'is_standard' => false]);
        }
        $this->admin  = $this->makeUser($this->t, 'client_admin', 'admin@client.test');
        $this->viewer = $this->makeUser($this->t, 'client_viewer', 'viewer@client.test');
        foreach ([$this->admin, $this->viewer] as $u) {
            $u->forceFill(['client_id' => $this->client->id])->save();
        }
    }

    protected function page(User $who, string $path = '/portal/contracts')
    {
        return $this->actingAs($who->fresh())->get($this->onHost($this->t, $path));
    }

    protected function secretContract(array $over = []): Contract
    {
        return $this->contract(array_merge(['annual_value' => '98765.432', 'payment_terms' => 'Net 45 secret terms', 'notes' => 'Internal note about margin', 'max_calls' => 12, 'call_period' => 'annual', 'calls_used_current_period' => 5, 'response_sla_minutes' => 120, 'resolution_sla_minutes' => 1440], $over));
    }

    public function test_a_client_admin_sees_the_contracts_of_their_own_company(): void
    {
        $a = $this->secretContract();
        $b = $this->contract(['contract_type' => 'warranty']);

        $html = $this->page($this->admin)->assertOk()->getContent();

        $this->assertStringContainsString($a->contract_number, $html);
        $this->assertStringContainsString($b->contract_number, $html);
        $this->assertStringContainsString('5 of 12 calls used', $html);
    }

    public function test_the_list_never_shows_the_value_the_terms_or_the_notes(): void
    {
        $this->secretContract();

        $html = $this->page($this->admin)->assertOk()->getContent();

        foreach (['98765', '98,765', 'Net 45 secret terms', 'Internal note about margin'] as $secret) {
            $this->assertStringNotContainsString($secret, $html, $secret);
        }
    }

    public function test_the_detail_page_has_the_allowed_fields_and_nothing_more(): void
    {
        $second = $this->otherSite();
        $c = $this->secretContract();
        $c->sites()->syncWithPivotValues([$this->site->id], ['tenant_id' => $this->t->id]);
        $cat = $this->makeCategory(['name' => 'Fire safety']);
        $this->categoriesOf($c, [$cat->id]);

        $html = $this->page($this->admin, '/portal/contracts/' . $c->id)->assertOk()->getContent();

        foreach ([$c->contract_number, 'AMC', 'Main site', 'Fire safety', '2 hours', '24 hours', 'Calls used', '5 of 12', 'Calls remaining'] as $shown) {
            $this->assertStringContainsString($shown, $html, $shown);
        }
        $this->assertStringContainsString('>7<', $html);                      // 12 - 5 remaining
        $this->assertStringNotContainsString('Second site', $html);
        foreach (['98765', 'Net 45 secret terms', 'Internal note about margin', 'document'] as $secret) {
            $this->assertStringNotContainsString($secret, $html, $secret);
        }
    }

    public function test_a_contract_with_no_sites_or_categories_says_all(): void
    {
        $c = $this->contract();

        $html = $this->page($this->admin, '/portal/contracts/' . $c->id)->assertOk()->getContent();

        $this->assertStringContainsString('All your sites', $html);
        $this->assertStringContainsString('All categories', $html);
    }

    /* ---------- isolation ---------- */

    public function test_another_clients_contract_is_not_listed_and_its_page_is_a_404(): void
    {
        $theirs = $this->contract(['client_id' => $this->makeClient($this->t, 'Other client')->id]);
        $mine = $this->contract();

        $this->assertStringNotContainsString($theirs->contract_number, $this->page($this->admin)->assertOk()->getContent());
        $this->page($this->admin, '/portal/contracts/' . $theirs->id)->assertNotFound();
        $this->page($this->admin, '/portal/contracts/' . $mine->id)->assertOk();
    }

    public function test_another_companys_contract_is_a_404(): void
    {
        $other = $this->makeTenant(slug: 'other');
        $theirs = Contract::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'client_id' => $this->client->id, 'contract_type' => 'amc', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $this->page($this->admin, '/portal/contracts/' . $theirs->id)->assertNotFound();
    }

    public function test_drafts_and_cancelled_contracts_are_not_shown(): void
    {
        $draft = $this->contract(['status' => 'draft']);
        $cancelled = $this->contract(['status' => 'cancelled']);

        $html = $this->page($this->admin)->assertOk()->getContent();

        $this->assertStringNotContainsString($draft->contract_number, $html);
        $this->assertStringNotContainsString($cancelled->contract_number, $html);
        $this->page($this->admin, '/portal/contracts/' . $draft->id)->assertNotFound();
    }

    /* ---------- who may open it ---------- */

    public function test_a_client_viewer_does_not_see_the_page(): void
    {
        $c = $this->contract();

        $this->page($this->viewer)->assertForbidden();
        $this->page($this->viewer, '/portal/contracts/' . $c->id)->assertForbidden();
    }

    public function test_the_page_needs_the_contracts_feature(): void
    {
        $plan = $this->t->plan;
        $plan->features = array_merge((array) $plan->features, ['contracts' => false]);
        $plan->save();

        $this->page($this->admin)->assertForbidden();
    }

    public function test_the_dashboard_links_to_the_page_for_a_client_admin_only(): void
    {
        $this->assertStringContainsString(route('portal.contracts.index', [], false), $this->page($this->admin, '/portal')->assertOk()->getContent());
        $this->assertStringNotContainsString('portal/contracts', $this->page($this->viewer, '/portal')->assertOk()->getContent());
    }

    public function test_staff_cannot_use_the_portal_page(): void
    {
        $this->page($this->u['company_admin'])->assertForbidden();
    }
}
