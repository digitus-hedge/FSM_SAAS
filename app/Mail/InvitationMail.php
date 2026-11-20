<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public Tenant $tenant,
        public string $invitationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to {$this->tenant->name} on Aiywah FSM",
        );
    }

    public function content(): Content
    {
        $m = \App\Services\Notifications\EmailContent::frame(
            \App\Services\Notifications\EmailContent::company($this->tenant), "You're invited to {$this->tenant->name}",
            "Hello {$this->user->name}, you have been invited to join {$this->tenant->name}. Accept the invitation and set your password.",
            [], ['label' => 'Accept invitation', 'url' => $this->invitationUrl],
            'This link works once and expires in 7 days. If you were not expecting this invitation, you can ignore this email.',
        );

        return new Content(view: 'emails.base', text: 'emails.base-text', with: ['m' => $m]);
    }
}
