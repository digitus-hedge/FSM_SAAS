<?php

namespace App\Mail;

use App\Models\UserNotification;
use Illuminate\Mail\Mailable;

class NotificationMail extends Mailable
{
    public function __construct(public UserNotification $notification) {}

    public function build()
    {
        return $this->subject($this->notification->title)
            ->view('emails.notification', ['n' => $this->notification]);
    }
}
