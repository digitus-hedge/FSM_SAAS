<?php

namespace App\Services\Notifications;

use App\Mail\DigestMail;
use App\Models\NotificationDigestItem;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the held messages (Reports-Summaries-Preferences spec PRF-02, D15). Hourly digests go at every run, daily digests at the user's
 * own time in the company's time zone. One email lists up to 50 lines, held items older than 7 days are dropped. A failed email is
 * retried at the next run and never sent twice, because an item is marked sent only after the mail was handed over.
 */
class DigestSender
{
    public const MAX_LINES = 50;
    public const KEEP_DAYS = 7;

    /** @return array{sent:int, dropped:int} */
    public function run(Carbon $nowUtc): array
    {
        $out = ['sent' => 0, 'dropped' => 0];

        $out['dropped'] = NotificationDigestItem::withoutGlobalScopes()->whereNull('sent_at')
            ->where('created_at', '<', $nowUtc->copy()->subDays(self::KEEP_DAYS))->delete();

        $due = NotificationDigestItem::withoutGlobalScopes()->whereNull('sent_at')->where('channel', 'email')->get()->groupBy('user_id');
        foreach ($due as $userId => $items) {
            $user = User::withoutGlobalScopes()->find($userId);
            if (! $user || $user->deactivated_at || ! filled($user->email)) {
                continue;
            }
            $tenant = Tenant::find($user->tenant_id);
            $local  = $nowUtc->copy()->setTimezone($tenant?->timezone ?: config('app.timezone'));

            $send = $items->where('frequency', 'hourly_digest');
            if ((int) $local->format('G') === (int) substr((string) ($user->digest_daily_time ?? '08:00:00'), 0, 2)) {
                $send = $send->merge($items->where('frequency', 'daily_digest'));
            }
            if ($send->isEmpty()) {
                continue;
            }

            $send = $send->sortByDesc('created_at')->values();
            try {
                app(\App\Services\Messaging\MessageLogger::class)->send($user->email, new DigestMail($tenant?->name ?? 'your company', $send->take(self::MAX_LINES), max(0, $send->count() - self::MAX_LINES)), 'digest', ['tenant_id' => $tenant?->id, 'user_id' => $user->id]);
                NotificationDigestItem::withoutGlobalScopes()->whereIn('id', $send->pluck('id'))->update(['sent_at' => now()]);
                $out['sent']++;
            } catch (\Throwable $e) {
                Log::warning('Digest failed', ['user' => $userId, 'error' => $e->getMessage()]);
            }
        }

        return $out;
    }
}
