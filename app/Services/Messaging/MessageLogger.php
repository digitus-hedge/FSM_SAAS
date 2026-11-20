<?php

namespace App\Services\Messaging;

use App\Mail\DigestMail;
use App\Mail\FeedbackRequestMail;
use App\Mail\InvoiceOverdueMail;
use App\Mail\InvoiceSentMail;
use App\Mail\JobRescheduledMail;
use App\Mail\NotificationMail;
use App\Mail\QuoteSentMail;
use App\Models\InvoiceToken;
use App\Models\MessageLog;
use App\Models\QuoteToken;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The one way the system sends an email to a person: it writes the message log first, sends, then records the result and any error.
 * (NotificationService and every Mailable send path go through here; WhatsApp will use the same table with another channel.)
 *
 * What a log row may hold: who, which event, which record, the status, a short error and the ids needed to build the message again.
 * It never holds a secure-link token, a sign-in or signature code, a password or the message text. Error text is cleaned of anything that
 * looks like a token before it is saved (see clean()).
 *
 * Retry: only a failed row whose message can be built again from ids alone (REBUILDERS). A message that carries a secure link or a code
 * is not retryable (the token is not kept): the person sends a new one from the screen that made it. The feedback message is built again
 * with a NEW link.
 */
class MessageLogger
{
    /** Mailable class => builder from a log row. A class missing here is not retryable. */
    private const REBUILDERS = [
        NotificationMail::class    => 'rebuildNotification',
        JobRescheduledMail::class  => 'rebuildRescheduled',
        FeedbackRequestMail::class => 'rebuildFeedback',
        QuoteSentMail::class       => 'rebuildQuote',
        InvoiceSentMail::class     => 'rebuildInvoice',
        InvoiceOverdueMail::class  => 'rebuildOverdue',
        \App\Mail\ClientEventMail::class => 'rebuildClientEvent',
    ];

    /** Messages sent by the queue worker, not during the web request. Anything else is still sent at once. */
    private const QUEUED = [
        \App\Mail\NotificationMail::class, \App\Mail\ClientEventMail::class, \App\Mail\JobRescheduledMail::class,
        \App\Mail\FeedbackRequestMail::class, \App\Mail\QuoteSentMail::class, \App\Mail\InvoiceSentMail::class,
        \App\Mail\InvoiceOverdueMail::class, \App\Mail\OneTimeCodeMail::class,
    ];

    /**
     * @param array{tenant_id?:int,user_id?:int,contact_id?:int,related?:Model,payload?:array} $ctx  payload holds ids only
     * @param bool $throw  rethrow a send error after it is logged (callers that handle the error themselves keep their flow)
     */
    public function send(string $to, Mailable $mailable, string $eventKey, array $ctx = [], bool $throw = true): MessageLog
    {
        $related = $ctx['related'] ?? null;
        $log = MessageLog::withoutGlobalScopes()->create([
            'tenant_id'            => $ctx['tenant_id'] ?? $related?->tenant_id ?? Tenant::current()?->id,
            'direction'            => 'outbound',
            'channel'              => 'email',
            'event_key'            => $eventKey,
            'recipient_user_id'    => $ctx['user_id'] ?? null,
            'recipient_contact_id' => $ctx['contact_id'] ?? null,
            'recipient_address'    => mb_substr($to, 0, 190),
            'status'               => 'queued',
            'attempts'             => 0,
            'mailable'             => $mailable::class,
            'related_type'         => $related ? $related::class : null,
            'related_id'           => $related?->getKey(),
            'payload_json'         => $ctx['payload'] ?? null,
        ]);

        // queued: the row says "queued" now and the worker sends it and records sent or failed (the job holds the message, the log never does)
        if (in_array($mailable::class, self::QUEUED, true)) {
            \App\Jobs\SendLoggedMail::dispatch((int) $log->id, $to, $mailable)->afterCommit();

            return $log;
        }

        try {
            $this->attempt($log, fn () => Mail::to($to)->send($mailable));
        } catch (\Throwable $e) {
            if ($throw) {
                throw $e;
            }
        }

        return $log->fresh();
    }

    /** The queue worker's send: the same attempt, status and error rules as a send during the request. Throws on failure so the job retries. */
    public function deliver(int $logId, string $to, Mailable $mailable): void
    {
        $log = MessageLog::withoutGlobalScopes()->find($logId);
        if (! $log || $log->status === 'sent') {
            return;
        }
        $this->attempt($log, fn () => Mail::to($to)->send($mailable));

        if ($log->related_type === UserNotification::class && $log->related_id) {
            UserNotification::withoutGlobalScopes()->whereKey($log->related_id)->update(['emailed_at' => now()]);
        }
    }

    /** Sends again a failed row that can be built again. Returns the row; check ->status. Throws \DomainException when it may not be retried. */
    public function retry(MessageLog $log): MessageLog
    {
        if (! $log->isFailed()) {
            throw new \DomainException('Only a failed message can be sent again.');
        }
        if (! $this->isRetryable($log)) {
            throw new \DomainException('This message carries a secure link or code, so it cannot be sent again from here. Send a new one from the screen that made it.');
        }

        $mailable = $this->rebuild($log);
        if (! $mailable) {
            throw new \DomainException('The record this message was about no longer exists.');
        }

        try {
            $this->attempt($log, fn () => Mail::to($log->recipient_address)->send($mailable));
        } catch (\Throwable $e) {
            // recorded by attempt(); the row says what happened
        }

        return $log->fresh();
    }

    public function isRetryable(MessageLog $log): bool
    {
        return $log->isFailed() && $log->mailable && isset(self::REBUILDERS[$log->mailable]);
    }

    /** True for a class that can ever be retried. */
    public function canRebuild(?string $mailable): bool
    {
        return $mailable !== null && isset(self::REBUILDERS[$mailable]);
    }

    private function attempt(MessageLog $log, \Closure $send): void
    {
        $log->forceFill([
            'attempts'         => min(255, (int) $log->attempts + 1),
            'first_attempt_at' => $log->first_attempt_at ?? now(),
            'last_attempt_at'  => now(),
        ])->save();

        try {
            $send();
            $log->forceFill(['status' => 'sent', 'sent_at' => now(), 'failure_reason' => null])->save();
        } catch (\Throwable $e) {
            $log->forceFill(['status' => 'failed', 'failure_reason' => $this->clean($e->getMessage())])->save();
            Log::warning('Message failed', ['log' => $log->id, 'event' => $log->event_key, 'error' => $log->failure_reason]);
            throw $e;
        }
    }

    /** A short error with anything that looks like a token or code removed. */
    public function clean(string $text): string
    {
        $text = preg_replace('/[A-Za-z0-9_\-]{24,}/', '[hidden]', $text) ?? '';
        $text = preg_replace('/\b\d{4,8}\b/', '[hidden]', $text) ?? '';

        return mb_substr(trim($text), 0, 250);
    }

    /* ---------- rebuilders (ids only) ---------- */

    private function rebuild(MessageLog $log): ?Mailable
    {
        $method = self::REBUILDERS[$log->mailable] ?? null;

        return $method ? $this->{$method}($log, (array) $log->payload_json) : null;
    }

    private function rebuildNotification(MessageLog $log, array $p): ?Mailable
    {
        $n = UserNotification::withoutGlobalScopes()->where('tenant_id', $log->tenant_id)->find($p['notification_id'] ?? 0);

        return $n ? new NotificationMail($n) : null;
    }

    private function rebuildClientEvent(MessageLog $log, array $p): ?Mailable
    {
        $sr = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $log->tenant_id)->find($p['sr_id'] ?? 0);

        return $sr ? new \App\Mail\ClientEventMail($sr, (string) ($p['event'] ?? $log->event_key), $p['contact_name'] ?? null, (array) ($p['extra'] ?? []), isset($p['contact_id']) ? (int) $p['contact_id'] : null) : null;
    }

    private function rebuildRescheduled(MessageLog $log, array $p): ?Mailable
    {
        $sr = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $log->tenant_id)->find($p['sr_id'] ?? 0);

        return $sr ? new JobRescheduledMail(
            $sr, (string) ($p['contact_name'] ?? ''), ! empty($p['previous']) ? Carbon::parse($p['previous']) : null, Carbon::parse($p['new']), $p['reason'] ?? null,
        ) : null;
    }

    private function rebuildFeedback(MessageLog $log, array $p): ?Mailable
    {
        $sr = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $log->tenant_id)->find($p['sr_id'] ?? 0);

        return $sr ? app(\App\Services\Feedback\FeedbackLinks::class)->mailFor($sr, (string) ($p['contact_name'] ?? ''), (int) ($p['contact_id'] ?? 0)) : null;
    }

    private function rebuildQuote(MessageLog $log, array $p): ?Mailable
    {
        $t = QuoteToken::withoutGlobalScopes()->where('tenant_id', $log->tenant_id)->find($p['token_id'] ?? 0);

        return $t ? new QuoteSentMail($t) : null;
    }

    private function rebuildInvoice(MessageLog $log, array $p): ?Mailable
    {
        $t = InvoiceToken::withoutGlobalScopes()->where('tenant_id', $log->tenant_id)->find($p['token_id'] ?? 0);

        return $t ? new InvoiceSentMail($t) : null;
    }

    private function rebuildOverdue(MessageLog $log, array $p): ?Mailable
    {
        $t = InvoiceToken::withoutGlobalScopes()->where('tenant_id', $log->tenant_id)->with('invoice')->find($p['token_id'] ?? 0);

        return ($t && $t->invoice) ? new InvoiceOverdueMail($t->invoice, $t) : null;
    }
}
