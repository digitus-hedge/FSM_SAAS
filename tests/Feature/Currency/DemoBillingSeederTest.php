<?php

namespace Tests\Feature\Currency;

use App\Models\Invoice;
use App\Models\InvoiceToken;
use App\Models\Payment;
use App\Services\Billing\AgingReport;
use Carbon\Carbon;
use Database\Seeders\DemoBillingSeeder;
use PHPUnit\Framework\Attributes\Test;

/** The demo seeder for checking B7 Part 1 by hand. */
class DemoBillingSeederTest extends BillingTestCase
{
    private function skyline()
    {
        $t = $this->makeTenant(base: 'INR', tz: 'Asia/Kolkata', slug: 'skyline');
        $this->makeUser($t, 'company_admin');

        return $t;
    }

    #[Test]
    public function it_refuses_to_run_in_production_and_adds_nothing(): void
    {
        $t = $this->skyline();
        $this->app['env'] = 'production';

        try {
            // Called directly: `db:seed` has its own production prompt, this tests the seeder own check.
            (new DemoBillingSeeder())->run();
            $this->fail('Expected the seeder to refuse in production');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('production', $e->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(0, Invoice::where('tenant_id', $t->id)->count());
    }

    #[Test]
    public function it_adds_the_demo_data_once(): void
    {
        $t = $this->skyline();

        $this->seed(DemoBillingSeeder::class);
        $this->seed(DemoBillingSeeder::class); // the second run adds nothing

        $this->assertSame(3, Invoice::where('tenant_id', $t->id)->count());
        $this->assertSame(1, Payment::where('tenant_id', $t->id)->count());
        $this->assertSame(3, InvoiceToken::where('tenant_id', $t->id)->count());
        $usd = Invoice::where('tenant_id', $t->id)->where('currency_code', 'USD')->sole();
        $this->assertSame('600.000', (string) $usd->balance);
        $this->assertSame('partially_paid', $usd->status);
        $p = Payment::where('tenant_id', $t->id)->sole();
        $this->assertSame(['USD', '35400.000'], [$p->currency_code, (string) $p->base_amount]); // 400 x 88.5 INR

        // Aging shows one figure in each of Current, 1 to 30 and 31 to 60.
        $r = app(AgingReport::class)->report($t)['totals'];
        $this->assertSame(['5000.00', '8000.00', '53100.00'], [$r['current'], $r['d1_30'], $r['d31_60']]); // 600 USD x 88.5
    }

    #[Test]
    public function the_overdue_job_then_reminds_the_two_overdue_invoices_once(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $t = $this->skyline();
        $this->seed(DemoBillingSeeder::class);

        Carbon::setTestNow(Carbon::now('Asia/Kolkata')->setTime(9, 0));
        $this->artisan('invoices:overdue-daily', ['--force' => true])->assertSuccessful();
        $this->artisan('invoices:overdue-daily', ['--force' => true])->assertSuccessful();

        $this->assertSame(2, Invoice::where('tenant_id', $t->id)->where('status', 'overdue')->count());
        $this->assertEquals(2, Invoice::where('tenant_id', $t->id)->sum('reminder_count'));
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\InvoiceOverdueMail::class, 2);
    }
}
