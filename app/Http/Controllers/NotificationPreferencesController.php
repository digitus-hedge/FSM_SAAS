<?php

namespace App\Http\Controllers;

use App\Services\Notifications\Preferences;
use Illuminate\Http\Request;

/**
 * "Notifications" under the profile (Reports-Summaries-Preferences spec PRF-01, PRF-03, D12 to D17). A user changes only their own settings.
 * Client users have none: their emails are fixed (the route group keeps them out).
 */
class NotificationPreferencesController extends Controller
{
    public function index(Preferences $prefs)
    {
        $user = auth()->user();
        $events = collect(config('notifications.events') ?? [])->map(fn ($def, $key) => [
            'key' => $key, 'title' => $def['label'] ?? $key, 'critical' => $prefs->isCritical($key), 'email' => (bool) ($def['email'] ?? false),
            'choice' => collect(Preferences::CHANNELS)->mapWithKeys(fn ($c) => [$c => $prefs->frequency($user, $key, $c)])->all(),
        ])->values();

        return view('notifications.preferences', ['events' => $events, 'user' => $user]);
    }

    public function update(Request $request, Preferences $prefs)
    {
        $user = $request->user();
        $request->validate([
            'prefs' => ['nullable', 'array'], 'prefs.*' => ['array'],
            'digest_daily_time' => ['nullable', 'date_format:H:i'],
            'daily_summary_on' => ['nullable', 'boolean'],
        ]);

        foreach ((array) $request->input('prefs', []) as $event => $channels) {
            foreach ((array) $channels as $channel => $frequency) {
                $prefs->set($user, (string) $event, (string) $channel, (string) $frequency);
            }
        }
        $changes = ['daily_summary_on' => $request->boolean('daily_summary_on')];
        if ($request->filled('digest_daily_time')) {
            $changes['digest_daily_time'] = $request->input('digest_daily_time') . ':00';
        }
        $user->forceFill($changes)->save();

        return back()->with('success', 'Your notification settings are saved.');
    }
}
