<?php

namespace App\Mail;

use App\Models\ServiceRequest;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The message a client contact gets when a request is completed (blueprint 9.2 event sr.completed, "Client contact (feedback link)").
 * It holds the secure feedback link. The link is never stored in the message log (the log keeps ids only).
 */
class FeedbackRequestMail extends Mailable
{
    public function __construct(
        public ServiceRequest $serviceRequest,
        public string $contactName,
        public string $feedbackUrl,
        public Carbon $validUntil,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->serviceRequest->sr_number} is completed. How did we do?");
    }

    public function content(): Content
    {
        $sr  = $this->serviceRequest;
        $ctx = \App\Services\Notifications\EmailContent::contextFor($sr, ['name' => $this->contactName]);
        $r   = strtolower($ctx['labels']['request']);
        $until = $this->validUntil->copy()->setTimezone($ctx['company']['timezone'])->format('j M Y');

        $m = \App\Services\Notifications\EmailContent::frame(
            $ctx['company'], "{$sr->sr_number} is completed. How did we do?",
            "Hello {$this->contactName}, your {$r} is completed. A rating, a score and, if you like, a comment takes about a minute.",
            \App\Services\Notifications\EmailContent::rows($ctx, ['number', 'title', 'client', 'site', 'technician']),
            ['label' => 'Give your feedback', 'url' => $this->feedbackUrl],
            "This link works once and is valid until {$until}.",
        );

        return new Content(view: 'emails.base', text: 'emails.base-text', with: ['m' => $m]);
    }
}
