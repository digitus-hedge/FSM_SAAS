<?php

namespace Tests\Feature\Currency;

use App\Models\Invoice;
use App\Models\InvoiceToken;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\QuoteToken;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * Company isolation for the money records: Invoice, Quote and Payment are scoped to the signed-in company, and route binding
 * is scoped too. Company A can never open company B's invoice, quote or payment by id, and the secure /i/ and /q/ links
 * still work, but only on the company they belong to (and on the root address, where no company is chosen).
 */
class BillingIsolationTest extends BillingTestCase
{
    private Tenant $a;
    private Tenant $b;
    private $userA;
    private Invoice $invB;
    private Quote $quoteB;
    private Payment $payB;
    private Invoice $invA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeTenant(slug: 'aco');
        $this->b = $this->makeTenant(slug: 'bco');
        $this->userA = $this->makeUser($this->a, 'finance');

        $cA = $this->makeClient($this->a, 'A Client');
        $cB = $this->makeClient($this->b, 'B Client');
        $this->invA = $this->makeInvoice($this->a, $cA, ['status' => 'sent']);
        $this->invB = $this->makeInvoice($this->b, $cB, ['status' => 'overdue', 'invoice_number' => 'INV-B-SECRET']);
        $this->payB = Payment::create(['tenant_id' => $this->b->id, 'invoice_id' => $this->invB->id, 'payment_number' => 'PAY-B-1', 'amount' => 10, 'received_at' => now(), 'method' => 'cash']);
        $this->quoteB = $this->makeQuote($this->b, $cB, $this->makeServiceRequest($this->b, $cB, 'pending'));
    }

    /** Request on a host, with the tenant context reset afterwards so assertions see every company again. */
    private function as(string $host, callable $request)
    {
        $res = $request($this->actingAs($this->userA), $host);
        Tenant::setCurrent(null);

        return $res;
    }

    private function hosts(): array
    {
        return ['company A address' => $this->onHost($this->a), 'root address' => 'http://localhost'];
    }

    #[Test]
    public function company_a_cannot_open_company_bs_invoice_by_id(): void
    {
        foreach ($this->hosts() as $label => $host) {
            foreach (['show', 'pdf', 'edit'] as $page) {
                $this->as($host, fn ($c, $h) => $c->get($h . route('admin.invoices.' . $page, $this->invB, false)))->assertNotFound($label . ' ' . $page);
            }
            foreach (['submit', 'approve', 'send', 'void', 'write-off'] as $action) {
                $this->as($host, fn ($c, $h) => $c->post($h . route('admin.invoices.' . $action, $this->invB, false), ['reason' => 'x']))->assertNotFound($label . ' ' . $action);
            }
            $this->as($host, fn ($c, $h) => $c->post($h . route('admin.invoices.payment', $this->invB, false), ['amount' => 1, 'received_at' => '2026-11-19', 'method' => 'cash']))->assertNotFound();
            $this->as($host, fn ($c, $h) => $c->put($h . route('admin.invoices.update', $this->invB, false), $this->builderPayload([], 'due_date')))->assertNotFound();
        }
        $this->assertSame('overdue', $this->invB->fresh()->status);
    }

    #[Test]
    public function company_a_cannot_open_company_bs_quote_by_id(): void
    {
        foreach ($this->hosts() as $label => $host) {
            foreach (['show', 'pdf', 'edit'] as $page) {
                $this->as($host, fn ($c, $h) => $c->get($h . route('admin.quotes.' . $page, $this->quoteB, false)))->assertNotFound($label . ' ' . $page);
            }
            $this->as($host, fn ($c, $h) => $c->post($h . route('admin.quotes.send', $this->quoteB, false), ['recipients' => ['x']]))->assertNotFound();
            $this->as($host, fn ($c, $h) => $c->post($h . route('admin.quotes.revise', $this->quoteB, false)))->assertNotFound();
            $this->as($host, fn ($c, $h) => $c->put($h . route('admin.quotes.update', $this->quoteB, false), $this->builderPayload()))->assertNotFound();
        }
        $this->assertSame('draft', $this->quoteB->fresh()->status);
    }

    #[Test]
    public function company_a_cannot_reverse_or_see_company_bs_payment_by_id(): void
    {
        foreach ($this->hosts() as $host) {
            $this->as($host, fn ($c, $h) => $c->post($h . route('admin.invoices.payment.reverse', $this->payB, false), ['reason' => 'because']))->assertNotFound();
        }
        $this->assertNull($this->payB->fresh()->reversed_at);

        // and the ledger shows only its own company's payments
        $this->as($this->onHost($this->a), fn ($c, $h) => $c->get($h . route('admin.payments.index', [], false)))->assertDontSee('PAY-B-1');
    }

    #[Test]
    public function company_a_cannot_pause_company_bs_reminders_by_id(): void
    {
        $this->as($this->onHost($this->a), fn ($c, $h) => $c->post($h . route('admin.invoices.reminders.pause', $this->invB, false), ['reason' => 'Trying it']))->assertNotFound();
        $this->assertNull($this->invB->fresh()->reminders_paused_at);
    }

    #[Test]
    public function the_lists_and_counts_on_a_company_address_hold_only_that_companys_records(): void
    {
        $res = $this->as($this->onHost($this->a), fn ($c, $h) => $c->get($h . route('admin.invoices.index', ['tab' => 'all'], false)));

        $res->assertOk()->assertSee($this->invA->invoice_number)->assertDontSee('INV-B-SECRET');

        // Finance may open quotes (quote.manage), and sees only its own company's
        $this->as($this->onHost($this->a), fn ($c, $h) => $c->get($h . route('admin.quotes.index', ['tab' => 'all'], false)))->assertOk()->assertDontSee($this->quoteB->quote_number);
    }

    #[Test]
    public function the_models_are_scoped_to_the_current_company(): void
    {
        Tenant::setCurrent($this->a);

        $this->assertSame([$this->invA->id], Invoice::pluck('id')->all());
        $this->assertSame(0, Quote::count());
        $this->assertSame(0, Payment::count());
        $this->assertNull(Invoice::find($this->invB->id));

        Tenant::setCurrent(null);
        $this->assertSame(2, Invoice::count()); // jobs and commands have no company and see everything, as before
    }

    #[Test]
    public function the_secure_links_work_on_the_right_company_and_not_on_another(): void
    {
        $it = InvoiceToken::create(['tenant_id' => $this->b->id, 'invoice_id' => $this->invB->id, 'recipient_name' => 'B contact', 'token' => InvoiceToken::generate(), 'sent_via' => 'email', 'sent_at' => now()]);
        $qt = QuoteToken::create(['tenant_id' => $this->b->id, 'quote_id' => $this->quoteB->id, 'recipient_name' => 'B contact', 'token' => QuoteToken::generate(), 'sent_via' => 'email', 'sent_at' => now()]);

        foreach (['/i/' . $it->token, '/i/' . $it->token . '/pdf', '/q/' . $qt->token, '/q/' . $qt->token . '/pdf'] as $path) {
            $this->get($this->onHost($this->b, $path))->assertOk();       // its own company
            Tenant::setCurrent(null);
            $this->get('http://localhost' . $path)->assertOk();            // the root address: no company chosen
            Tenant::setCurrent(null);
            $this->get($this->onHost($this->a, $path))->assertNotFound();  // another company
            Tenant::setCurrent(null);
        }
    }
}
