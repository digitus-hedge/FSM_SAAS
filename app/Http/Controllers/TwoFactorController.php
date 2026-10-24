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

    public function setup()
    {
        $user = $this->activeUser();
        abort_unless($user, 403);

        $secret = $user->two_factor_secret ?: $this->tfa->startSetup($user);
        $qr     = $this->tfa->qrCodeInline(
            config('app.name') . ' · ' . ($user->tenant?->name ?? 'Aiywah FSM'),
            $user->email,
            $secret,
        );

        return view('auth.two-factor-setup', [
            'secret'    => $secret,
            'qrSvg'     => $qr,
            'confirmed' => (bool) $user->two_factor_confirmed_at,
        ]);
    }

    public function confirm(Request $request)
    {
        $user = $this->activeUser();
        abort_unless($user, 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], ['code.digits' => 'Enter the 6-digit code from your app.']);

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
    \Log::debug('2FA challenge diagnostic', [
        'host' => $request->getHost(),
        'tenant_id' => \App\Models\Tenant::current()?->id,
        'pending_exists' => $request->session()->has('two_factor_pending'),
        'pending' => $request->session()->get('two_factor_pending'),
        'web_authenticated' => Auth::guard('web')->check(),
        'platform_authenticated' => Auth::guard('platform')->check(),
        'session_id' => $request->session()->getId(),
    ]);

    $pendingKey = $request->session()->get('two_factor_pending');

    abort_unless($pendingKey, 403);

    return view('auth.two-factor-challenge');
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
                'item_type' => $user ? $user::class : null,
                'item_id' => $user?->id,
                'new' => ['reason' => 'too_many_wrong_two_factor_codes'],
            ]);

            return redirect()
                ->route($guard === 'platform' ? 'platform.login' : 'login')
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

    // Authenticate only after the 2FA code is verified.
    Auth::guard($guard)->login(
        $user,
        (bool) ($pending['remember'] ?? false)
    );

    // Regenerate the session once after authentication.
    $request->session()->regenerate();

    // Remove temporary 2FA state and any stale intended URL.
    $request->session()->forget('two_factor_pending');
    $request->session()->forget('url.intended');

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

    return redirect()->route(
        $guard === 'platform' ? 'admin.dashboard' : 'tenant.dashboard'
    );
}

}