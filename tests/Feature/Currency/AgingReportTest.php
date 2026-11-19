<?php

namespace Tests\Feature\Currency;

use App\Models\ActivityLog;
use App\Services\Billing\AgingReport;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/** Section 10.7: aging report (LED-02), CP-061 to CP-070. Today is fixed at 19 Nov 2026. */
class AgingReportTest extends BillingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
    }

    /** An open invoice that is $days past its due date (negative = not yet due). */
    private function open($t, $client, int $days, array $extra = [])
    {
        return $this->makeInvoice($t, $client, array_merge([
            'due_date' => Carbon::parse('2026-11-19')->subDays($days)->toDateString(),
            'status'   => $days > 0 ? 'overdue' : 'sent',
            'total'    => 100, 'balance' => 100,
        ], $extra));
    }

    private function report($t, ?int $clientId = null): array
    {
        return app(AgingReport::class)->report($t, $clientId);
    }

    #[Test]
    public function CP_061_one_invoice_in_each_band(): void
    {
        $t = $this->makeTenant();
        $c = $this->makeClient($t);
        foreach ([-5, 10, 45, 75, 120] as $d) {
            $this->open($t, $c, $d);
        }

        $row = $this->report($t)['rows'][0];

        $this->assertSame(['current' => '100.00', 'd1_30' => '100.00', 'd31_60' => '100.00', 'd61_90' => '100.00', 'over90' => '100.00'], $row['bands']);
        $this->assertSame('500.00', $row['total']);
    }

    #[Test]
    public function CP_062_exactly_30_60_and_90_days_fall_in_the_lower_band(): void
    {
        $this->assertSame('d1_30', AgingReport::bandFor(30));
        $this->assertSame('d31_60', AgingReport::bandFor(31));
        $this->assertSame('d31_60', AgingReport::bandFor(60));
        $this->assertSame('d61_90', AgingReport::bandFor(61));
        $this->assertSame('d61_90', AgingReport::bandFor(90));
        $this->assertSame('over90', AgingReport::bandFor(91));
        $this->assertSame('current', AgingReport::bandFor(0));

        $t = $this->makeTenant();
        $c = $this->makeClient($t);
        foreach ([30, 60, 90] as $d) {
            $this->open($t, $c, $d);
        }
        $b = $this->report($t)['rows'][0]['bands'];
        $this->assertSame(['100.00', '100.00', '100.00'], [$b['d1_30'], $b['d31_60'], $b['d61_90']]);
    }

    #[Test]
    public function CP_063_paid_void_and_written_off_invoices_are_not_counted(): void
    {
        $t = $this->makeTenant();
        $c = $this->makeClient($t);
        $this->open($t, $c, 10, ['status' => 'paid', 'balance' => 0]);
        $this->open($t, $c, 10, ['status' => 'void']);
        $this->open($t, $c, 10, ['status' => 'written_off', 'balance' => 0]);
        $this->open($t, $c, 10, ['status' => 'draft']);

        $this->assertSame([], $this->report($t)['rows']);
    }

    #[Test]
    public function CP_064_a_part_paid_invoice_counts_its_balance(): void
    {
        $t = $this->makeTenant();
        $c = $this->makeClient($t);
        $this->open($t, $c, 10, ['status' => 'partially_paid', 'total' => 100, 'balance' => 40]);

        $this->assertSame('40.00', $this->report($t)['rows'][0]['bands']['d1_30']);
    }

    #[Test]
    public function CP_065_a_usd_invoice_is_converted_at_its_own_rate(): void
    {
        $t = $this->makeTenant();
        $c = $this->makeClient($t);
        $this->open($t, $c, 10, ['currency_code' => 'USD', 'exchange_rate' => '3.6725', 'total' => 1000, 'balance' => 1000]);

        $this->assertSame('3672.50', $this->report($t)['rows'][0]['bands']['d1_30']);
    }

    #[Test]
    public function CP_066_the_total_row_adds_the_bands_across_clients(): void
    {
        $t = $this->makeTenant();
        $a = $this->makeClient($t, 'Alpha');
        $b = $this->makeClient($t, 'Beta');
        $this->open($t, $a, -2);
        $this->open($t, $a, 100);
        $this->open($t, $b, 10);
        $this->open($t, $b, 10, ['currency_code' => 'USD', 'exchange_rate' => '2', 'total' => 50, 'balance' => 50]);

        $r = $this->report($t);

        $this->assertSame(['Alpha', 'Beta'], array_column($r['rows'], 'client'));
        $this->assertSame('100.00', $r['totals']['current']);
        $this->assertSame('200.00', $r['totals']['d1_30']); // Beta: 100 + 50 USD x 2
        $this->assertSame('100.00', $r['totals']['over90']);
        $this->assertSame('400.00', $r['totals']['total']);
        $this->assertSame('400.00', number_format(array_sum(array_map('floatval', array_diff_key($r['totals'], ['total' => 1]))), 2, '.', ''));
    }

    #[Test]
    public function CP_067_a_cell_opens_its_invoices_with_both_amounts(): void
    {
        $t = $this->makeTenant();
        $c = $this->makeClient($t, 'Alpha');
        $inv = $this->open($t, $c, 10, ['currency_code' => 'USD', 'exchange_rate' => '3.6725', 'total' => 1000, 'balance' => 1000]);
        $this->open($t, $c, 100); // another band
        $finance = $this->makeUser($t, 'finance');

        $res = $this->actingAs($finance)->get(route('admin.aging.invoices', ['band' => 'd1_30', 'client_id' => $c->id]))->assertOk();

        $res->assertSee($inv->invoice_number)->assertSee('USD 1,000.00')->assertSee('3,672.50')->assertSee('10');
        $this->assertSame(1, substr_count($res->getContent(), 'INV-T-'));
    }

    #[Test]
    public function CP_068_days_are_counted_in_the_companys_time_zone(): void
    {
        // 19 Nov 21:30 UTC is already 20 Nov in Dubai (UTC+4): an invoice due on the 19th is 1 day overdue there, 0 in London.
        Carbon::setTestNow(Carbon::parse('2026-11-19 21:30:00', 'UTC'));
        $dubai  = $this->makeTenant(tz: 'Asia/Dubai', slug: 'du');
        $london = $this->makeTenant(tz: 'Europe/London', slug: 'lo');
        foreach ([$dubai, $london] as $t) {
            $this->makeInvoice($t, $this->makeClient($t), ['due_date' => '2026-11-19', 'status' => 'sent', 'total' => 100, 'balance' => 100]);
        }

        $this->assertSame('100.00', $this->report($dubai)['rows'][0]['bands']['d1_30']);
        $this->assertSame('100.00', $this->report($london)['rows'][0]['bands']['current']);
    }

    #[Test]
    public function an_invoice_with_no_due_date_counts_as_current(): void
    {
        $t = $this->makeTenant();
        $this->makeInvoice($t, $this->makeClient($t), ['due_date' => null, 'status' => 'sent', 'total' => 100, 'balance' => 100]);

        $this->assertSame('100.00', $this->report($t)['rows'][0]['bands']['current']);
    }

    #[Test]
    public function CP_069_nothing_unpaid_shows_the_empty_message(): void
    {
        $t = $this->makeTenant();

        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.aging.index'))->assertOk()->assertSee('No unpaid invoices.')->assertSee('As of 19 Nov 2026');
    }

    #[Test]
    public function the_page_shows_the_figures_and_the_converted_note(): void
    {
        $t = $this->makeTenant();
        $this->open($t, $this->makeClient($t, 'Alpha'), 10, ['total' => 250, 'balance' => 250]);

        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.aging.index'))->assertSee('Alpha')->assertSee('250.00')->assertSee("Converted at each document's rate.", false);
    }

    #[Test]
    public function CP_070_export_has_the_same_figures_and_is_recorded(): void
    {
        $t = $this->makeTenant();
        $this->open($t, $this->makeClient($t, 'Alpha'), 10, ['total' => 250, 'balance' => 250]);

        $res = $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.aging.export'))->assertOk();

        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($res->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Client', $sheet->getCell('A1')->getValue());
        $this->assertSame('Alpha', $sheet->getCell('A2')->getValue());
        $this->assertEquals(250, $sheet->getCell('C2')->getValue()); // 1 to 30
        $this->assertSame('Total', $sheet->getCell('A3')->getValue());
        $this->assertEquals(250, $sheet->getCell('G3')->getValue());
        $this->assertSame(1, ActivityLog::where('action', 'aging.exported')->where('tenant_id', $t->id)->count());
    }

    #[Test]
    public function the_report_is_per_company_and_the_roles_match_the_spec(): void
    {
        $a = $this->makeTenant(slug: 'aa');
        $b = $this->makeTenant(slug: 'ab');
        $this->open($b, $this->makeClient($b, 'Other Co'), 10);

        $this->actingAs($this->makeUser($a, 'finance'))->get(route('admin.aging.index'))->assertDontSee('Other Co');
        $this->actingAs($this->makeUser($a, 'operations_manager'))->get(route('admin.aging.index'))->assertOk();
        $this->actingAs($this->makeUser($a, 'front_desk'))->get(route('admin.aging.index'))->assertNotFound();

        $s = $this->makeTenant($this->starterPlan(), slug: 'as');
        $this->actingAs($this->makeUser($s, 'finance'))->get(route('admin.aging.index'))->assertStatus(403);
    }
}
