<?php

namespace App\Services\Feedback;

use App\Mail\FeedbackRequestMail;
use App\Models\ClientContact;
use App\Models\MessageLog;
use App\Models\SecureLink;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\SecureLinkService;
use App\Services\Messaging\MessageLogger;
use App\Services\Notifications\NotificationService;
use App\Support\TenantUrl;
use Illuminate\Support\Facades\DB;

/**
 * Feedback by secure link (blueprint 7.7b and 9.2).
 *  - When a request is completed, one secure link is made for it and sent in the client message `sr.completed` (issue()).
 *    It is valid for the company's feedback_link_days (7 by default) and works once. A reopened job gets a new link when it completes again
 *    and the new answer replaces the old one.
 *  - The answer is a star rating (1 to 5) and a score (0 to 10), both required, and an optional comment (answer()).
 *  - The Operations Manager is told (feedback.received); a low rating (1 or 2 stars, or a score of 0 to 6) is Urgent.
 */
class FeedbackLinks
{
    public const PURPOSE = 'client_feedback';
    public const ENTITY  = 'service_request';
    public const DEFAULT_DAYS = 7;

    public function __construct(private SecureLinkService $links)
    {
    }

    public function days(int $tenantId): int
    {
        return (int) (TenantSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->value('feedback_link_days') ?: self::DEFAULT_DAYS);
    }

    /** The client's primary active contact with an email, else the first active contact with one. */
    public function contactFor(ServiceRequest $sr): ?ClientContact
    {
        return ClientContact::withoutGlobalScopes()->where('tenant_id', $sr->tenant_id)->where('client_id', $sr->client_id)
            ->where('is_active', true)->whereNotNull('email')->where('email', '!=', '')
            ->orderByDesc('is_primary')->orderBy('id')->first();
    }

    /** Makes the link and sends the message for a request that has just been completed. Returns the log row, or null when nobody can be written to. */
    public function issue(ServiceRequest $sr): ?MessageLog
    {
        if ($sr->parent_sr_id || $sr->status !== 'completed') {
            return null;                                         // a trade is part of its master: the client answers once, for the master
        }
        $contact = $this->contactFor($sr);
        if (! $contact) {
            return null;
        }

        return app(MessageLogger::class)->send($contact->email, $this->mailFor($sr, $contact->name, (int) $contact->id), 'sr.completed', [
            'tenant_id' => $sr->tenant_id, 'contact_id' => $contact->id, 'related' => $sr,
            'payload'   => ['sr_id' => $sr->id, 'contact_id' => $contact->id, 'contact_name' => $contact->name],      // ids and a name: never the link
        ], throw: false);
    }

    /** Makes a NEW link for the request and builds the message around it. Also used by the retry of a failed message. */
    public function mailFor(ServiceRequest $sr, string $contactName, int $contactId): FeedbackRequestMail
    {
        $days  = $this->days((int) $sr->tenant_id);
        $token = $this->links->createFor((int) $sr->tenant_id, self::PURPOSE, self::ENTITY, (int) $sr->id, $contactId ?: null, $days * 24 * 60);

        return new FeedbackRequestMail($sr, $contactName, TenantUrl::for((int) $sr->tenant_id, '/feedback/' . $token), now()->addDays($days));
    }

    /** What state a link is in: live, used, expired. */
    public function state(SecureLink $link): string
    {
        return $link->used_at ? 'used' : ($link->expires_at->isPast() ? 'expired' : 'live');
    }

    /**
     * Saves the answer once. The link row is locked, so two answers at the same moment cannot both be saved.
     * Returns false when the link was used or expired in the meantime.
     */
    public function answer(SecureLink $link, int $stars, int $score, ?string $comment, string $ip): bool
    {
        $saved = DB::transaction(function () use ($link, $stars, $score, $comment, $ip) {
            $locked = SecureLink::withoutGlobalScopes()->lockForUpdate()->find($link->id);
            if (! $locked || $this->state($locked) !== 'live') {
                return null;
            }

            $sr = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $locked->tenant_id)->findOrFail($locked->entity_id);
            $contact = $locked->client_contact_id ? ClientContact::withoutGlobalScopes()->find($locked->client_contact_id) : null;

            $this->links->consume($locked, $ip);
            $sr->forceFill([
                'client_star_rating'      => $stars,
                'client_nps_score'        => $score,
                'client_feedback_comment' => filled($comment) ? trim($comment) : null,
                'client_feedback_at'      => now(),
                'client_feedback_source'  => 'link',
                'client_feedback_by'      => $contact?->name,
            ])->saveQuietly();

            app(AuditLogger::class)->log('feedback.received', [
                'tenant_id' => $sr->tenant_id, 'item_type' => ServiceRequest::class, 'item_id' => $sr->id,
                'actor'     => ['type' => 'client', 'id' => $contact?->id, 'name' => $contact?->name ?? 'client'],
                'new'       => ['sr_number' => $sr->sr_number, 'stars' => $stars, 'score' => $score, 'comment_given' => filled($comment), 'source' => 'link'],
            ]);

            return $sr;
        });

        if (! $saved) {
            return false;
        }

        $this->tellManagers($saved, $stars, $score);

        return true;
    }

    public static function isLow(int $stars, int $score): bool
    {
        return $stars <= 2 || $score <= 6;
    }

    private function tellManagers(ServiceRequest $sr, int $stars, int $score): void
    {
        $urgent = self::isLow($stars, $score);
        $svc = app(NotificationService::class);
        $svc->notify('feedback.received', $svc->usersWithRole('operations_manager', (int) $sr->tenant_id), [
            'urgent'    => $urgent ? 'Urgent: ' : '',
            'sr_number' => $sr->sr_number,
            'client'    => $sr->client?->name ?? '',
            'stars'     => $stars,
            'score'     => $score,
        ], route('service-requests.show', $sr, false), ['service_request_id' => $sr->id, 'stars' => $stars, 'score' => $score, 'priority' => $urgent ? 'urgent' : 'normal']);
    }
}
