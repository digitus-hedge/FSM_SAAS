<?php

namespace Tests\Feature\Currency;

use App\Models\Invoice;
use App\Models\InvoiceToken;
use App\Models\Payment;
use App\Models\QuoteToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

/** Section 10.5: dashboards in the base currency (CUR-06), CP-045 to CP-050. The money strip is on the company dashboard. */
class DashboardBaseCurrencyTest extends BillingTestCase
{
    private $tenant;
    private $finance;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->tenant  = $this->makeTenant();
        $this->finance = $this->makeUser($this->tenant, 'finance');
    }

    private function dashboard($user = null)
    {
        return $this->actingAs($user ?? $this->finance)->get($this->onHost($this->tenant, '/dashboard'));
    }

    private function pay(Invoice $inv, string $amount): void
    {
        Payment::create(['tenant_id' => $inv->tenant_id, 'invoice_id' => $inv->id, 'payment_number' => 'P-' . $inv->id . '-' . $amount,
            'amount' => $amount, 'received_at' => now(), 'method' => 'cash']);
        $inv->fresh()->recomputeBalance();
    }

    /** AED 1,000 (not due), USD 1,000 at 3.6725 and USD 500 at 3.70 (both overdue), 100 AED and 400 USD received. */
    private function seedMixed(): void
    {
        $c = $this->makeClient($this->tenant);
        $aed  = $this->makeInvoice($this->tenant, $c, ['total' => 1000, 'balance' => 1000]);
        $usd1 = $this->makeInvoice($this->tenant, $c, ['currency_code' => 'USD', 'exchange_rate' => '3.6725', 'total' => 1000, 'balance' => 1000, 'due_date' => '2026-11-09', 'status' => 'overdue']);
        $this->makeInvoice($this->tenant, $c, ['currency_code' => 'USD', 'exchange_rate' => '3.70', 'total' => 500, 'balance' => 500, 'due_date' => '2026-11-14', 'status' => 'overdue']);
        $this->pay($aed, '100');
        $this->pay($usd1, '400');
    }

    #[Test]
    public function CP_045_the_finance_dashboard_shows_all_money_in_the_base_currency_from_base_amounts(): void
    {
        $this->seedMixed();

        $res = $this->dashboard()->assertOk()->assertSee('id="dx-money"', false);

        $res->assertSee('AED 1,569.00');   // received: 100 + 400 USD x 3.6725 = 1,469.00
        $res->assertDontSee('USD 1,000')->assertDontSee('USD 600');
    }

    #[Test]
    public function CP_046_unpaid_and_overdue_are_converted_at_each_invoices_own_rate(): void
    {
        $this->seedMixed();

        // unpaid: 900 AED + 600 USD x 3.6725 (2,203.50) + 500 USD x 3.70 (1,850.00) = 4,953.50
        // overdue: the two USD invoices = 4,053.50
        $this->dashboard()->assertSee('AED 4,953.50')->assertSee('AED 4,053.50');
    }

    #[Test]
    public function CP_047_the_converted_note_shows_only_when_other_currencies_are_included(): void
    {
        $this->seedMixed();
        $this->dashboard()->assertSee("Converted at each document's rate.", false);

        $local = $this->makeTenant(slug: 'localonly');
        $u = $this->makeUser($local, 'finance');
        $this->makeInvoice($local, $this->makeClient($local), ['total' => 700, 'balance' => 700]);
        $this->actingAs($u)->get($this->onHost($local, '/dashboard'))->assertOk()->assertSee('AED 700.00')->assertDontSee("Converted at each document's rate.", false);
    }

    #[Test]
    public function CP_048_amounts_in_different_currencies_are_never_added_together(): void
    {
        $this->seedMixed();

        // the face values left are 900 + 600 + 500 = 2,000: that sum must not appear anywhere
        $this->dashboard()->assertDontSee('2,000.00')->assertDontSee('AED 2,000');
    }

    #[Test]
    public function CP_049_release_a_documents_count_at_rate_1(): void
    {
        $c = $this->makeClient($this->tenant);
        // a Release A invoice: base currency, the default rate, base_total equal to the total
        Invoice::create(['tenant_id' => $this->tenant->id, 'client_id' => $c->id, 'service_request_id' => 1, 'invoice_number' => 'INV-OLD-1',
            'status' => 'sent', 'currency_code' => 'AED', 'total' => 250, 'balance' => 250, 'base_total' => 250, 'due_date' => '2026-11-01']);

        $this->dashboard()->assertSee('AED 250.00');
    }

    #[Test]
    public function the_money_strip_follows_the_payments_ledger_permissions(): void
    {
        $this->dashboard($this->makeUser($this->tenant, 'operations_manager'))->assertOk()->assertSee('id="dx-money"', false); // invoice.approve
        $this->dashboard($this->makeUser($this->tenant, 'company_admin'))->assertOk()->assertSee('id="dx-money"', false);
        $this->dashboard($this->makeUser($this->tenant, 'front_desk'))->assertOk()->assertDontSee('id="dx-money"', false);
    }

    #[Test]
    public function finance_can_open_the_company_dashboard(): void
    {
        $this->dashboard()->assertOk()->assertSee('Open requests');
    }

    #[Test]
    public function CP_050_scenario_17_end_to_end(): void
    {
        Mail::fake();
        $this->enableCurrency($this->tenant, 'USD'); // today's USD rate is 3.6725
        $admin  = $this->makeUser($this->tenant, 'company_admin');
        $client = $this->makeClient($this->tenant);
        $sr     = $this->makeServiceRequest($this->tenant, $client, 'pending');
        $f      = $this->actingAs($this->finance);

        // 1. A quote in USD: the rate fills from the provider, and Finance overrides it
        $f->get(route('admin.quotes.build', $sr))->assertOk();
        $quote = \App\Models\Quote::where('service_request_id', $sr->id)->sole();
        $f->put(route('admin.quotes.update', $quote), $this->builderPayload(['currency_code' => 'USD']))->assertRedirect();
        $this->assertSame('3.67250000', (string) $quote->fresh()->exchange_rate);
        $f->put(route('admin.quotes.update', $quote), $this->builderPayload(['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '3.70']))->assertRedirect();
        $this->assertSame(['3.70000000', 'manual'], [(string) $quote->fresh()->exchange_rate, $quote->fresh()->rate_source]);

        // 2. Sent: currency and rate fixed
        $f->post(route('admin.quotes.send', $quote), ['recipients' => ['Client']])->assertRedirect();
        $f->putJson(route('admin.quotes.update', $quote), $this->builderPayload(['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '3.9']))->assertStatus(409);

        // 3. The client accepts on the secure link
        $token = QuoteToken::where('quote_id', $quote->id)->value('token');
        $this->post($this->onHost($this->tenant, '/q/' . $token . '/accept'), ['name' => 'Client Person', 'confirmed' => 1])->assertRedirect();
        $this->assertSame('accepted', $quote->fresh()->status);

        // 4. The invoice starts in USD with the quote's rate
        \App\Models\ServiceRequest::withoutGlobalScopes()->whereKey($sr->id)->update(['status' => 'awaiting_invoice']);
        $f->get(route('admin.invoices.build', $sr))->assertOk();
        $inv = Invoice::where('service_request_id', $sr->id)->sole();
        $this->assertSame(['USD', '3.70000000', '1000.000', '3700.000'], [$inv->currency_code, (string) $inv->exchange_rate, (string) $inv->total, (string) $inv->base_total]);

        // 5. Submitted, approved by someone else, sent
        $f->post(route('admin.invoices.submit', $inv))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.invoices.approve', $inv))->assertRedirect();
        $this->actingAs($this->finance)->post(route('admin.invoices.send', $inv), ['recipients' => ['Client']])->assertRedirect();
        $this->assertSame('sent', $inv->fresh()->status);

        // 6. A payment of USD 500 at the invoice's rate
        $this->actingAs($this->finance)->post(route('admin.invoices.payment', $inv), ['amount' => '500', 'received_at' => '2026-11-19', 'method' => 'bank_transfer'])->assertRedirect();
        $p = Payment::where('invoice_id', $inv->id)->sole();
        $this->assertSame(['USD', '1850.000'], [$p->currency_code, (string) $p->base_amount]);

        // 7. The dashboard is in AED: received 1,850.00 and USD 500 left = 1,850.00 unpaid
        $res = $this->dashboard()->assertOk();
        $res->assertSee('AED 1,850.00')->assertSee("Converted at each document's rate.", false)->assertDontSee('USD 500');
    }
}
