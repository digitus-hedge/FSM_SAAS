<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceToken;
use App\Services\Currency\Money;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The overdue reminder to a client contact (LED-05, spec 7). The wording is fixed by the spec. */
class InvoiceOverdueMail extends Mailable
{
    public function __construct(public Invoice $invoice, public InvoiceToken $token)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Invoice {$this->invoice->invoice_number} is overdue");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-overdue',
            with: [
                'number' => $this->invoice->invoice_number,
                'amount' => $this->invoice->currency_code . ' ' . Money::format($this->invoice->balance, $this->invoice->currency_code),
                'due'    => $this->invoice->due_date?->format('d M Y'),
                'link'   => route('public.invoice.show', $this->token->token),
            ],
        );
    }
}
