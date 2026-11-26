<?php
namespace App\Http\Controllers;

use App\Models\{Tenant, User};
use App\Services\Audit\AuditLogger;
use App\Services\Auth\DeviceService;
use App\Support\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Cache, RateLimiter};
use Illuminate\Support\Str;

/**
 * One sign-in address for every company (the root address, no tenant).
 *
 *   1. attempt()  — email + password, checked against every company that has this email.
 *   2. choose()   — picker, only when more than one company matches.
 *   3. handoff()  — one-time token (60 s) → redirect to the company's own address.
 *   4. consume()  — runs on the company address: token → signed-in session there.
 *
 * Sessions stay per company: a cookie per company path (path mode) or per company host (subdomain mode), so two companies never mix.
 */
class CentralLoginController extends Controller
{
    private const CLIENT_CODES = ['client_user', 'client_admin', 'client_viewer'];
    private const PICK_TTL_MIN = 5;
    private const TOKEN_TTL_SEC = 60;
    // A valid bcrypt hash of a throw-away string — keeps timing similar when the email is unknown.
    private const DUMMY_HASH = '$2y$12$rJ1z/5/qs.KQd3b5RxWYTuwlfqslRqMqRJ2gGIVizG.xJqnvPVVgG';

    public function __construct(private AuditLogger $audit) {}

    public function show()
    {
        abort_if(Tenant::current(), 404);
        return view('auth.central-login');
    }

    public function attempt(Request $request)
    {
        abort_if(Tenant::current(), 404);

        $ipKey = 'login-ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $mins = max(1, (int) ceil(RateLimiter::availableIn($ipKey) / 60));
            return back()->withErrors(['identifier' => "Too many attempts. Try again in {$mins} minutes."]);
        }

        $data = $request->validate([
            'identifier' => ['required', 'email', 'max:190'],
            'password'   => ['required', 'string'],
        ], [
            'identifier.required' => 'Email or password is incorrect.',
            'identifier.email'    => 'Email or password is incorrect.',
            'password.required'   => 'Email or password is incorrect.',
        ]);

        $rows = User::with(['tenant', 'role'])
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['identifier'])])
            ->get();

        if ($rows->isEmpty()) {
            password_verify($data['password'], self::DUMMY_HASH);
        }

        // Rows whose password matches. Only these decide what the person can open.
        $passwordOk = $rows->filter(fn (User $u) => \Illuminate\Support\Facades\Hash::check($data['password'], $u->password));

        if ($passwordOk->isEmpty()) {
            RateLimiter::hit($ipKey, 15 * 60);
            foreach ($rows as $u) {
                $u->increment('failed_login_count');
                if ($u->failed_login_count >= 5) {
                    $u->update(['locked_until' => now()->addMinutes(15)]);
                    $this->audit->log('auth.locked', [
                        'tenant_id' => $u->tenant_id,
                        'item_type' => User::class,
                        'item_id'   => $u->id,
                    ]);
                }
                $this->audit->log('auth.sign_in_failed', [
                    'tenant_id' => $u->tenant_id,
                    'actor'     => ['type' => 'user', 'id' => $u->id, 'name' => $u->name],
                    'new'       => ['identifier_tried' => '[redacted]', 'channel' => 'central_login'],
                ]);
            }
            return back()->withErrors(['identifier' => 'Email or password is incorrect.'])->onlyInput('identifier');
        }

        // Of the password matches, keep the ones that can really sign in.
        $usable = collect();
        $reason = null;
        foreach ($passwordOk as $u) {
            if (! $u->tenant) {
                continue;
            }
            if (! $u->tenant->isActive()) {
                $reason ??= "{$u->tenant->name} is not available right now. Please contact Aiywah support.";
                continue;
            }
            if ($u->locked_until && $u->locked_until->isFuture()) {
                $mins = max(1, $u->locked_until->diffInMinutes(now()));
                $reason ??= "Too many attempts. Try again in {$mins} minutes.";
                continue;
            }
            if ($u->deactivated_at) {
                $reason ??= 'This account is turned off. Ask your company admin.';
                continue;
            }
            if (in_array($u->role?->code, self::CLIENT_CODES, true)) {
                $reason ??= 'This account signs in using a link emailed to you, not a password. Request a new sign-in link to continue.';
                continue;
            }
            $usable->push($u);
        }

        if ($usable->isEmpty()) {
            return back()->withErrors(['identifier' => $reason ?? 'Email or password is incorrect.'])->onlyInput('identifier');
        }

        $remember = $request->boolean('remember');

        if ($usable->count() === 1) {
            return $this->proceed($request, $usable->first(), $remember);
        }

        // More than one company — let the person pick. Remember only the ids, for a few minutes.
        $request->session()->put('central_login_pending', [
            'user_ids' => $usable->pluck('id')->all(),
            'remember' => $remember,
            'expires'  => now()->addMinutes(self::PICK_TTL_MIN)->timestamp,
        ]);

        return view('auth.choose-workspace', ['users' => $usable->sortBy(fn ($u) => $u->tenant->name)->values()]);
    }

    public function choose(Request $request)
    {
        abort_if(Tenant::current(), 404);

        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $pending = $request->session()->get('central_login_pending');
        if (! $pending || ($pending['expires'] ?? 0) < now()->timestamp
            || ! in_array((int) $data['user_id'], $pending['user_ids'], true)) {
            $request->session()->forget('central_login_pending');
            return redirect()->route('central.login')->withErrors(['identifier' => 'Please sign in again.']);
        }

        $request->session()->forget('central_login_pending');

        $user = User::with('tenant')->findOrFail($data['user_id']);

        return $this->proceed($request, $user, (bool) ($pending['remember'] ?? false));
    }

    /** 2FA first (if this account has it), then the hand-off. */
    private function proceed(Request $request, User $user, bool $remember): RedirectResponse
    {
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $request->session()->put('two_factor_pending', [
                'user_id'  => $user->id,
                'guard'    => 'web',
                'remember' => $remember,
                'attempts' => 0,
            ]);
            return redirect()->route('central.two-factor.challenge');
        }

        return $this->handoff($user, $remember);
    }

    /** Called here, and by TwoFactorController after a correct code. */
    public function handoff(User $user, bool $remember, array $extra = []): RedirectResponse
    {
        $token = Str::random(48);

        // $extra carries impersonation_session_id for a support session (Services/Platform/Impersonation)
        Cache::put('central_login:' . hash('sha256', $token), [
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
            'remember'  => $remember,
        ] + $extra, now()->addSeconds(self::TOKEN_TTL_SEC));

        $user->loadMissing('tenant');

        // The company's own address (path mode /{slug}/..., subdomain mode {slug}.host/...)
        return redirect()->away(TenantUrl::for($user->tenant, '/auth/handoff/' . $token));
    }

    /** Runs on the company's own address. */
    public function consume(Request $request, string $token)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $payload = Cache::pull('central_login:' . hash('sha256', $token));

        $user = $payload ? User::find($payload['user_id']) : null;

        if (! $payload
            || (int) $payload['tenant_id'] !== (int) $tenant->id
            || ! $user
            || (int) $user->tenant_id !== (int) $tenant->id
            || ! $user->isActive()) {
            return redirect()->route('login')->withErrors(['identifier' => 'Your sign-in expired. Please sign in again.']);
        }

        // A support session: the handoff names the session, which must still be open and be for this user and company.
        $impId = $payload['impersonation_session_id'] ?? null;
        $imp   = $impId ? \App\Models\ImpersonationSession::find($impId) : null;
        if ($impId && (! $imp || ! $imp->isActive() || (int) $imp->target_user_id !== (int) $user->id || (int) $imp->tenant_id !== (int) $tenant->id)) {
            return redirect()->route('login')->withErrors(['identifier' => 'The support session has ended.']);
        }

        Auth::guard('web')->login($user, $imp ? false : (bool) $payload['remember']);
        $request->session()->regenerate();

        if ($imp) {
            $request->session()->put('impersonation_session_id', $imp->id);   // the audit rows of this request and later ones carry the session
        } else {
            $user->update([
                'last_sign_in_at'    => now(),
                'failed_login_count' => 0,
            ]);

            app(DeviceService::class)->register($user, $request);
        }

        $this->audit->log('auth.sign_in', [
            'item_type' => User::class,
            'item_id'   => $user->id,
            'new'       => ['channel' => $imp ? 'support_session' : 'central_login'],
        ]);

        if ($user->must_change_password && ! $imp) {
            return redirect()->route('password.change');
        }

        $code = $user->role?->code;
        if ($code === 'team_lead') {
            return redirect()->route('technician.team.desktop');
        }
        if ($code === 'field_technician') {
            return redirect()->route('technician.jobs.mobile');
        }

        return redirect()->intended(route('tenant.dashboard'));
    }
}
