<?php

namespace App\Services\Notifications;

use App\Mail\ClientEventMail;
use App\Models\ClientContact;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Feedback\FeedbackLinks;
use App\Services\Messaging\MessageLogger;
use Illuminate\Support\Facades\Log;

/**
 * The events blueprint 9.2 sent by WhatsApp, now sent by email (WhatsApp is paused), to the same recipients.
 * Called from the model listeners in AppServiceProvider, so every screen and job that changes a request sends the same message once.
 *
 *  - Client contact: a ClientEventMail through MessageLogger (message log; fixed, no preferences for clients).
 *  - Technician: NotificationService::notify (in-app row, preferences, the critical rule, message log).
 *
 * A failure here never breaks the action that raised the event.
 * Already built elsewhere: sr.completed (FeedbackLinks), quote.sent, invoice.sent, invoice.overdue, sla.breached, pm.generated, pm.overdue,
 * summary.daily. The visit-moved email of the dispatch calendar is the client's sr.eta_changed (it sets $quietClientEta so it is not sent twice).
 */
class EventMessages
{
    /** Set by the dispatch calendar, which already emails the client when it moves a visit. */
    public static bool $quietClientEta = false;

    /** > 0 while a bulk job runs: the request listeners then send nothing. See mute(). */
    private static int $muted = 0;

    public static function isMuted(): bool
    {
        return self::$muted > 0;
    }

    public static function pushMute(): void
    {
        self::$muted++;
    }

    public static function popMute(): void
    {
        self::$muted = max(0, self::$muted - 1);
    }

    /** Runs a closure with every request listener message switched off (seeders, imports, demo and repair commands). */
    public static function mute(\Closure $fn): mixed
    {
        self::$muted++;
        try {
            return $fn();
        } finally {
            self::$muted--;
        }
    }

    /** Commands that must never send a message. Muted from the moment they start until they finish (AppServiceProvider). */
    public const MUTED_COMMANDS = ['db:seed', 'migrate:fresh', 'migrate:refresh', 'sla:recalculate', 'times:fix-pm-eta', 'demo:remove-billing', 'usage:refresh'];

    /** A request was created. Trades of a master and maintenance requests (pm.generated tells the staff) are not announced to the client. */
    public function onCreated(ServiceRequest $sr): void
    {
        if (self::isMuted() || $sr->parent_sr_id || $sr->source === 'pm_auto') {
            return;
        }
        $this->safely(fn () => $this->client($sr, 'sr.created'));
    }

    /** A request changed. Looks at what changed and sends what the catalogue says. */
    public function onUpdated(ServiceRequest $sr): void
    {
        if (self::isMuted()) {
            return;
        }
        $this->safely(function () use ($sr) {
            $child = (bool) $sr->parent_sr_id;                       // a trade is part of its master: the client hears about the master

            if ($sr->wasChanged('status')) {
                if ($sr->status === 'approved' && ! $child) {
                    $this->client($sr, 'sr.approved');
                }
                if ($sr->status === 'cancelled') {
                    if (! $child) {
                        $this->client($sr, 'sr.cancelled', ['reason' => $sr->cancellation_reason]);
                    }
                    $this->tech($sr, (int) $sr->assigned_tech_user_id, 'sr.cancelled', ['reason' => $sr->cancellation_reason ? 'Reason: ' . $sr->cancellation_reason : '']);
                }
            }

            $prevTech = (int) $sr->getOriginal('assigned_tech_user_id');
            if ($sr->wasChanged('assigned_tech_user_id') && $sr->assigned_tech_user_id) {
                if ($prevTech) {
                    // sr.reassigned: the previous and the new technician (the new one is not also told "assigned")
                    $this->tech($sr, $prevTech, 'sr.reassigned', ['headline' => "{$sr->sr_number} was given to another technician", 'detail' => 'You are no longer assigned to this job.'], false);
                    $this->tech($sr, (int) $sr->assigned_tech_user_id, 'sr.reassigned', ['headline' => "{$sr->sr_number} was reassigned to you", 'detail' => $sr->scheduled_eta_at ? 'Due ' . $sr->scheduled_eta_at->format('j M Y H:i') . '.' : '']);
                } elseif (! $child) {
                    $this->client($sr, 'sr.assigned');           // the technician is told by the sr.assigned listener
                }
            } elseif ($sr->wasChanged('scheduled_eta_at') && $sr->assigned_tech_user_id && $sr->getOriginal('scheduled_eta_at')) {
                if (! $child && ! self::$quietClientEta) {
                    $this->client($sr, 'sr.eta_changed', ['previous' => \Carbon\Carbon::parse($sr->getOriginal('scheduled_eta_at'))->toIso8601String()]);
                }
                $this->tech($sr, (int) $sr->assigned_tech_user_id, 'sr.eta_changed', ['when' => $sr->scheduled_eta_at ? 'Now ' . $sr->scheduled_eta_at->format('j M Y H:i') . '.' : '']);
            }

            if ($sr->wasChanged('first_punch_in_at') && ! $sr->getOriginal('first_punch_in_at') && ! $sr->is_master_sr) {
                $this->client($sr, 'tech.arrived');
            }

            if ($sr->wasChanged('qc_rework_count') && (int) $sr->qc_rework_count > (int) $sr->getOriginal('qc_rework_count') && $sr->assigned_tech_user_id) {
                $this->tech($sr, (int) $sr->assigned_tech_user_id, 'sr.rework', ['reason' => $sr->qc_rework_reason ?: 'No reason was given.']);
            }
        });
    }

    /** One email to the client's contact (the primary active contact with an email, else the first). Returns false when nobody can be written to. */
    public function client(ServiceRequest $sr, string $event, array $extra = []): bool
    {
        $contact = app(FeedbackLinks::class)->contactFor($sr);
        if (! $contact instanceof ClientContact) {
            return false;
        }

        try {
            app(MessageLogger::class)->send($contact->email, new ClientEventMail($sr, $event, $contact->name, $extra), $event, [
                'tenant_id' => $sr->tenant_id, 'contact_id' => $contact->id, 'related' => $sr,
                'payload'   => ['sr_id' => $sr->id, 'event' => $event, 'contact_name' => $contact->name, 'extra' => $extra],   // ids, a name and times: never a link or code
            ], throw: false);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Client email failed', ['sr' => $sr->id, 'event' => $event, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** A message to the technician. Not sent to the person who did the action themselves. */
    private function tech(ServiceRequest $sr, int $techId, string $event, array $vars, bool $skipActor = true): void
    {
        if (! $techId || ($skipActor && auth()->id() === $techId)) {
            return;
        }
        $tech = User::withoutGlobalScopes()->where('tenant_id', $sr->tenant_id)->find($techId);
        if (! $tech) {
            return;
        }
        app(NotificationService::class)->notify($event, [$tech], array_merge([
            'sr_number' => $sr->sr_number, 'site' => $sr->site?->name, 'when' => '', 'reason' => '', 'detail' => '', 'headline' => '',
        ], $vars), route('technician.jobs.show', $sr, false), ['sr_id' => $sr->id]);
    }

    private function safely(\Closure $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('Event email failed', ['error' => $e->getMessage()]);
        }
    }
}
