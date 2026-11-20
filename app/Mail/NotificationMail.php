<?php

namespace App\Mail;

use App\Models\ServiceRequest;
use App\Models\UserNotification;
use App\Services\Notifications\EmailContent;
use App\Support\TenantUrl;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The email behind a staff or field notification (NotificationService). Same shared layout as every other message: company name and logo,
 * the title as the heading, the first line of the text as the intro, a details table when the message is about a request, one button
 * to the exact page on the company's own address, and the company's contact details in the footer.
 */
class NotificationMail extends Mailable
{
    public function __construct(public UserNotification $notification) {}

    /** The layout data. */
    public function frame(): array
    {
        $n = $this->notification;

        $sr = null;
        $srId = $n->data['sr_id'] ?? null;
        if ($srId) {
            $sr = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $n->tenant_id)->find($srId);
        }

        $ctx = $sr
            ? EmailContent::contextFor($sr)
            : ['company' => EmailContent::company((int) $n->tenant_id), 'labels' => EmailContent::labels((int) $n->tenant_id), 'extra' => []];

        $url = $n->url ? (str_starts_with($n->url, '/') ? TenantUrl::for((int) $n->tenant_id, $n->url) : $n->url) : null;

        return EmailContent::staff((string) $n->event_key, (string) $n->title, $n->body, $ctx, $url);
    }

    public function build()
    {
        return $this->subject($this->notification->title)
            ->view('emails.base', ['m' => $this->frame()])
            ->text('emails.base-text', ['m' => $this->frame()]);
    }
}
