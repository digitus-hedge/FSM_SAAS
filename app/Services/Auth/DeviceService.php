<?php
namespace App\Services\Auth;

use App\Models\{PlatformUser, User, UserDevice};
use Illuminate\Http\Request;

class DeviceService
{
    /**
     * Called right after a successful login.
     * Technician devices trust for 30 days (D4), everyone else 8 hours idle.
     */
    public function register(User|PlatformUser $user, Request $request): UserDevice
    {
        $isTechnician = $user instanceof User && $user->role?->code === 'field_technician';
        $trustedUntil = $isTechnician ? now()->addDays(30) : now()->addHours(8);

        $device = UserDevice::create([
            'tenant_id'     => $user instanceof User ? $user->tenant_id : null,
            'user_type'     => $user instanceof PlatformUser ? 'platform_user' : 'user',
            'user_id'       => $user->id,
            'label'         => $this->labelFromUserAgent($request->userAgent()),
            'ip'            => $request->ip(),
            'user_agent'    => mb_substr((string) $request->userAgent(), 0, 500),
            'session_id'    => $request->session()->getId(),
            'last_used_at'  => now(),
            'trusted_until' => $trustedUntil,
        ]);

        return $device;
    }

    public function markCurrentRevoked(Request $request): void
    {
        UserDevice::where('session_id', $request->session()->getId())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function touch(User|PlatformUser $user, Request $request): void
    {
        $isTechnician = $user instanceof User && $user->role?->code === 'field_technician';

        UserDevice::where('user_type', $user instanceof PlatformUser ? 'platform_user' : 'user')
            ->where('user_id', $user->id)
            ->where('session_id', $request->session()->getId())
            ->whereNull('revoked_at')
            ->update([
                'last_used_at'  => now(),
                // Staff/client sessions slide to +8h from last activity.
                // Technician trust is a fixed 30 days from login, not slid here.
                'trusted_until' => $isTechnician ? \DB::raw('trusted_until') : now()->addHours(8),
            ]);
    }

    private function labelFromUserAgent(?string $ua): string
    {
        if (! $ua) return 'Unknown device';

        $browser = 'Browser';
        if (preg_match('/Edg\/[\d.]+/i', $ua))         { $browser = 'Edge'; }
        elseif (preg_match('/Chrome\/[\d.]+/i', $ua))  { $browser = 'Chrome'; }
        elseif (preg_match('/Firefox\/[\d.]+/i', $ua)) { $browser = 'Firefox'; }
        elseif (preg_match('/Safari\/[\d.]+/i', $ua) && ! preg_match('/Chrome/i', $ua)) { $browser = 'Safari'; }

        $os = 'Unknown OS';
        if     (preg_match('/Windows NT [\d.]+/i', $ua)) $os = 'Windows';
        elseif (preg_match('/Mac OS X/i', $ua))          $os = 'macOS';
        elseif (preg_match('/Android/i', $ua))           $os = 'Android';
        elseif (preg_match('/iPhone|iPad/i', $ua))       $os = 'iOS';
        elseif (preg_match('/Linux/i', $ua))             $os = 'Linux';

        return "{$browser} on {$os}";
    }
}