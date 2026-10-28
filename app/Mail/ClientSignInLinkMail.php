<?php
namespace App\Mail;

use App\Models\{Tenant, User};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientSignInLinkMail extends Mailable implements ShouldQueue
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
        return new Content(
            view: 'emails.client-sign-in',
            with: [
                'user'      => $this->user,
                'tenant'    => $this->tenant,
                'signInUrl' => $this->signInUrl,
            ],
        );
    }
}