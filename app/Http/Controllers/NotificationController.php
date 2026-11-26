<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;

class NotificationController extends Controller
{
    /** D12: new messages for the browser notification script. Only what the user left at "at once" for the browser. */
    public function latest(\Illuminate\Http\Request $request)
    {
        $prefs = app(\App\Services\Notifications\Preferences::class);
        $user  = auth()->user();
        $items = UserNotification::where('user_id', $user->id)->where('id', '>', (int) $request->input('after'))->orderBy('id')->limit(20)->get()
            ->filter(fn ($n) => $prefs->frequency($user, $n->event_key, 'push') === 'instant')
            ->map(fn ($n) => ['id' => $n->id, 'title' => $n->title, 'body' => $n->body, 'url' => \App\Support\TenantUrl::open((int) $n->tenant_id, $n->url)])->values();

        return response()->json(['items' => $items, 'last' => (int) (UserNotification::where('user_id', $user->id)->max('id') ?? 0)]);
    }

    public function index()
    {
        $items = UserNotification::where('user_id', auth()->id())
            ->orderByDesc('id')->paginate(25);

        return view('notifications.index', [
            'items'  => $items,
            'unread' => UserNotification::where('user_id', auth()->id())->unread()->count(),
        ]);
    }

    /** Open an entry: mark it read, then go to its link. */
    public function open(UserNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) auth()->id(), 404);

        if (! $notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        // Rows keep company-relative links: add the company address (path mode /{slug}/...)
        return redirect($notification->url ? \App\Support\TenantUrl::open((int) $notification->tenant_id, $notification->url) : route('notifications.index'));
    }

    public function read(UserNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) auth()->id(), 404);
        $notification->forceFill(['read_at' => now()])->save();

        return back();
    }

    public function readAll()
    {
        UserNotification::where('user_id', auth()->id())->unread()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
