<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

/** One email with the messages a user held for an hourly or daily digest (spec D15). */
class DigestMail extends Mailable
{
    public function __construct(public string $company, public Collection $items, public int $more = 0) {}

    public function build()
    {
        $n = $this->items->count() + $this->more;

        return $this->subject("{$n} new messages from {$this->company}")->view('emails.digest');
    }
}
