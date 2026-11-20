<?php

namespace Tests\Feature\Contracts;

use App\Models\Asset;
use App\Models\Contract;

/**
 * C3: the Default contract field on the asset form (Contracts spec 5.6, CNT-05, CC-053 to CC-059).
 * The list holds Active and Expiring soon contracts that are in date; the browser narrows it to the chosen client and site.
 * A pin is accepted when the contract is the client's, valid today, and covers the asset's site.
 */
class ContractPinFieldTest extends ContractsTestCase
{
    protected function payload(array $over = []): array
    {
        return array_merge([
            'client_id' => $this->client->id, 'project_site_id' => $this->site->id, 'service_category_id' => $this->makeCategory()->id,
            'name' => 'Chiller', 'status' => 'active',
        ], $over);
    }

    protected function create(array $over = [])
    {
        return $this->as('company_admin')->post($this->onHost($this->t, '/admin/assets'), $this->payload($over));
    }

    protected function form(?Asset $asset = null): string
    {
        $path = $asset ? '/admin/assets/' . $asset->id . '/edit' : '/admin/assets/create';

        return $this->as('company_admin')->get($this->onHost($this->t, $path))->assertOk()->getContent();
    }

    /* ---------- the list on the form (CC-058) ---------- */

    public function test_the_field_lists_active_and_expiring_soon_contracts_that_are_in_date(): void
    {
        $active   = $this->contract();
        $expiring = $this->contract(['status' => 'expiring_soon', 'end_date' => '2026-12-15']);
        $draft    = $this->contract(['status' => 'draft']);
        $cancelled = $this->contract(['status' => 'cancelled']);
        $expired  = $this->contract(['status' => 'active', 'end_date' => '2026-10-31']);
        $future   = $this->contract(['status' => 'active', 'start_date' => '2026-12-20', 'end_date' => '2027-12-19']);

        $html = $this->form();

        $this->assertStringContainsString($active->contract_number, $html);
        $this->assertStringContainsString($expiring->contract_number, $html);
        foreach ([$draft, $cancelled, $expired, $future] as $c) {
            $this->assertStringNotContainsString($c->contract_number, $html, $c->status . ' contract listed');
        }
    }

    public function test_each_option_carries_its_client_and_its_sites_for_the_browser_to_narrow_the_list(): void
    {
        $second = $this->otherSite();
        $all  = $this->contract();                                         // no sites: covers every site of the client
        $only = $this->contract([], [$second->id]);

        $html = $this->form();

        $this->assertMatchesRegularExpression('/<option value="' . $all->id . '" data-client="' . $this->client->id . '" data-sites=""/', $html);
        $this->assertMatchesRegularExpression('/<option value="' . $only->id . '" data-client="' . $this->client->id . '" data-sites="' . $second->id . '"/', $html);
    }

    public function test_the_note_under_the_field(): void
    {
        $this->contract();

        $this->assertStringContainsString('Used without asking while it is valid and covers the request.', $this->form());
    }

    public function test_the_field_is_not_shown_without_the_contracts_feature(): void
    {
        $plan = $this->t->plan;
        $plan->features = array_merge((array) $plan->features, ['contracts' => false]);
        $plan->save();

        $this->assertStringNotContainsString('default_contract_id', $this->form());
    }

    /* ---------- saving a pin (CC-056) ---------- */

    public function test_a_valid_contract_can_be_pinned(): void
    {
        $c = $this->contract([], [$this->site->id]);

        $this->create(['default_contract_id' => $c->id])->assertSessionHasNoErrors();

        $this->assertSame($c->id, Asset::withoutGlobalScopes()->where('tenant_id', $this->t->id)->where('name', 'Chiller')->value('default_contract_id'));
    }

    public function test_a_contract_that_covers_every_site_can_be_pinned(): void
    {
        $c = $this->contract();

        $this->create(['default_contract_id' => $c->id])->assertSessionHasNoErrors();
    }

    public function test_an_expiring_soon_contract_can_be_pinned(): void
    {
        $c = $this->contract(['status' => 'expiring_soon', 'end_date' => '2026-12-15']);

        $this->create(['default_contract_id' => $c->id])->assertSessionHasNoErrors();
    }

    /** @dataProvider invalidPins */
    public function test_a_contract_that_is_not_valid_for_the_asset_cannot_be_pinned(string $kind): void
    {
        $second = $this->otherSite();
        $c = match ($kind) {
            'draft'      => $this->contract(['status' => 'draft']),
            'cancelled'  => $this->contract(['status' => 'cancelled']),
            'expired'    => $this->contract(['end_date' => '2026-10-31']),
            'not_started' => $this->contract(['start_date' => '2026-12-20', 'end_date' => '2027-12-19']),
            'other_site' => $this->contract([], [$second->id]),
            'other_client' => $this->contract(['client_id' => $this->makeClient($this->t, 'Another client')->id]),
        };

        $this->create(['default_contract_id' => $c->id])->assertSessionHasErrors('default_contract_id');

        $this->assertSame(0, Asset::withoutGlobalScopes()->where('tenant_id', $this->t->id)->where('name', 'Chiller')->count());
    }

    public static function invalidPins(): array
    {
        return [['draft'], ['cancelled'], ['expired'], ['not_started'], ['other_site'], ['other_client']];
    }

    public function test_another_companys_contract_cannot_be_pinned(): void
    {
        $other = $this->makeTenant(slug: 'other');
        $theirs = Contract::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'client_id' => $this->makeClient($other, 'Theirs')->id, 'contract_type' => 'amc', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $this->create(['default_contract_id' => $theirs->id])->assertSessionHasErrors('default_contract_id');
    }

    /* ---------- a pin that stops being valid (CC-059) ---------- */

    public function test_a_pinned_contract_that_stopped_being_valid_shows_the_message_and_stays_visible(): void
    {
        $c = $this->contract();
        $asset = $this->makeAsset(null, ['default_contract_id' => $c->id]);
        $this->assertStringNotContainsString('The pinned contract is no longer valid.', $this->form($asset));

        $c->forceFill(['status' => 'cancelled'])->save();

        $html = $this->form($asset);
        $this->assertStringContainsString('The pinned contract is no longer valid.', $html);
        $this->assertStringContainsString($c->contract_number, $html);        // the old pin is still shown, so it is not dropped by accident
    }

    public function test_a_pin_on_a_site_the_contract_no_longer_covers_is_no_longer_valid(): void
    {
        $second = $this->otherSite();
        $c = $this->contract([], [$second->id]);
        $asset = $this->makeAsset(null, ['default_contract_id' => $c->id]);        // the asset is at the first site

        $this->assertStringContainsString('The pinned contract is no longer valid.', $this->form($asset));
    }

    public function test_saving_other_changes_keeps_an_old_pin_that_is_no_longer_valid(): void
    {
        $c = $this->contract();
        $asset = $this->makeAsset(null, ['default_contract_id' => $c->id]);
        $c->forceFill(['status' => 'cancelled'])->save();

        $this->as('company_admin')->put($this->onHost($this->t, '/admin/assets/' . $asset->id), $this->payload([
            'service_category_id' => $asset->service_category_id, 'name' => 'Renamed', 'default_contract_id' => $c->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $asset->fresh()->name);
        $this->assertSame($c->id, $asset->fresh()->default_contract_id);
    }

    public function test_the_pin_can_be_cleared(): void
    {
        $c = $this->contract();
        $asset = $this->makeAsset(null, ['default_contract_id' => $c->id]);

        $this->as('company_admin')->put($this->onHost($this->t, '/admin/assets/' . $asset->id), $this->payload([
            'service_category_id' => $asset->service_category_id, 'name' => $asset->name, 'default_contract_id' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($asset->fresh()->default_contract_id);
    }
}
