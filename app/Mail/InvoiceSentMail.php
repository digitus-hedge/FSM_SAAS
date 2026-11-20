<?php

namespace App\Mail;

use App\Models\InvoiceToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceSentMail extends Mailable
{
    use Queueable;

    public function __construct(public InvoiceToken $token)
    {
    }

    public function envelope(): Envelope
    {
        $invoice = $this->token->invoice;
        return new Envelope(
            subject: "Invoice {$invoice->invoice_number} from " . ($invoice->client?->name ?? 'us'),
        );
    }

    public function content(): Content
    {
        $invoice = $this->token->invoice;
        $sr      = $invoice->serviceRequest;
        $company = \App\Services\Notifications\EmailContent::company((int) $invoice->tenant_id);
        $labels  = \App\Services\Notifications\EmailContent::labels((int) $invoice->tenant_id);

        $m = \App\Services\Notifications\EmailContent::frame(
            $company, "Invoice {$invoice->invoice_number} from {$company['name']}",
            'Hello, your invoice is ready.',
            [
                'Invoice number'               => $invoice->invoice_number,
                $labels['request'] . ' number' => $sr?->sr_number,
                $labels['client']              => $invoice->client?->name,
                $labels['site']                => $sr?->site?->name,
                'Total'                        => $invoice->currency_code . ' ' . \App\Services\Currency\Money::format($invoice->total, $invoice->currency_code),
                'Due date'                     => $invoice->due_date?->format('j M Y'),
            ],
            ['label' => 'View invoice', 'url' => route('public.invoice.show', $this->token->token)],
        );

        return new Content(view: 'emails.base', text: 'emails.base-text', with: ['m' => $m]);
    }
}
