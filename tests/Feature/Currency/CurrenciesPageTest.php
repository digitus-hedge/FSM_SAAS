<?php

namespace Tests\Feature\Currency;

use App\Models\ActivityLog;
use App\Models\ExchangeRate;
use App\Models\TenantCurrency;
use App\Services\Currency\CurrencyException;
use App\Services\Currency\CurrencyService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/** Section 10.2: currencies page (CUR-02), CP-011 to CP-018, plus the audit actions of section 7. */
class CurrenciesPageTest extends BillingTestCase
{
    #[Test]
    public function CP_011_turn_on_usd_and_it_appears_with_its_rate(): void
    {
        $t = $this->makeTenant();
        $finance = $this->makeUser($t, 'finance');

        $this->actingAs($finance)->post(route('admin.currencies.store'), ['currency_code' => 'usd'])->assertRedirect(route('admin.currencies.index'));

        $this->assertTrue(TenantCurrency::where('tenant_id', $t->id)->where('currency_code', 'USD')->where('is_active', true)->exists());
        $this->actingAs($finance)->get(route('admin.currencies.index'))
            ->assertOk()->assertSee('USD')->assertSee('1 USD = 3.6725 AED')->assertSee('Base currency, locked');
        $this->assertTrue(ActivityLog::where('action', 'currency.enabled')->where('tenant_id', $t->id)->exists());
    }

    #[Test]
    public function the_page_shows_the_empty_state_with_only_the_base_currency(): void
    {
        $t = $this->makeTenant();

        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.currencies.index'))
            ->assertOk()->assertSee('Only AED is turned on. Add a currency to quote and invoice in it.');
    }

    #[Test]
    public function an_old_rate_is_shown_with_its_date(): void
    {
        Carbon::setTestNow('2026-11-19 10:00:00');
        $t = $this->makeTenant();
        TenantCurrency::create(['tenant_id' => $t->id, 'currency_code' => 'USD', 'is_active' => true]);
        $this->setRate($t, 'USD', '2026-11-17', '3.67000000');

        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.currencies.index'))->assertSee('Rate from 17 Nov 2026');
    }

    #[Test]
    public function CP_012_turning_on_the_base_currency_is_refused(): void
    {
        $t = $this->makeTenant();

        $this->actingAs($this->makeUser($t, 'finance'))->post(route('admin.currencies.store'), ['currency_code' => 'AED'])->assertSessionHasErrors('currency_code');

        $this->assertSame(0, TenantCurrency::where('tenant_id', $t->id)->count());
    }

    #[Test]
    public function CP_013_turning_off_usd_used_on_a_sent_invoice_gives_currency_in_use(): void
    {
        $t = $this->makeTenant();
        TenantCurrency::create(['tenant_id' => $t->id, 'currency_code' => 'USD', 'is_active' => true]);
        $this->makeInvoice($t, $this->makeClient($t), ['currency_code' => 'USD', 'exchange_rate' => 3.6725, 'status' => 'sent']);

        try {
            app(CurrencyService::class)->disable($t, 'USD');
            $this->fail('Expected CURRENCY_IN_USE');
        } catch (CurrencyException $e) {
            $this->assertSame('CURRENCY_IN_USE', $e->errorCode);
            $this->assertSame('USD is used on open documents and cannot be turned off.', $e->getMessage());
        }

        $this->actingAs($this->makeUser($t, 'finance'))->delete(route('admin.currencies.destroy', 'USD'))
            ->assertSessionHasErrors(['currency_code' => 'USD is used on open documents and cannot be turned off.']);
        $this->assertTrue(TenantCurrency::where('currency_code', 'USD')->value('is_active') == true);
    }

    #[Test]
    public function CP_014_turning_off_usd_with_no_open_document_keeps_its_history(): void
    {
        $t = $this->makeTenant();
        TenantCurrency::create(['tenant_id' => $t->id, 'currency_code' => 'USD', 'is_active' => true]);
        $this->setRate($t, 'USD', '2026-11-17', '3.67000000');
        $this->makeInvoice($t, $this->makeClient($t), ['currency_code' => 'USD', 'exchange_rate' => 3.6725, 'status' => 'paid', 'balance' => 0]);

        $this->actingAs($this->makeUser($t, 'finance'))->delete(route('admin.currencies.destroy', 'USD'))->assertRedirect(route('admin.currencies.index'));

        $this->assertFalse((bool) TenantCurrency::where('currency_code', 'USD')->value('is_active'));
        $this->assertSame(1, ExchangeRate::where('tenant_id', $t->id)->count());
        $this->assertTrue(ActivityLog::where('action', 'currency.disabled')->exists());
    }

    #[Test]
    public function CP_015_a_manual_rate_for_yesterday_replaces_the_provider_rate_and_is_audited(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $t = $this->makeTenant();
        TenantCurrency::create(['tenant_id' => $t->id, 'currency_code' => 'USD', 'is_active' => true]);
        $this->setRate($t, 'USD', '2026-11-18', '3.67000000', 'provider');

        $this->actingAs($this->makeUser($t, 'finance'))
            ->post(route('admin.currencies.rates.store', 'USD'), ['rate_date' => '2026-11-18', 'rate' => '3.7123'])
            ->assertRedirect(route('admin.currencies.history', 'USD'));

        $row = ExchangeRate::where('tenant_id', $t->id)->sole();
        $this->assertSame('manual', $row->source);
        $this->assertSame('3.71230000', (string) $row->rate);
        $log = ActivityLog::where('action', 'rate.manual_added')->sole();
        $this->assertSame('provider', $log->old_values['source']);
        $this->assertSame('manual', $log->new_values['source']);
    }

    #[Test]
    public function CP_016_a_manual_rate_of_zero_and_one_for_tomorrow_are_refused_with_their_messages(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $t = $this->makeTenant();
        TenantCurrency::create(['tenant_id' => $t->id, 'currency_code' => 'USD', 'is_active' => true]);
        $finance = $this->makeUser($t, 'finance');

        $this->actingAs($finance)->post(route('admin.currencies.rates.store', 'USD'), ['rate_date' => '2026-11-19', 'rate' => '0'])
            ->assertSessionHasErrors(['rate' => 'Enter a rate above 0.']);
        $this->actingAs($finance)->post(route('admin.currencies.rates.store', 'USD'), ['rate_date' => '2026-11-21', 'rate' => '3.6'])
            ->assertSessionHasErrors(['rate_date' => 'Choose today or an earlier date.']);

        $this->assertSame(0, ExchangeRate::count());
    }

    #[Test]
    public function CP_017_starter_gets_the_plan_page(): void
    {
        $t = $this->makeTenant($this->starterPlan());

        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.currencies.index'))
            ->assertStatus(403)->assertSee('This is not part of your plan');
    }

    #[Test]
    public function CP_018_a_front_desk_user_gets_not_found(): void
    {
        $t = $this->makeTenant();
        $desk = $this->makeUser($t, 'front_desk');

        $this->actingAs($desk)->get(route('admin.currencies.index'))->assertNotFound();
        $this->actingAs($desk)->post(route('admin.currencies.store'), ['currency_code' => 'USD'])->assertNotFound();
    }

    #[Test]
    public function an_operations_manager_with_settings_access_can_manage_but_a_view_only_holder_cannot_change(): void
    {
        $t = $this->makeTenant();
        $ops = $this->makeUser($t, 'operations_manager'); // settings.manage is full for this role
        $this->actingAs($ops)->post(route('admin.currencies.store'), ['currency_code' => 'USD'])->assertRedirect();

        // Remove the settings.manage right: invoice.manage is only view_only for this role, so changing is refused.
        \App\Models\UserPermissionOverride::create(['tenant_id' => $t->id, 'user_id' => $ops->id, 'permission_id' => \App\Models\Permission::where('key', 'settings.manage')->value('id'), 'level' => 'none']);
        $this->actingAs($ops)->get(route('admin.currencies.index'))->assertOk();
        $this->actingAs($ops)->post(route('admin.currencies.store'), ['currency_code' => 'EUR'])->assertForbidden();
        $this->actingAs($ops)->delete(route('admin.currencies.destroy', 'USD'))->assertForbidden();
    }

    #[Test]
    public function the_history_page_is_isolated_between_companies(): void
    {
        $a = $this->makeTenant(slug: 'ca');
        $b = $this->makeTenant(slug: 'cb');
        TenantCurrency::create(['tenant_id' => $b->id, 'currency_code' => 'USD', 'is_active' => true]);

        $this->actingAs($this->makeUser($a, 'finance'))->get(route('admin.currencies.history', 'USD'))->assertNotFound();
    }
}
