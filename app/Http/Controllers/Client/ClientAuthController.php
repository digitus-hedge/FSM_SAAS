<?php
namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\ClientSignInLinkMail;
use App\Models\{Tenant, User};
use App\Services\Audit\AuditLogger;
use App\Services\Auth\SecureLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log, Mail, RateLimiter};

class ClientAuthController extends Controller
{
    public function __construct(
        private SecureLinkService $links,
        private AuditLogger $audit,
    ) {}

    /* ========== Sign-in form ========== */

    public function showSignIn(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        if (! $this->tenantSupportsClientSignIn($tenant)) {
            return view('client.auth.error', [
                'tenant'   => $tenant,
                'errorKey' => 'starter',
            ]);
        }

        return view('client.auth.sign-in', ['tenant' => $tenant]);
    }

    /* ========== Request a sign-in link ========== */

    public function requestLink(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        if (! $this->tenantSupportsClientSignIn($tenant)) {
            return redirect()->route('client.sign-in');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);
        $email = mb_strtolower($data['email']);

        // Rate limit — 3 per email per hour, 20 per IP per hour (TEN-D5)
        $emailKey = 'client-signin-email:' . $email;
        $ipKey    = 'client-signin-ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($emailKey, 3) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            // Still show the generic "sent" page — don't reveal rate limit hit either.
            return redirect()->route('client.sign-in.sent')->with('sent_to', $email);
        }
        RateLimiter::hit($emailKey, 60 * 60);
        RateLimiter::hit($ipKey,    60 * 60);

        // Look up a client user with that email in this tenant.
        $user = User::where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('deactivated_at')
            ->whereHas('role', fn($q) => $q->whereIn('code', ['client_user', 'client_admin', 'client_viewer']))
            ->first();

        // Privacy: always redirect to the same "sent" page regardless of whether the user exists.
        if ($user && $tenant->isActive()) {
            try {
                $token = $this->links->create($user, 'client_sign_in', 24 * 60);
                $url   = $this->buildPortalUrl($tenant, "/client/sign-in/{$token}");

                Mail::to($user->email)->send(
                    (new ClientSignInLinkMail($user, $tenant, $url))->afterCommit()
                );

                $this->audit->log('auth.sign_in_link_requested', [
                    'tenant_id' => $tenant->id,
                    'item_type' => User::class,
                    'item_id'   => $user->id,
                    'new'       => ['channel' => 'client_sign_in_link'],
                ]);
            } catch (\Throwable $e) {
                Log::error("Client sign-in email failed for {$email}: {$e->getMessage()}");
            }
        }

        return redirect()->route('client.sign-in.sent')->with('sent_to', $email);
    }

    public function showSent(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        return view('client.auth.sent', [
            'tenant' => $tenant,
            'email'  => session('sent_to') ?? '',
        ]);
    }

    /* ========== Verify and sign in ========== */

    public function verifyLink(Request $request, string $token)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $link = $this->links->find($token, 'client_sign_in');

        if (! $link) {
            return view('client.auth.error', [
                'tenant'   => $tenant,
                'errorKey' => 'invalid',
            ]);
        }

        if ($link->consumed_at ?? null) {
            return view('client.auth.error', [
                'tenant'   => $tenant,
                'errorKey' => 'used',
                'usedAt'   => $link->consumed_at,
            ]);
        }

        if ($link->expires_at && $link->expires_at->isPast()) {
            return view('client.auth.error', [
                'tenant'   => $tenant,
                'errorKey' => 'expired',
                'sentAt'   => $link->created_at,
            ]);
        }

        $user = $link->user;

        if (! $user || $user->tenant_id !== $tenant->id || $user->deactivated_at) {
            return view('client.auth.error', [
                'tenant'   => $tenant,
                'errorKey' => 'invalid',
            ]);
        }

        $this->links->consume($link, $request->ip());

        Auth::guard('web')->login($user, false);
        $user->update([
            'last_sign_in_at'    => now(),
            'failed_login_count' => 0,
        ]);

        app(\App\Services\Auth\DeviceService::class)->register($user, $request);
        $this->audit->log('auth.sign_in', [
            'tenant_id' => $tenant->id,
            'item_type' => User::class,
            'item_id'   => $user->id,
            'new'       => ['channel' => 'client_sign_in_link'],
        ]);

        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'))
            ->with('welcome', true);
    }

    /* ========== Dashboard ========== */

    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $tenant = Tenant::current();

        abort_unless($user && $tenant, 404);

        // Must be a client user (not staff)
        $clientCodes = ['client_user', 'client_admin', 'client_viewer'];
        abort_unless(in_array($user->role?->code, $clientCodes, true), 403);

        // Pull their recent service requests (scoped by client_id if the user is attached to a client)
        $requests = \App\Models\ServiceRequest::where('tenant_id', $tenant->id)
            ->where('client_id', $user->client_id ?? 0)
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        return view('client.dashboard', compact('user', 'tenant', 'requests'));
    }

    public function signOut(Request $request)
    {
        if (auth()->check()) {
            $this->audit->log('auth.sign_out', [
                'item_type' => User::class,
                'item_id'   => auth()->id(),
                'new'       => ['channel' => 'client_portal'],
            ]);
        }
        app(\App\Services\Auth\DeviceService::class)->markCurrentRevoked($request);
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.sign-in');
    }

    /* ========== Helpers ========== */

    private function tenantSupportsClientSignIn(Tenant $tenant): bool
    {
        if (! $tenant->isActive()) return false;
        $planCode = $tenant->plan?->code;
        return in_array($planCode, ['professional', 'enterprise'], true);
    }

    private function buildPortalUrl(Tenant $tenant, string $path): string
    {
        $host   = parse_url(config('app.url'), PHP_URL_HOST);
        $port   = parse_url(config('app.url'), PHP_URL_PORT);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $portPart = $port ? ":{$port}" : '';
        return "{$scheme}://{$tenant->slug}.{$host}{$portPart}{$path}";
    }
}