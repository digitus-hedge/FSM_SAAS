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
        $sr = $this->serviceRequest;

        return new Content(
            view: 'emails.feedback-request',
            with: [
                'name'    => $this->contactName,
                'number'  => $sr->sr_number,
                'title'   => $sr->title,
                'company' => \App\Models\Tenant::find($sr->tenant_id)?->name ?? config('app.name'),
                'link'    => $this->feedbackUrl,
                'until'   => $this->validUntil->copy()->setTimezone(\App\Models\Tenant::find($sr->tenant_id)?->timezone ?: config('app.timezone'))->format('j M Y'),
            ],
        );
    }
}
