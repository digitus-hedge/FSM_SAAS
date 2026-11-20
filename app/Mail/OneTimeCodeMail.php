<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Services\Notifications\EmailContent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A one-time code by email (blueprint 9.2 signature.code and the field login code, decisions D6 and 100): 6 digits, 10 minutes, 3 tries.
 * kind is 'login' or 'signature'. The code is never kept in the message log, so this message cannot be sent again from the log.
 * Nothing sends it yet: the signature-waiver flow and the one-time login are not built. Send it through MessageLogger when they are.
 */
class OneTimeCodeMail extends Mailable
{
    public function __construct(
        public string $kind,
        public string $code,
        public Tenant|int|null $tenant = null,
        public ?string $contactName = null,
        public array $request = [],       // number, client, site, technician (names only) for a signature code
        public int $minutes = 10,
        public int $tries = 3,
    ) {
    }

    public function frame(): array
    {
        $ctx = array_merge(['company' => EmailContent::company($this->tenant), 'labels' => EmailContent::labels($this->tenant), 'name' => $this->contactName, 'extra' => []], $this->request);

        return EmailContent::code($this->kind, $ctx, $this->code, $this->minutes, $this->tries);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->kind === 'signature' ? 'Your signature code' : 'Your sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.base', text: 'emails.base-text', with: ['m' => $this->frame()]);
    }
}
