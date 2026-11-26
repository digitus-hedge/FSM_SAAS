<?php

namespace App\Jobs;

use App\Services\Messaging\MessageLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one email from the queue for MessageLogger. The message log row was written when the message was queued; the worker records
 * sent or failed on that same row (MessageLogger::deliver). The job holds the message itself (it may carry a secure link or a code), the
 * log never does. A failed send is tried again after 1 and 5 minutes; the row keeps the attempt count and a cleaned error.
 */
class SendLoggedMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $logId, public string $to, public Mailable $mailable)
    {
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(MessageLogger $messages): void
    {
        // The worker has no company address: links in the message (route() calls while it renders) need the company of the log row.
        $tenantId = \App\Models\MessageLog::whereKey($this->logId)->value('tenant_id');
        if (! $tenantId) {
            $messages->deliver($this->logId, $this->to, $this->mailable);
            return;
        }

        \App\Support\Tenancy::withUrlsFor((int) $tenantId, fn () => $messages->deliver($this->logId, $this->to, $this->mailable));
    }
}
