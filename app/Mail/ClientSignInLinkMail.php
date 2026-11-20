<?php
namespace App\Mail;

use App\Models\{Tenant, User};
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientSignInLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public Tenant $tenant,
        public string $signInUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your sign-in link for {$this->tenant->name}",
        );
    }

    public function content(): Content
    {
        $m = \App\Services\Notifications\EmailContent::frame(
            \App\Services\Notifications\EmailContent::company($this->tenant), "Your sign-in link for {$this->tenant->name}",
            "Hello {$this->user->name}, use the button below to sign in to your {$this->tenant->name} workspace.",
            [], ['label' => 'Sign in', 'url' => $this->signInUrl],
            'This link works once and expires in 24 hours. If you did not ask for it, you can safely ignore this email.',
        );

        return new Content(view: 'emails.base', text: 'emails.base-text', with: ['m' => $m]);
    }
}
