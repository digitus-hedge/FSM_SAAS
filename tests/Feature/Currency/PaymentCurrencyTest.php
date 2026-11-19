<?php

namespace Tests\Feature\Currency;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\Payment;
use App\Services\Billing\OverdueReminderService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/** Section 10.4 (payments in the invoice currency): CP-039 to CP-042 and CP-044. CP-037, 038 and 043 are in PaymentsLedgerTest and MigrationsTest. */
class PaymentCurrencyTest extends BillingTestCase
{
    private $tenant;
    private $finance;
    private Invoice $inv;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->tenant  = $this->makeTenant();
        $this->finance = $this->makeUser($this->tenant, 'finance');
        $this->inv     = $this->makeInvoice($this->tenant, $this->makeClient($this->tenant), [
            'currency_code' => 'USD', 'exchange_rate' => '3.6725', 'total' => 1000, 'balance' => 1000, 'base_total' => 3672.5,
        ]);
    }

    private function pay(string $amount, string $date = '2026-11-19')
    {
        return $this->actingAs($this->finance)->post(route('admin.invoices.payment', $this->inv), ['amount' => $amount, 'received_at' => $date, 'method' => 'bank_transfer']);
    }

    #[Test]
    public function CP_039_a_payment_on_a_day_with_a_different_rate_uses_the_invoices_fixed_rate(): void
    {
        $this->setRate($this->tenant, 'USD', '2026-11-10', '4.20000000'); // the market moved
        $this->setRate($this->tenant, 'USD', '2026-11-19', '3.90000000');

        $this->pay('1000', '2026-11-10')->assertRedirect();

        $p = Payment::where('invoice_id', $this->inv->id)->sole();
        $this->assertSame(['USD', '3672.500'], [$p->currency_code, (string) $p->base_amount]);
        $this->assertSame('paid', $this->inv->fresh()->status);
    }

    #[Test]
    public function CP_040_reversing_a_payment_keeps_its_base_amount_and_leaves_it_out_of_the_totals(): void
    {
        $this->pay('400')->assertRedirect();
        $p = Payment::where('invoice_id', $this->inv->id)->sole();

        $this->actingAs($this->finance)->post(route('admin.invoices.payment.reverse', $p), ['reason' => 'Bounced cheque'])->assertRedirect();

        $p = $p->fresh();
        $this->assertSame('1469.000', (string) $p->base_amount); // 400 x 3.6725, kept
        $this->assertNotNull($p->reversed_at);
        $this->assertSame('1000.000', (string) $this->inv->fresh()->balance);
        $ledger = app(\App\Services\Billing\PaymentLedgerQuery::class);
        $this->assertSame('0', $ledger->totalBase($this->tenant, $ledger->filters($this->tenant, ['include_reversed' => 1])));
    }

    #[Test]
    public function CP_041_writing_off_the_balance_is_in_usd_at_the_fixed_rate(): void
    {
        $this->pay('400')->assertRedirect();

        $this->actingAs($this->finance)->post(route('admin.invoices.write-off', $this->inv), ['reason' => 'Small balance agreed'])->assertRedirect();

        $inv = $this->inv->fresh();
        $this->assertSame(['written_off', '600.000', '0.000', 'USD', '3.67250000'], [$inv->status, (string) $inv->write_off_amount, (string) $inv->balance, $inv->currency_code, (string) $inv->exchange_rate]);
        $this->assertStringContainsString('USD 600.000', InvoiceEvent::where('invoice_id', $inv->id)->where('type', 'written_off')->value('label'));
        $this->actingAs($this->finance)->get(route('admin.invoices.show', $inv))->assertSee('USD')->assertSee('≈ 3,672.50 AED');
    }

    #[Test]
    public function CP_042_the_dialog_shows_the_amount_in_the_invoice_currency_and_the_base_line(): void
    {
        $this->actingAs($this->finance)->get(route('admin.invoices.show', $this->inv))
            ->assertOk()->assertSee('Amount in USD')->assertSee('id="pay-base-line"', false)->assertSee('data-rate="3.6725"', false)
            ->assertSee('at the invoice rate', false)->assertSee('value="1000.00"', false);

        $aed = $this->makeInvoice($this->tenant, $this->makeClient($this->tenant, 'Local'), ['total' => 500, 'balance' => 500]);
        $this->actingAs($this->finance)->get(route('admin.invoices.show', $aed))->assertSee('Amount in AED')->assertDontSee('id="pay-base-line"', false);
    }

    #[Test]
    public function the_payments_table_on_the_invoice_page_shows_the_base_amount(): void
    {
        $this->pay('400')->assertRedirect();

        $this->actingAs($this->finance)->get(route('admin.invoices.show', $this->inv))->assertSee('USD 400.00')->assertSee('≈ 1,469.00 AED');
    }

    #[Test]
    public function CP_044_a_usd_invoice_becomes_overdue_the_day_after_its_due_date_as_in_release_a(): void
    {
        $due = Carbon::now('Asia/Dubai')->toDateString();
        $this->inv->forceFill(['due_date' => $due, 'status' => 'sent'])->save();
        $svc = app(OverdueReminderService::class);

        $svc->runForTenant($this->tenant, Carbon::now('Asia/Dubai')->startOfDay()); // on the due date
        $this->assertSame('sent', $this->inv->fresh()->status);

        $svc->runForTenant($this->tenant, Carbon::now('Asia/Dubai')->addDay()->startOfDay()); // the day after
        $this->assertSame('overdue', $this->inv->fresh()->status);
    }
}
