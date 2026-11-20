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
        $quote   = $this->token->quote;
        $sr      = $quote->serviceRequest;
        $company = \App\Services\Notifications\EmailContent::company((int) $quote->tenant_id);
        $labels  = \App\Services\Notifications\EmailContent::labels((int) $quote->tenant_id);

        $m = \App\Services\Notifications\EmailContent::frame(
            $company, "Quote {$quote->quote_number} from {$company['name']}",
            'Hello, a quote is ready for your review.',
            [
                'Quote number'                 => $quote->quote_number,
                $labels['request'] . ' number' => $sr?->sr_number,
                $labels['client']              => $quote->client?->name,
                $labels['site']                => $sr?->site?->name,
                'Total'                        => $quote->currency_code . ' ' . \App\Services\Currency\Money::format($quote->total, $quote->currency_code),
                'Valid until'                  => $quote->valid_until?->format('j M Y'),
            ],
            ['label' => 'View and respond to quote', 'url' => route('public.quote.show', $this->token->token)],
        );

        return new Content(view: 'emails.base', text: 'emails.base-text', with: ['m' => $m]);
    }
}
