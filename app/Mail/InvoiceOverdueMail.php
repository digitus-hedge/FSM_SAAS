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
        $inv     = $this->invoice;
        $sr      = $inv->serviceRequest;
        $company = \App\Services\Notifications\EmailContent::company((int) $inv->tenant_id);
        $labels  = \App\Services\Notifications\EmailContent::labels((int) $inv->tenant_id);
        $amount  = $inv->currency_code . ' ' . Money::format($inv->balance, $inv->currency_code);

        $m = \App\Services\Notifications\EmailContent::frame(
            $company, "Invoice {$inv->invoice_number} is overdue",
            "Invoice {$inv->invoice_number} for {$amount} was due on " . $inv->due_date?->format('j M Y') . ' and is still unpaid. Please arrange payment or contact us.',
            [
                'Invoice number'               => $inv->invoice_number,
                $labels['request'] . ' number' => $sr?->sr_number,
                $labels['client']              => $inv->client?->name,
                'Amount unpaid'                => $amount,
                'Due date'                     => $inv->due_date?->format('j M Y'),
            ],
            ['label' => 'View invoice', 'url' => route('public.invoice.show', $this->token->token)],
        );

        return new Content(view: 'emails.base', text: 'emails.base-text', with: ['m' => $m]);
    }
}
