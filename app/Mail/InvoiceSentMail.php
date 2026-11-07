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
        return new Content(
            view: 'emails.invoice-sent',
            with: [
                'invoice' => $this->token->invoice,
                'link'    => route('public.invoice.show', $this->token->token),
            ],
        );
    }
}