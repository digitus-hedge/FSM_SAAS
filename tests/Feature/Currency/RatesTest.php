<?php

namespace Tests\Feature\Currency;

use App\Models\ExchangeRate;
use App\Models\TenantCurrency;
use App\Services\Currency\CurrencyService;
use App\Services\Currency\FakeRateProvider;
use App\Services\Currency\RateFetcher;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/** Section 10.1: daily rates (CUR-01), CP-001 to CP-010. */
class RatesTest extends BillingTestCase
{
    private function turnOn($tenant, string ...$codes): void
    {
        foreach ($codes as $c) {
            TenantCurrency::create(['tenant_id' => $tenant->id, 'currency_code' => $c, 'is_active' => true]);
        }
    }

    private function run_(string $at = '2026-11-19 10:00:00', $tenant = null): array
    {
        Carbon::setTestNow(Carbon::parse($at, 'UTC'));

        return app(RateFetcher::class)->runForTenant($tenant);
    }

    #[Test]
    public function CP_001_company_with_base_aed_and_usd_gets_one_provider_rate_for_today(): void
    {
        $t = $this->makeTenant();
        $this->turnOn($t, 'USD');

        $r = $this->run_(tenant: $t);

        $this->assertSame(1, $r['stored']);
        $row = ExchangeRate::where('tenant_id', $t->id)->sole();
        $this->assertSame('USD', $row->from_currency);
        $this->assertSame('AED', $row->to_currency);
        $this->assertSame('provider', $row->source);
        $this->assertSame('2026-11-19', $row->rate_date->toDateString());
    }

    #[Test]
    public function CP_002_running_twice_the_same_day_leaves_one_row(): void
    {
        $t = $this->makeTenant();
        $this->turnOn($t, 'USD');

        $this->run_(tenant: $t);
        $this->run_(tenant: $t);

        $this->assertSame(1, ExchangeRate::where('tenant_id', $t->id)->count());
    }

    #[Test]
    public function CP_003_a_manual_rate_for_today_is_not_replaced_by_the_provider(): void
    {
        $t = $this->makeTenant();
        $this->turnOn($t, 'USD');
        $this->setRate($t, 'USD', '2026-11-19', '3.70000000', 'manual');

        $this->run_(tenant: $t);

        $row = ExchangeRate::where('tenant_id', $t->id)->sole();
        $this->assertSame('manual', $row->source);
        $this->assertSame('3.70000000', (string) $row->rate);
    }

    #[Test]
    public function CP_004_provider_down_all_day_keeps_the_latest_stored_rate_in_use(): void
    {
        $t = $this->makeTenant();
        $this->turnOn($t, 'USD');
        $this->setRate($t, 'USD', '2026-11-17', '3.67000000');
        FakeRateProvider::$fails = true;

        $r = $this->run_(tenant: $t);

        $this->assertSame(1, $r['failed']);
        $this->assertSame(1, ExchangeRate::where('tenant_id', $t->id)->count());
        $latest = app(CurrencyService::class)->latestRate($t, 'USD');
        $this->assertSame('2026-11-17', $latest->rate_date->toDateString()); // shown as "Rate from 17 Nov 2026"
    }

    #[Test]
    public function CP_004b_a_later_retry_stores_the_rate_once_the_provider_is_back(): void
    {
        $t = $this->makeTenant();
        $this->turnOn($t, 'USD');
        FakeRateProvider::$fails = true;
        $this->run_(tenant: $t);

        FakeRateProvider::$fails = false;
        $r = $this->run_('2026-11-19 18:00:00', $t);

        $this->assertSame(1, $r['stored']);
    }

    #[Test]
    public function CP_005_no_rate_stored_at_all_means_no_latest_rate(): void
    {
        $t = $this->makeTenant();
        $this->turnOn($t, 'USD');
        FakeRateProvider::$fails = true;
        $this->run_(tenant: $t);

        $this->assertNull(app(CurrencyService::class)->latestRate($t, 'USD')); // the document's rate field stays empty and required
    }

    #[Test]
    public function CP_006_company_with_no_turned_on_currencies_is_skipped(): void
    {
        $t = $this->makeTenant();

        $r = $this->run_(tenant: $t);

        $this->assertSame(['stored' => 0, 'skipped' => 0, 'failed' => 0], $r);
        $this->assertSame(0, FakeRateProvider::$calls);
    }

    #[Test]
    public function CP_007_rate_date_is_the_companys_date_near_midnight(): void
    {
        $t = $this->makeTenant(tz: 'Asia/Dubai'); // UTC+4
        $this->turnOn($t, 'USD');

        $this->run_('2026-11-19 22:30:00', $t); // 02:30 on the 20th in Dubai

        $this->assertSame('2026-11-20', ExchangeRate::where('tenant_id', $t->id)->sole()->rate_date->toDateString());
    }

    #[Test]
    public function CP_008_two_companies_with_the_same_pair_each_get_their_own_row(): void
    {
        $a = $this->makeTenant(slug: 'a1');
        $b = $this->makeTenant(slug: 'b1');
        $this->turnOn($a, 'USD');
        $this->turnOn($b, 'USD');

        $this->run_(tenant: $a);
        $this->run_(tenant: $b);

        $this->assertSame(1, ExchangeRate::where('tenant_id', $a->id)->count());
        $this->assertSame(1, ExchangeRate::where('tenant_id', $b->id)->count());
    }

    #[Test]
    public function CP_009_a_rate_of_zero_or_below_from_the_provider_is_not_stored(): void
    {
        $t = $this->makeTenant();
        $this->turnOn($t, 'USD', 'EUR');
        FakeRateProvider::$rates = ['USD' => '0', 'EUR' => '-2'];

        $r = $this->run_(tenant: $t);

        $this->assertSame(0, ExchangeRate::where('tenant_id', $t->id)->count());
        $this->assertSame(2, $r['failed']);
    }

    #[Test]
    public function CP_010_rates_are_stored_with_8_decimals(): void
    {
        $t = $this->makeTenant();
        $this->turnOn($t, 'USD');
        FakeRateProvider::$rates = ['USD' => '3.6725'];

        $this->run_(tenant: $t);

        $this->assertSame('3.67250000', \DB::table('exchange_rates')->where('tenant_id', $t->id)->value('rate'));
    }

    #[Test]
    public function the_command_runs_for_plans_with_multi_currency_and_skips_others(): void
    {
        $pro = $this->makeTenant(slug: 'pro1');
        $starter = $this->makeTenant($this->starterPlan(), slug: 'st1');
        $this->turnOn($pro, 'USD');
        $this->turnOn($starter, 'USD');
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));

        $this->artisan('rates:fetch')->assertSuccessful();

        $this->assertSame(1, ExchangeRate::where('tenant_id', $pro->id)->count());
        $this->assertSame(0, ExchangeRate::where('tenant_id', $starter->id)->count());
    }
}
