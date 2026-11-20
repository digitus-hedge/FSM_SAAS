<?php

namespace App\Mail;

use App\Models\ServiceRequest;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells a client contact that a visit moved to another date or time (Release B register DSP-08). Only sent when the time really changes.
 * Never carries the technician's name or contact details, the priority, the coverage or any internal field (blueprint 7.7b: what a client sees).
 * The times are already in the company's time zone.
 */
class JobRescheduledMail extends Mailable
{
    public function __construct(
        public ServiceRequest $serviceRequest,
        public string $contactName,
        public ?Carbon $previous,
        public Carbon $new,
        public ?string $reason = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your visit for {$this->serviceRequest->sr_number} has moved to {$this->new->format('D j M, H:i')}");
    }

    public function content(): Content
    {
        $sr  = $this->serviceRequest;
        $ctx = \App\Services\Notifications\EmailContent::contextFor($sr, [
            'name' => $this->contactName, 'when' => $this->new->format('D j M Y, H:i'), 'whenLabel' => 'New date and time',
            'previous' => $this->previous?->format('D j M Y, H:i'), 'reason' => $this->reason, 'technician' => null,
        ]);

        // the times are already in the company's zone; the client sees no technician here (blueprint 7.7b)
        return new Content(view: 'emails.base', text: 'emails.base-text', with: ['m' => \App\Services\Notifications\EmailContent::client('sr.eta_changed', $ctx)]);
    }
}
