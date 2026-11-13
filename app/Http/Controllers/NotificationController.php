<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;

class NotificationController extends Controller
{
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

        return redirect($notification->url ?: route('notifications.index'));
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
