<?php
namespace App\Http\Controllers;

use App\Models\PlatformUser;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class ProfileDevicesController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    private function user(): User|PlatformUser|null
    {
        if (\App\Models\Tenant::current()) {
            return Auth::guard('web')->user() ?? Auth::guard('platform')->user();
        }
        return Auth::guard('platform')->user() ?? Auth::guard('web')->user();
    }

    private function userType(): string
    {
        if (\App\Models\Tenant::current() && Auth::guard('web')->check()) {
            return 'user';
        }
        return Auth::guard('platform')->check() ? 'platform_user' : 'user';
    }

    public function index(Request $request)
    {
        $user = $this->user();
        abort_unless($user, 403);

        $devices = UserDevice::where('user_type', $this->userType())
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('trusted_until', '>', now())
            ->orderByDesc('last_used_at')
            ->get();

        $currentSessionId = $request->session()->getId();

        return view('profile.devices', compact('devices', 'currentSessionId'));
    }

    public function revoke(Request $request, UserDevice $device)
    {
        $user = $this->user();
        abort_unless($user && $device->user_id === $user->id && $device->user_type === $this->userType(), 403);

        $device->update(['revoked_at' => now()]);

        // Kill the Laravel session so that device is signed out immediately.
        if ($device->session_id) {
            DB::table('sessions')->where('id', $device->session_id)->delete();
        }

        $this->audit->log('user.devices_revoked', [
            'item_type' => $user::class,
            'item_id'   => $user->id,
            'new'       => ['device_id' => $device->id, 'label' => $device->label],
        ]);

        return back()->with('success', "Device {$device->label} was signed out.");
    }

    public function revokeOthers(Request $request)
    {
        $user = $this->user();
        abort_unless($user, 403);

        $others = UserDevice::where('user_type', $this->userType())
            ->where('user_id', $user->id)
            ->where('session_id', '!=', $request->session()->getId())
            ->whereNull('revoked_at')
            ->get();

        $sessionIds = $others->pluck('session_id')->filter()->all();

        UserDevice::whereIn('id', $others->pluck('id'))->update(['revoked_at' => now()]);
        if (! empty($sessionIds)) {
            DB::table('sessions')->whereIn('id', $sessionIds)->delete();
        }

        $count = $others->count();

        $this->audit->log('user.devices_revoked', [
            'item_type' => $user::class,
            'item_id'   => $user->id,
            'new'       => ['scope' => 'all_others', 'count' => $count],
        ]);

        return back()->with('success', "Signed out of {$count} other device" . ($count === 1 ? '' : 's') . '.');
    }
}