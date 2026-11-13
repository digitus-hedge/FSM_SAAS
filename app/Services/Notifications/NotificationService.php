<?php

namespace App\Services\Notifications;

use App\Mail\NotificationMail;
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
            $def = config("notifications.events.{$event}");
            if (! $def) {
                return;
            }

            $title = $this->fill($def['title'], $vars);
            $body  = $this->fill($def['body'] ?? '', $vars);

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

                if (($def['email'] ?? false) && filled($user->email)) {
                    try {
                        Mail::to($user->email)->send(new NotificationMail($n));
                        $n->forceFill(['emailed_at' => now()])->save();
                    } catch (\Throwable $e) {
                        Log::warning('Notification email failed', ['event' => $event, 'user' => $user->id, 'error' => $e->getMessage()]);
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
