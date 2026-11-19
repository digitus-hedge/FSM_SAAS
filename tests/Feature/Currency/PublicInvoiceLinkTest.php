<?php

namespace Tests\Feature\Currency;

use App\Models\Invoice;
use App\Models\InvoiceToken;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * The client's secure invoice link /i/{token} (also the "View invoice" link of the overdue reminder).
 * resources/views/public/invoice.blade.php did not exist, so the page failed. It must work on the company address.
 */
class PublicInvoiceLinkTest extends BillingTestCase
{
    private $tenant;
    private Invoice $inv;
    private InvoiceToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->tenant = $this->makeTenant(slug: 'linkco');
        $this->inv = $this->makeInvoice($this->tenant, $this->makeClient($this->tenant, 'Acme Client'), [
            'invoice_number' => 'INV-2026-000042', 'currency_code' => 'USD', 'exchange_rate' => '3.6725', 'status' => 'sent',
            'subtotal' => 1000, 'total' => 1000, 'balance' => 600, 'base_total' => 3672.5, 'due_date' => '2026-12-01',
        ]);
        \App\Models\InvoiceItem::create(['invoice_id' => $this->inv->id, 'line_no' => 1, 'description' => 'Compressor repair', 'quantity' => 1, 'unit_rate' => 1000, 'line_total' => 1000]);
        $this->token = $this->sendTo($this->inv, 'client@acme.test');
    }

    #[Test]
    public function the_invoice_link_works_on_the_company_host_and_counts_the_view(): void
    {
        $res = $this->get($this->onHost($this->tenant, '/i/' . $this->token->token));

        $res->assertOk()->assertSee('INV-2026-000042')->assertSee('Compressor repair')->assertSee('USD 1,000.00')->assertSee('USD 600.00')->assertSee('01 Dec 2026');
        $this->assertSame(1, $this->token->fresh()->viewed_count);
        $this->assertNotNull($this->token->fresh()->viewed_first_at);
    }

    #[Test]
    public function the_client_page_shows_the_document_currency_only_never_the_rate_or_the_base_amount(): void
    {
        $res = $this->get($this->onHost($this->tenant, '/i/' . $this->token->token))->assertOk();

        foreach (['3.6725', '3,672.50', '3672.5', 'from provider', 'entered by hand', 'AED'] as $secret) {
            $res->assertDontSee($secret);
        }
    }

    #[Test]
    public function the_link_in_the_overdue_reminder_email_opens_this_page(): void
    {
        $mail = new \App\Mail\InvoiceOverdueMail($this->inv, $this->token);
        $link = route('public.invoice.show', $this->token->token);

        $this->assertStringContainsString($link, $mail->render());
        $this->get($link)->assertOk()->assertSee('INV-2026-000042'); // the full URL, on whatever host the app runs on
    }

    #[Test]
    public function the_pdf_link_works_and_an_unknown_link_is_not_found(): void
    {
        $this->get($this->onHost($this->tenant, '/i/' . $this->token->token . '/pdf'))->assertOk()->assertSee('USD')->assertDontSee('3.6725');
        $this->get($this->onHost($this->tenant, '/i/not-a-real-token'))->assertNotFound();
    }
}
