<?php

namespace App\Services\Notifications;

use App\Mail\NotificationMail;
use App\Models\NotificationDigestItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * C1 notification foundation: an in-app entry plus an email for each recipient.
 * A failure here must never break the action that raised the event, so everything is wrapped.
 */
class NotificationService
{
    /**
     * @param iterable<User> $users  recipients (duplicates are removed)
     * @param array          $vars   values for the {placeholders} of the event text
     */
    public function notify(string $event, iterable $users, array $vars = [], ?string $url = null, array $data = []): void
    {
        try {
            // Not config("notifications.events.{$event}"): event names contain dots (invoice.overdue), and config()
            // would read them as nested keys and find nothing.
            $def = (config('notifications.events') ?? [])[$event] ?? null;
            if (! $def) {
                return;
            }

            $title = $this->fill($def['title'], $vars);
            $body  = $this->fill($def['body'] ?? '', $vars);

            // the email shows the request's details: remember which request the message is about
            if (! isset($data['sr_id']) && ! empty($vars['sr_number'])) {
                $users = $users instanceof Collection || is_array($users) ? $users : iterator_to_array($users, false);   // a generator can be read once
                $tid = collect($users)->filter()->first()?->tenant_id;
                $srId = $tid ? \App\Models\ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tid)->where('sr_number', $vars['sr_number'])->value('id') : null;
                if ($srId) {
                    $data['sr_id'] = $srId;
                }
            }

            $seen = [];
            foreach ($users as $user) {
                if (! $user || isset($seen[$user->id])) {
                    continue;
                }
                $seen[$user->id] = true;

                $n = UserNotification::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id'   => $user->id,
                    'event_key' => $event,
                    'title'     => $title,
                    'body'      => $body ?: null,
                    'url'       => $url,
                    'data'      => $data ?: null,
                ]);

                $freq = ($def['email'] ?? false) ? app(Preferences::class)->frequency($user, $event, 'email') : 'off';
                if (in_array($freq, ['hourly_digest', 'daily_digest'], true)) {
                    // held for the digest (PRF-02): the in-app message above is already written
                    NotificationDigestItem::create([
                        'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'event_key' => $event, 'channel' => 'email',
                        'frequency' => $freq, 'title' => $title, 'body' => $body ?: null, 'link' => $url, 'created_at' => now(),
                    ]);
                } elseif ($freq === 'instant' && filled($user->email)) {
                    // logged in the message log whether it works or not; a failure never breaks the action that raised the event
                    $log = app(\App\Services\Messaging\MessageLogger::class)->send($user->email, new NotificationMail($n), $event, [
                        'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'related' => $n, 'payload' => ['notification_id' => $n->id],
                    ], throw: false);
                    if ($log->status === 'sent') {
                        $n->forceFill(['emailed_at' => now()])->save();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Notification failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    /** Active users of the current company who hold a permission. */
    public function usersWhoCan(string $permission, ?int $tenantId = null): Collection
    {
        $tenantId ??= Tenant::current()?->id;

        return User::withoutGlobalScopes()->where('tenant_id', $tenantId)->get()
            ->filter(fn ($u) => ($u->is_active ?? true) && $u->canDo($permission))
            ->values();
    }

    /** Users of the current company with a role code, for example 'ops_manager'. */
    public function usersWithRole(string $code, ?int $tenantId = null): Collection
    {
        $tenantId ??= Tenant::current()?->id;

        return User::withoutGlobalScopes()->where('tenant_id', $tenantId)->with('role')->get()
            ->filter(fn ($u) => ($u->is_active ?? true) && $u->role?->code === $code)
            ->values();
    }

    private function fill(string $text, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }
        return $text;
    }
}
