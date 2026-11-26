<?php
namespace App\Http\Controllers;

use App\Services\Audit\AuditLogger;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash};

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $tfa,
        private AuditLogger $audit,
    ) {}

    private function activeUser(): mixed
    {
        if (\App\Models\Tenant::current()) {
            return Auth::guard('web')->user();
        }

        return Auth::guard('platform')->user()
            ?? Auth::guard('web')->user();
    }

    private function activeGuard(): string
    {
        if (\App\Models\Tenant::current()) {
            return 'web';
        }

        return Auth::guard('platform')->check()
            ? 'platform'
            : 'web';
    }

    /**
     * Route name of a two-factor page here: the console (platform.two-factor.*), the central sign-in (central.two-factor.challenge|verify,
     * no company) or the company (two-factor.*). An empty $name gives the prefix, for the views.
     */
    private function tfaRoute(string $name, ?string $guard = null): string
    {
        $guard ??= $this->activeGuard();
        $prefix = match (true) {
            $guard === 'platform' => 'platform.two-factor.',
            ! \App\Models\Tenant::current() && in_array($name, ['challenge', 'verify'], true) => 'central.two-factor.',
            default => 'two-factor.',
        };

        return $prefix . $name;
    }

    /** Session key for a reset in progress: the new secret waits here until it is confirmed, the old one stays active. */
    private const RESET_KEY = 'two_factor_reset';

    /** The pending reset secret, only when it belongs to the signed-in user and guard. */
    private function pendingReset(Request $request, mixed $user): ?string
    {
        $p = $request->session()->get(self::RESET_KEY);
        if (! is_array($p) || ($p['guard'] ?? null) !== $this->activeGuard() || (int) ($p['user_id'] ?? 0) !== (int) $user->id) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($p['secret']);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setup(Request $request)
    {
        $user = $this->activeUser();
        abort_unless($user, 403);

        $pending = $user->two_factor_confirmed_at ? $this->pendingReset($request, $user) : null;

        // Two-factor is on and no reset was verified: show the status only, never the QR code or the secret.
        if ($user->two_factor_confirmed_at && ! $pending) {
            return view('auth.two-factor-setup', [
                'tfa'         => $this->tfaRoute(''),
                'confirmed'   => true,
                'confirmedAt' => $user->two_factor_confirmed_at,
                'secret'      => null,
                'qrSvg'       => null,
                'resetting'   => false,
            ]);
        }

        $secret = $pending ?: ($user->two_factor_secret ?: $this->tfa->startSetup($user));
        $qr     = $this->tfa->qrCodeInline(
            config('app.name') . ' · ' . ($user->tenant?->name ?? 'Aiywah FSM'),
            $user->email,
            $secret,
        );

        return view('auth.two-factor-setup', [
                'tfa'         => $this->tfaRoute(''),
            'secret'      => $secret,
            'qrSvg'       => $qr,
            'confirmed'   => false,
            'confirmedAt' => null,
            'resetting'   => (bool) $pending,
        ]);
    }

    /** Start a reset: the current password and a current code first, then a new setup is shown. */
    public function reset(Request $request)
    {
        $user = $this->activeUser();
        abort_unless($user, 403);
        if (! $user->two_factor_confirmed_at) {
            return redirect()->route($this->tfaRoute('setup'));
        }

        $data = $request->validate([
            'current' => ['required', 'string'],
            'code'    => ['required', 'string', 'digits:6'],
        ], ['code.digits' => 'Enter the 6-digit code from your app.']);

        if (! Hash::check($data['current'], $user->password)) {
            return back()->withErrors(['current' => 'Your current password is incorrect.']);
        }
        if (! $this->tfa->verify($user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => 'That code is not right. Try the next code from your app.']);
        }

        $request->session()->put(self::RESET_KEY, [
            'guard'   => $this->activeGuard(),
            'user_id' => $user->id,
            'secret'  => \Illuminate\Support\Facades\Crypt::encryptString($this->tfa->generateSecret()),
        ]);

        return redirect()->route($this->tfaRoute('setup'));
    }

    public function confirm(Request $request)
    {
        $user = $this->activeUser();
        abort_unless($user, 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], ['code.digits' => 'Enter the 6-digit code from your app.']);

        // A verified reset: the new secret replaces the old one only once its code is right.
        if ($user->two_factor_confirmed_at) {
            $pending = $this->pendingReset($request, $user);
            if (! $pending) {
                return redirect()->route($this->tfaRoute('setup'));
            }
            if (! $this->tfa->verify($pending, $data['code'])) {
                return back()->withErrors(['code' => 'That code is not right. Try the next code from your app.']);
            }
            $user->update(['two_factor_secret' => $pending, 'two_factor_confirmed_at' => now()]);
            $request->session()->forget(self::RESET_KEY);
            $this->audit->log('auth.two_factor_reset', [
                'item_type' => $user::class,
                'item_id'   => $user->id,
            ]);

            return redirect()->route($this->tfaRoute('setup'))->with('success', 'Two-factor sign-in is on with your new app.');
        }

        if (! $this->tfa->confirm($user, $data['code'])) {
            return back()->withErrors(['code' => 'That code is not right. Try the next code from your app.']);
        }

        $this->audit->log('auth.two_factor_enabled', [
            'item_type' => $user::class,
            'item_id'   => $user->id,
        ]);

        $target = $this->activeGuard() === 'platform'
            ? route('admin.dashboard')
            : route('tenant.dashboard');

        return redirect()->intended($target)->with('success', 'Two-factor sign-in is on.');
    }

    public function disable(Request $request)
    {
        $user = $this->activeUser();
        abort_unless($user, 403);

        $data = $request->validate([
            'current' => ['required', 'string'],
            'code'    => ['required', 'string', 'digits:6'],
        ]);

        if (! Hash::check($data['current'], $user->password)) {
            return back()->withErrors(['current' => 'Your current password is incorrect.']);
        }
        if (! $this->tfa->verify($user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => 'That code is not right. Try the next code from your app.']);
        }

        // D8: platform admins require 2FA — refuse disable for them.
        if ($this->activeGuard() === 'platform') {
            return back()->withErrors(['current' => 'Two-factor is required for platform admins.']);
        }

        $this->tfa->disable($user);
        $this->audit->log('auth.two_factor_disabled', [
            'item_type' => $user::class,
            'item_id'   => $user->id,
        ]);

        return back()->with('success', 'Two-factor sign-in is off.');
    }

    // ─────────── Challenge after password ───────────


public function challenge(Request $request)
{
    $pendingKey = $request->session()->get('two_factor_pending');

    abort_unless($pendingKey, 403);

    $guard = $pendingKey['guard'] ?? 'web';

    return view('auth.two-factor-challenge', ['verifyRoute' => $this->tfaRoute('verify', $guard)]);
}


public function verifyChallenge(Request $request)
{
    $data = $request->validate([
        'code' => ['required', 'string', 'digits:6'],
    ]);

    $pending = $request->session()->get('two_factor_pending');

    abort_unless($pending, 403);

    $guard = $pending['guard'] ?? 'web';

    if (! in_array($guard, ['web', 'platform'], true)) {
        abort(403);
    }

    $user = $guard === 'platform'
        ? \App\Models\PlatformUser::find($pending['user_id'])
        : \App\Models\User::find($pending['user_id']);

    if (! $user || ! $this->tfa->verify($user->two_factor_secret, $data['code'])) {
        $attempts = ($pending['attempts'] ?? 0) + 1;

        if ($attempts >= 3) {
            if ($user) {
                $user->increment('failed_login_count');
            }

            $request->session()->forget('two_factor_pending');

            $this->audit->log('auth.sign_in_failed', [
                'actor' => $user ? ['type' => $guard === 'platform' ? 'platform_user' : 'user', 'id' => $user->id, 'name' => $user->name] : null,
                'item_type' => $user ? $user::class : null,
                'item_id' => $user?->id,
                'new' => ['reason' => 'too_many_wrong_two_factor_codes'],
            ]);

            return redirect()
                ->route($guard === 'platform' ? 'platform.login' : (\App\Models\Tenant::current() ? 'login' : 'central.login'))
                ->withErrors([
                    'identifier' => 'Too many wrong codes. Sign in again.',
                ]);
        }

        $pending['attempts'] = $attempts;
        $request->session()->put('two_factor_pending', $pending);

        return back()->withErrors([
            'code' => 'That code is not right. Try the next code from your app.',
        ]);
    }

    // Central sign-in (no company address yet): hand the verified user to their company address.
    if ($guard === 'web' && ! \App\Models\Tenant::current()) {
        $request->session()->forget('two_factor_pending');

        return app(CentralLoginController::class)->handoff($user, (bool) ($pending['remember'] ?? false));
    }

    // Authenticate only after the 2FA code is verified.
    Auth::guard($guard)->login(
        $user,
        (bool) ($pending['remember'] ?? false)
    );

    // Regenerate the session once after authentication.
    $request->session()->regenerate();

    // Remove temporary 2FA state and any stale intended URL.
    $request->session()->forget('two_factor_pending');

    $user->update([
        'last_sign_in_at' => now(),
        'failed_login_count' => 0,
    ]);

    app(\App\Services\Auth\DeviceService::class)
        ->register($user, $request);

    $this->audit->log('auth.sign_in', [
        'item_type' => $user::class,
        'item_id' => $user->id,
        'new' => ['two_factor' => true],
    ]);

        // Same rules as a normal sign-in: a forced password change comes first.
    if ($guard === 'web' && ! empty($user->must_change_password)) {
        return redirect()->route('password.change');
    }

    return redirect()->intended(
        route($guard === 'platform' ? 'admin.dashboard' : 'tenant.dashboard')
    );
}

}
