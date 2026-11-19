<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\FormException;

/**
 * Personal notification settings (Reports-Summaries-Preferences spec PRF-01 to PRF-03, D12 to D17).
 * Channels: email, in_app, push (a browser notification while the app is open). No WhatsApp.
 * In-app messages are always written, so the bell stays current; the preference decides email and push.
 */
class Preferences
{
    /** Blueprint 9.2: the only events a user cannot turn off or delay. */
    public const CRITICAL = ['sr.assigned', 'sla.breached'];
    public const CHANNELS = ['email', 'in_app', 'push'];
    public const FREQUENCIES = ['instant', 'hourly_digest', 'daily_digest', 'off'];

    private array $cache = [];

    public function isCritical(string $event): bool
    {
        return in_array($event, self::CRITICAL, true);
    }

    /** What the user chose for one event and channel; instant when nothing was chosen. */
    public function frequency(User $user, string $event, string $channel): string
    {
        if (! isset($this->cache[$user->id])) {
            $this->cache[$user->id] = NotificationPreference::withoutGlobalScopes()->where('user_id', $user->id)->get()
                ->mapWithKeys(fn ($p) => [$p->event_key . '|' . $p->channel => $p->frequency])->all();
        }
        $f = $this->cache[$user->id][$event . '|' . $channel] ?? 'instant';

        // a critical event is never delayed
        if ($this->isCritical($event) && in_array($f, ['hourly_digest', 'daily_digest'], true)) {
            return 'instant';
        }

        return $f;
    }

    public function forget(User $user): void
    {
        unset($this->cache[$user->id]);
    }

    /** A user's own choice. Client users have no preferences (D16). */
    public function set(User $user, string $event, string $channel, string $frequency): void
    {
        if (in_array($user->role?->code, ['client_user', 'client_admin', 'client_viewer'], true)) {
            throw new FormException('PREFERENCE_NOT_ALLOWED', 'Client messages are fixed.', 403);
        }
        if (! in_array($channel, self::CHANNELS, true) || ! in_array($frequency, self::FREQUENCIES, true)) {
            throw new FormException('PREFERENCE_INVALID', 'Choose a valid setting.');
        }
        if ($channel !== 'email' && in_array($frequency, ['hourly_digest', 'daily_digest'], true)) {
            throw new FormException('PREFERENCE_INVALID', 'A digest is only for email.');
        }

        if ($this->isCritical($event)) {
            $message = 'This message cannot be turned off. You can choose email or in-app.';
            if (in_array($frequency, ['hourly_digest', 'daily_digest'], true)) {
                throw new FormException('PREFERENCE_CRITICAL', $message);
            }
            // at least one channel stays instant
            if ($frequency === 'off') {
                $others = collect(self::CHANNELS)->reject(fn ($c) => $c === $channel)->filter(fn ($c) => $this->frequency($user, $event, $c) === 'instant');
                if ($others->isEmpty()) {
                    throw new FormException('PREFERENCE_CRITICAL', $message);
                }
            }
        }

        NotificationPreference::withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $user->id, 'event_key' => $event, 'channel' => $channel],
            ['tenant_id' => $user->tenant_id, 'frequency' => $frequency]
        );
        $this->forget($user);

        app(AuditLogger::class)->log('notification_preference.changed', [
            'tenant_id' => $user->tenant_id, 'item_type' => User::class, 'item_id' => $user->id,
            'new' => ['event' => $event, 'channel' => $channel, 'frequency' => $frequency],
        ]);
    }

    /** The events a user can set: those of the catalogue. */
    public function events(): array
    {
        return array_keys(config('notifications.events') ?? []);
    }
}
