<?php

namespace Tests\Feature\Currency;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\UserPermissionOverride;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/** Section 10.4 (CP-037, CP-038, CP-043 hook) and 10.6: payments ledger (LED-01), CP-051 to CP-060. */
class PaymentsLedgerTest extends BillingTestCase
{
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
    }

    private function pay(Invoice $inv, string $amount, string $at = '2026-11-18 09:00:00', array $extra = []): Payment
    {
        return Payment::create(array_merge([
            'tenant_id' => $inv->tenant_id, 'invoice_id' => $inv->id, 'payment_number' => 'PAY-T-' . ++$this->seq,
            'amount' => $amount, 'received_at' => $at, 'method' => 'bank_transfer',
        ], $extra));
    }

    private function usdInvoice($t, $client, string $rate = '3.6725'): Invoice
    {
        return $this->makeInvoice($t, $client, ['currency_code' => 'USD', 'exchange_rate' => $rate, 'total' => 5000, 'balance' => 5000]);
    }

    #[Test]
    public function CP_037_a_payment_gets_the_invoice_currency_and_a_base_amount_at_the_invoice_rate(): void
    {
        $t = $this->makeTenant();
        $p = $this->pay($this->usdInvoice($t, $this->makeClient($t)), '1000');

        $this->assertSame('USD', $p->fresh()->currency_code);
        $this->assertSame('3672.500', (string) $p->fresh()->base_amount);
    }

    #[Test]
    public function CP_038_part_payments_use_the_same_fixed_rate(): void
    {
        $t = $this->makeTenant();
        $inv = $this->usdInvoice($t, $this->makeClient($t));

        $a = $this->pay($inv, '1000');
        $b = $this->pay($inv, '250.50');

        $this->assertSame('3672.500', (string) $a->fresh()->base_amount);
        $this->assertSame('919.960', (string) $b->fresh()->base_amount); // 250.50 x 3.6725 = 919.96125, rounded to 919.96
    }

    #[Test]
    public function the_base_amount_rounds_half_up_to_the_base_currency_decimals(): void
    {
        $kwd = $this->makeTenant(base: 'KWD', slug: 'kw');
        $inv = $this->makeInvoice($kwd, $this->makeClient($kwd), ['currency_code' => 'USD', 'exchange_rate' => '0.30712', 'total' => 100, 'balance' => 100]);

        $this->assertSame('30.712', (string) $this->pay($inv, '100')->fresh()->base_amount); // KWD has 3 decimals

        $jpy = $this->makeTenant(base: 'JPY', slug: 'jp');
        $inv2 = $this->makeInvoice($jpy, $this->makeClient($jpy), ['currency_code' => 'USD', 'exchange_rate' => '150.5', 'total' => 10, 'balance' => 10]);
        $this->assertSame('1505.000', (string) $this->pay($inv2, '10.00')->fresh()->base_amount); // JPY has 0 decimals
    }

    #[Test]
    public function recording_a_payment_from_the_existing_invoice_screen_still_works(): void
    {
        $t = $this->makeTenant();
        $finance = $this->makeUser($t, 'finance');
        $inv = $this->usdInvoice($t, $this->makeClient($t));

        $this->actingAs($finance)->post(route('admin.invoices.payment', $inv), [
            'amount' => '1000', 'received_at' => '2026-11-19', 'method' => 'bank_transfer',
        ])->assertRedirect(route('admin.invoices.show', $inv));

        $p = Payment::where('invoice_id', $inv->id)->sole();
        $this->assertSame('USD', $p->currency_code);
        $this->assertSame('3672.500', (string) $p->base_amount);
        $this->assertSame('4000.000', (string) $inv->fresh()->balance);
        $this->assertSame('partially_paid', $inv->fresh()->status);
    }

    #[Test]
    public function CP_051_the_ledger_opens_on_this_months_payments_newest_first(): void
    {
        $t = $this->makeTenant();
        $inv = $this->makeInvoice($t, $this->makeClient($t));
        $this->pay($inv, '10', '2026-11-02 09:00:00');
        $this->pay($inv, '20', '2026-11-15 09:00:00');
        $this->pay($inv, '30', '2026-10-20 09:00:00'); // last month

        $res = $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.payments.index'))->assertOk();

        $res->assertSeeInOrder(['PAY-T-2', 'PAY-T-1'])->assertDontSee('PAY-T-3');
    }

    #[Test]
    public function CP_052_each_filter_narrows_the_list(): void
    {
        $t = $this->makeTenant();
        $c1 = $this->makeClient($t, 'Alpha Co');
        $c2 = $this->makeClient($t, 'Beta Co');
        $aed = $this->makeInvoice($t, $c1);
        $usd = $this->usdInvoice($t, $c2);
        $this->pay($aed, '10', '2026-11-03 09:00:00', ['method' => 'cash']);          // PAY-T-1
        $this->pay($usd, '20', '2026-11-10 09:00:00', ['method' => 'card']);          // PAY-T-2
        $rev = $this->pay($aed, '30', '2026-11-12 09:00:00');                         // PAY-T-3
        $rev->forceFill(['reversed_at' => now(), 'reversal_reason' => 'Bounced cheque'])->save();
        $user = $this->actingAs($this->makeUser($t, 'finance'));

        $user->get(route('admin.payments.index', ['from' => '2026-11-05', 'to' => '2026-11-30']))->assertSee('PAY-T-2')->assertDontSee('PAY-T-1');
        $user->get(route('admin.payments.index', ['client_id' => $c1->id]))->assertSee('PAY-T-1')->assertDontSee('PAY-T-2');
        $user->get(route('admin.payments.index', ['method' => 'card']))->assertSee('PAY-T-2')->assertDontSee('PAY-T-1');
        $user->get(route('admin.payments.index', ['currency' => 'USD']))->assertSee('PAY-T-2')->assertDontSee('PAY-T-1');
        $user->get(route('admin.payments.index', ['include_reversed' => 1]))->assertSee('PAY-T-3');
    }

    #[Test]
    public function CP_053_the_total_is_in_the_base_currency_without_reversed_payments(): void
    {
        $t = $this->makeTenant();
        $aed = $this->makeInvoice($t, $this->makeClient($t));
        $usd = $this->usdInvoice($t, $this->makeClient($t, 'Beta'));
        $this->pay($aed, '100');                                   // 100.000 AED
        $this->pay($usd, '1000');                                  // 3672.500 AED
        $this->pay($usd, '500')->forceFill(['reversed_at' => now(), 'reversal_reason' => 'Wrong amount'])->save();

        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.payments.index', ['include_reversed' => 1]))
            ->assertSee('AED 3,772.50'); // 100 + 3,672.50, the reversed 1,836.25 left out
    }

    #[Test]
    public function CP_054_a_reversed_payment_is_struck_through_with_its_reason_and_only_shown_with_the_filter(): void
    {
        $t = $this->makeTenant();
        $inv = $this->makeInvoice($t, $this->makeClient($t));
        $this->pay($inv, '30')->forceFill(['reversed_at' => now(), 'reversal_reason' => 'Bounced cheque'])->save();
        $user = $this->actingAs($this->makeUser($t, 'finance'));

        $user->get(route('admin.payments.index'))->assertDontSee('Bounced cheque')->assertSee('No payments match.');
        $user->get(route('admin.payments.index', ['include_reversed' => 1]))->assertSee('Reversed')->assertSee('Reason: Bounced cheque')->assertSee('pl-rev', false);
    }

    #[Test]
    public function CP_055_no_payments_shows_the_empty_message(): void
    {
        $t = $this->makeTenant();

        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.payments.index'))->assertSee('No payments recorded yet.');
    }

    #[Test]
    public function CP_056_export_has_the_same_rows_and_is_recorded(): void
    {
        $t = $this->makeTenant();
        $inv = $this->usdInvoice($t, $this->makeClient($t));
        $this->pay($inv, '1000');

        $res = $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.payments.export'))->assertOk();

        $this->assertStringContainsString('spreadsheetml', $res->headers->get('content-type'));
        $file = $res->baseResponse->getFile()->getPathname();
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
        $this->assertSame('Payment', $sheet->getCell('A1')->getValue());
        $this->assertSame('PAY-T-1', $sheet->getCell('A2')->getValue());
        $this->assertEquals(1000, $sheet->getCell('E2')->getValue());
        $this->assertEquals(3672.5, $sheet->getCell('G2')->getValue());
        $this->assertSame(1, ActivityLog::where('action', 'ledger.exported')->where('tenant_id', $t->id)->count()); // written through AuditLogger
    }

    #[Test]
    public function CP_057_export_without_report_export_is_refused(): void
    {
        $t = $this->makeTenant();
        $finance = $this->makeUser($t, 'finance');
        UserPermissionOverride::create([
            'tenant_id' => $t->id, 'user_id' => $finance->id, 'permission_id' => Permission::where('key', 'report.export')->value('id'), 'level' => 'none',
        ]);

        $this->actingAs($finance)->get(route('admin.payments.index'))->assertOk()->assertDontSee('>Export<', false);
        $this->actingAs($finance)->get(route('admin.payments.export'))->assertForbidden();
        $this->assertSame(0, ActivityLog::where('action', 'ledger.exported')->count());
    }

    #[Test]
    public function CP_058_an_operations_manager_sees_the_ledger_but_cannot_record_a_payment(): void
    {
        $t = $this->makeTenant();
        $ops = $this->makeUser($t, 'operations_manager');
        $inv = $this->makeInvoice($t, $this->makeClient($t));

        $this->assertFalse($ops->canDo('payment.record'));
        $this->actingAs($ops)->get(route('admin.payments.index'))->assertOk();
        $this->actingAs($ops)->post(route('admin.invoices.payment', $inv), ['amount' => 10, 'received_at' => '2026-11-19', 'method' => 'cash'])->assertForbidden();
    }

    #[Test]
    public function CP_059_a_row_opens_the_invoice(): void
    {
        $t = $this->makeTenant();
        $inv = $this->makeInvoice($t, $this->makeClient($t));
        $this->pay($inv, '10');

        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.payments.index'))->assertSee(route('admin.invoices.show', $inv->id), false);
    }

    #[Test]
    public function CP_060_an_export_over_5000_rows_is_refused(): void
    {
        $t = $this->makeTenant();
        $inv = $this->makeInvoice($t, $this->makeClient($t));
        $rows = [];
        for ($i = 1; $i <= 5001; $i++) {
            $rows[] = ['tenant_id' => $t->id, 'invoice_id' => $inv->id, 'payment_number' => 'BULK-' . $i, 'amount' => 1, 'currency_code' => 'AED',
                'base_amount' => 1, 'received_at' => '2026-11-10 09:00:00', 'method' => 'cash', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('payments')->insert($chunk);
        }

        $this->actingAs($this->makeUser($t, 'finance'))->from(route('admin.payments.index'))->get(route('admin.payments.export'))
            ->assertSessionHasErrors('export');
        $this->assertSame(0, ActivityLog::where('action', 'ledger.exported')->count());
    }

    #[Test]
    public function the_ledger_never_shows_another_companys_payments(): void
    {
        $a = $this->makeTenant(slug: 'la');
        $b = $this->makeTenant(slug: 'lb');
        $this->pay($this->makeInvoice($b, $this->makeClient($b)), '10');

        $this->actingAs($this->makeUser($a, 'finance'))->get(route('admin.payments.index'))->assertDontSee('PAY-T-1');
    }

    #[Test]
    public function technician_and_front_desk_get_not_found_and_starter_gets_the_plan_page(): void
    {
        $t = $this->makeTenant();
        // Field users never reach office pages: the existing RouteTechnicianMobile middleware sends them to their own app.
        $this->actingAs($this->makeUser($t, 'field_technician'))->get(route('admin.payments.index'))->assertRedirect(route('technician.jobs.index'));
        $this->actingAs($this->makeUser($t, 'front_desk'))->get(route('admin.payments.index'))->assertNotFound();

        $s = $this->makeTenant($this->starterPlan(), slug: 'stx');
        $this->actingAs($this->makeUser($s, 'finance'))->get(route('admin.payments.index'))->assertStatus(403)->assertSee('This is not part of your plan');
    }
}
