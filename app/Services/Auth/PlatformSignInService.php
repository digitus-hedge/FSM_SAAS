<?php
namespace App\Services\Auth;

use App\Models\PlatformUser;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\{Auth, Hash, RateLimiter};

class PlatformSignInService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Attempt to sign in a platform admin.
     *
     * @return array{status: 'ok'|'invalid'|'locked', redirect?: string, minutes?: int}
     */
    public function attempt(string $email, string $password, string $ip, ?string $userAgent): array
    {
        // IP-level throttle (D5): 20 failed attempts / 15 min from one address.
        if (RateLimiter::tooManyAttempts("platform-login:ip:$ip", 20)) {
            $this->audit->log('auth.sign_in_failed', ['reason' => 'ip_throttled', 'ip' => $ip]);
            $retryIn = RateLimiter::availableIn("platform-login:ip:$ip");
            return ['status' => 'locked', 'minutes' => max(1, (int) ceil($retryIn / 60))];
        }

        $user = PlatformUser::where('email', $email)->first();

        // Same generic failure for unknown user AND wrong password — never leak which (D5).
        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit("platform-login:ip:$ip", 15 * 60);
            if ($user) {
                $this->recordFailedAttempt($user);
            }
            $this->audit->log('auth.sign_in_failed', ['email' => $email, 'ip' => $ip]);
            return ['status' => 'invalid'];
        }

        // Account-level lockout check — only revealed AFTER correct password, so lockout
        // state can't be used to probe for valid accounts.
        if ($user->locked_until && $user->locked_until->isFuture()) {
            return ['status' => 'locked', 'minutes' => max(1, $user->locked_until->diffInMinutes(now()))];
        }

        if ($user->deactivated_at) {
            return ['status' => 'invalid'];
        }

        // Success — reset counters, log in, audit.
        $user->update(['failed_login_count' => 0, 'last_sign_in_at' => now()]);
        Auth::guard('platform')->login($user, remember: false);
        $this->audit->log('auth.sign_in', ['account_id' => $user->id, 'guard' => 'platform']);

        return ['status' => 'ok', 'redirect' => route('admin.dashboard')];
    }

    public function signOut(PlatformUser $user): void
    {
        Auth::guard('platform')->logout();
        $this->audit->log('auth.sign_out', ['account_id' => $user->id]);
    }

    private function recordFailedAttempt(PlatformUser $user): void
    {
        $user->increment('failed_login_count');
        // D5: 5th failure in 15 min locks for 15 min.
        if ($user->failed_login_count >= 5) {
            $user->update(['locked_until' => now()->addMinutes(15)]);
            $this->audit->log('auth.locked', ['account_id' => $user->id]);
        }
    }
}