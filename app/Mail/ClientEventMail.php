<?php

namespace App\Mail;

use App\Models\ServiceRequest;
use App\Services\Notifications\EmailContent;
use App\Support\TenantUrl;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The email a client contact gets for the events blueprint 9.2 sent by WhatsApp: sr.created, sr.approved, sr.cancelled, sr.assigned,
 * sr.eta_changed, tech.arrived, depot.received and depot.ready_for_return. It is built from a request, an event key and a contact name,
 * so the message log can build it again from ids. The words are in EmailContent; it never carries internal fields.
 *
 * $extra: 'previous' (ISO time, sr.eta_changed) and 'reason'.
 */
class ClientEventMail extends Mailable
{
    public function __construct(
        public ServiceRequest $serviceRequest,
        public string $event,
        public ?string $contactName = null,
        public array $extra = [],
    ) {
    }

    /** The layout data. Public so the message log and the tests can read it. */
    public function frame(): array
    {
        $sr  = $this->serviceRequest;
        $ctx = EmailContent::contextFor($sr, ['name' => $this->contactName]);
        $tz  = $ctx['company']['timezone'];

        if (! empty($ctx['company']['portal'])) {
            $ctx['url'] = TenantUrl::for((int) $sr->tenant_id, '/portal/requests/' . $sr->id);
        }
        if (! empty($this->extra['previous'])) {
            $ctx['previous'] = \Carbon\Carbon::parse($this->extra['previous'])->setTimezone($tz)->format('D j M Y, H:i');
        }
        if (array_key_exists('reason', $this->extra)) {
            $ctx['reason'] = $this->extra['reason'];
        }
        if ($this->event === 'tech.arrived') {
            $ctx['when'] = ($sr->first_punch_in_at ?? now())->copy()->setTimezone($tz)->format('D j M Y, H:i');
            $ctx['whenLabel'] = 'Arrived at';
        }
        if (in_array($this->event, ['depot.received', 'depot.ready_for_return'], true)) {
            $ctx['when'] = now()->setTimezone($tz)->format('D j M Y, H:i');
            $ctx['technician'] = null;
        }
        if (in_array($this->event, ['sr.created', 'sr.approved', 'sr.cancelled'], true)) {
            $ctx['technician'] = null;
        }

        return EmailContent::client($this->event, $ctx);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->frame()['heading']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.base', text: 'emails.base-text', with: ['m' => $this->frame()]);
    }
}
