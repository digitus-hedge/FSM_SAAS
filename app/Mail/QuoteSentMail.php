<?php

namespace App\Mail;

use App\Models\QuoteToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class QuoteSentMail extends Mailable
{
    use Queueable;

    public function __construct(public QuoteToken $token)
    {
    }

    public function envelope(): Envelope
    {
        $quote = $this->token->quote;
        return new Envelope(
            subject: "Quote {$quote->quote_number} from " . ($quote->client?->name ?? 'us'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-sent',
            with: [
                'quote' => $this->token->quote,
                'link'  => route('public.quote.show', $this->token->token),
            ],
        );
    }
}