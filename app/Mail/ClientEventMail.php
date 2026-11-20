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
        public ?int $contactId = null,
    ) {
    }

    /** The layout data. Public so the message log and the tests can read it. */
    public function frame(): array
    {
        $sr  = $this->serviceRequest;
        $ctx = EmailContent::contextFor($sr, ['name' => $this->contactName]);
        $tz  = $ctx['company']['timezone'];

        if (! empty($ctx['company']['portal']) && $this->hasPortalAccount()) {
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

    /**
     * True only when the contact has an active client sign-in on this company's portal (same email, same client) and may see this request
     * (a viewer only sees the sites they are scoped to). Otherwise the button is the "contact us" mailto, not a link to a page they cannot open.
     */
    private function hasPortalAccount(): bool
    {
        $sr      = $this->serviceRequest;
        $contact = $this->contactId ? \App\Models\ClientContact::withoutGlobalScopes()->where('tenant_id', $sr->tenant_id)->find($this->contactId) : null;
        if (! $contact || ! filled($contact->email)) {
            return false;
        }

        $user = \App\Models\User::withoutGlobalScopes()->where('tenant_id', $sr->tenant_id)->where('client_id', $sr->client_id)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($contact->email)])->whereNull('deactivated_at')
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['client_user', 'client_admin', 'client_viewer']))->first();
        if (! $user) {
            return false;
        }
        if ($user->role?->code === 'client_viewer') {
            return in_array((int) $sr->project_site_id, array_map('intval', $user->site_scope_json ?? []), true);
        }

        return true;
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
