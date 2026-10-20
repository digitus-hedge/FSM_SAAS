<?php
// app/Services/Auth/SignInService.php
namespace App\Services\Auth;

use App\Models\{Tenant, User, PlatformUser, AuthCode, UserDevice};
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\{Auth, Hash, RateLimiter};
use Carbon\Carbon;

class SignInService
{
    public function __construct(private AuditLogger $audit) {}

    public function attempt(
        ?Tenant $tenant,
        bool $isPlatformHost,
        string $identifier,
        string $password,
        string $ip,
        ?string $userAgent,
    ): SignInResult {
        // 1. Address-level throttle (D5): 20 fails / 15 min / IP.
        if (RateLimiter::tooManyAttempts("auth:ip:$ip", 20)) {
            $this->audit->log('auth.sign_in_failed', ['reason' => 'ip_throttled', 'ip' => $ip]);
            return SignInResult::locked(RateLimiter::availableIn("auth:ip:$ip") / 60);
        }

        // 2. Find the account. On platform host, look at platform_users.
        //    On tenant host, look at users scoped by tenant_id (never cross-tenant).
        $account = $isPlatformHost
            ? PlatformUser::whereEmail($identifier)->first()
            : $this->findTenantUser($tenant, $identifier);

        // 3. Check password BEFORE revealing anything else.
        if (! $account || ! Hash::check($password, $account->password)) {
            $this->recordFailedAttempt($account, $ip);
            return SignInResult::invalid();
        }

        // 4. Lockout check — AFTER correct password, so lockout message
        //    cannot be used to probe for valid accounts.
        if ($account->locked_until && $account->locked_until->isFuture()) {
            return SignInResult::locked($account->locked_until->diffInMinutes(now()));
        }

        // 5. Company and user state (6.12 step 1).
        if ($account instanceof User) {
            if ($account->tenant->status !== 'active' && $account->tenant->status !== 'trial') {
                return SignInResult::invalid();  // same copy, don't say "suspended"
            }
            if ($account->deactivated_at) {
                return SignInResult::deactivated();  // only now reveal "turned off"
            }
        }

        // 6. Platform admins always need 2FA (D8). Company users: only if confirmed.
        $needs2FA = $isPlatformHost || $account->two_factor_confirmed_at;
        if ($needs2FA) {
            return SignInResult::twoFactorRequired(
                $this->issuePendingToken($account)
            );
        }

        return $this->completeSignIn($account, $tenant, $ip, $userAgent);
    }

    private function findTenantUser(?Tenant $tenant, string $identifier): ?User
    {
        if (! $tenant) return null;
        // Spec: user is found by identifier WITHIN the company only. Never cross-tenant.
        return User::where('tenant_id', $tenant->id)
            ->where(fn($q) => $q->where('email', $identifier)->orWhere('phone', $identifier))
            ->first();
    }

    private function recordFailedAttempt(?object $account, string $ip): void
    {
        RateLimiter::hit("auth:ip:$ip", 15 * 60);
        if ($account) {
            $account->increment('failed_login_count');
            // D5: 5th failure in 15 min locks for 15 min. Window via decayed counter;
            // simplified here, use a RateLimiter per-user key in the real impl.
            if ($account->failed_login_count >= 5) {
                $account->update(['locked_until' => now()->addMinutes(15)]);
                $this->audit->log('auth.locked', ['account_id' => $account->id]);
            }
        }
        $this->audit->log('auth.sign_in_failed', ['identifier_tried' => '[redacted]']);
    }

    private function completeSignIn(
        User|PlatformUser $account,
        ?Tenant $tenant,
        string $ip,
        ?string $userAgent,
    ): SignInResult {
        $account->update([
            'failed_login_count' => 0,
            'last_sign_in_at'    => now(),
        ]);

        $guard = $account instanceof PlatformUser ? 'platform' : 'web';
        Auth::guard($guard)->login($account);

        UserDevice::create([
            'tenant_id'    => $tenant?->id,
            'user_id'      => $account->id,
            'user_type'    => $account::class,
            'label'        => $this->deviceLabel($userAgent),
            'ip'           => $ip,
            'trusted_until'=> $account instanceof User && $account->role->code === 'field_technician'
                              ? now()->addDays(30)
                              : now()->addHours(8),
        ]);

        $this->audit->log('auth.sign_in', ['account_id' => $account->id]);

        $redirect = $account instanceof PlatformUser
            ? route('platform.dashboard')
            : ($account->must_change_password ? route('auth.change-password') : route('tenant.dashboard'));

        return SignInResult::ok($redirect, $account->must_change_password);
    }

    // ... issuePendingToken, deviceLabel, signOut
}