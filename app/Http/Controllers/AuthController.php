<?php
namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Audit\AuditLogger;   // ← added
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter; 
class AuthController extends Controller
{
    public function __construct(private AuditLogger $audit) {}   // ← added

    public function showLogin(Request $request)
    {
        $tenant = Tenant::current();

        if ($tenant) {
            return view('auth.company-login', compact('tenant'));
        }
        return view('auth.platform-login');
    }

    public function login(Request $request)
    {
        $ipKey = 'login-ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $mins = max(1, (int) ceil(RateLimiter::availableIn($ipKey) / 60));
            return back()->withErrors([
                'identifier' => "Too many attempts. Try again in {$mins} minutes.",
            ]);
        }

        $data = $request->validate([
            'identifier' => ['required', 'email', 'max:190'],
            'password'   => ['required', 'string'],
        ], [
            'identifier.required' => 'Email, phone or password is incorrect.',
            'identifier.email'    => 'Email, phone or password is incorrect.',
            'password.required'   => 'Email, phone or password is incorrect.',
        ]);

        $tenant = Tenant::current();
        if (! $tenant) {
            return back()->withErrors(['identifier' => 'Email, phone or password is incorrect.'])
                         ->onlyInput('identifier');
        }

        if (! $tenant->isActive()) {
            return back()->withErrors([
                'identifier' => "{$tenant->name} is not available right now. Please contact Aiywah support.",
            ]);
        }

        $user = \App\Models\User::where('tenant_id', $tenant->id)
            ->where('email', $data['identifier'])
            ->first();

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($ipKey, 15 * 60);        // ← ADD THIS LINE

            if ($user) {
                $user->increment('failed_login_count');
                if ($user->failed_login_count >= 5) {
                    $user->update(['locked_until' => now()->addMinutes(15)]);
                    $this->audit->log('auth.locked', [
                        'tenant_id' => $tenant->id,
                        'item_type' => \App\Models\User::class,
                        'item_id'   => $user->id,
                    ]);
                }
            }
            $this->audit->log('auth.sign_in_failed', [
                'tenant_id' => $tenant->id,
                'new'       => ['identifier_tried' => '[redacted]'],
            ]);
            return back()->withErrors(['identifier' => 'Email, phone or password is incorrect.'])
                        ->onlyInput('identifier');
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            $mins = max(1, $user->locked_until->diffInMinutes(now()));
            return back()->withErrors([
                'identifier' => "Too many attempts. Try again in {$mins} minutes.",
            ]);
        }

        if ($user->deactivated_at) {
            return back()->withErrors([
                'identifier' => 'This account is turned off. Ask your company admin.',
            ]);
        }

        // Client-role accounts don't use password login — direct them to the sign-in link flow.
        $clientCodes = ['client_user', 'client_admin', 'client_viewer'];
        if (in_array($user->role?->code, $clientCodes, true)) {
            return back()->withErrors([
                'identifier' => 'This account signs in using a link emailed to you, not a password. Request a new sign-in link to continue.',
            ])->onlyInput('identifier');
        }

        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $request->session()->put('two_factor_pending', [
                'user_id'  => $user->id,
                'guard'    => 'web',
                'remember' => $request->boolean('remember'),
                'attempts' => 0,
            ]);
            return redirect()->route('two-factor.challenge');
        }

        \Illuminate\Support\Facades\Auth::guard('web')->login($user, $request->boolean('remember'));
        $user->update([
            'last_sign_in_at'    => now(),
            'failed_login_count' => 0,
        ]);
        app(\App\Services\Auth\DeviceService::class)->register($user, $request);
        $this->audit->log('auth.sign_in', [
            'item_type' => \App\Models\User::class,
            'item_id'   => $user->id,
        ]);

        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

         return redirect()->intended($this->redirectAfterSignIn($user)->getTargetUrl());
        
    }

    public function logout(Request $request)
    {
        if (auth()->check()) {
            $this->audit->log('auth.sign_out', [
                'item_type' => \App\Models\User::class,
                'item_id'   => auth()->id(),
            ]);
        }
        app(\App\Services\Auth\DeviceService::class)->markCurrentRevoked($request);
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function showChangePassword()
    {
        abort_unless(auth()->check(), 403);
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current' => ['required', 'string'],
            'new'     => ['required', 'string', 'min:10', 'different:current'],
            'repeat'  => ['required', 'same:new'],
        ], [
            'current.required' => 'Enter your current password.',
            'new.min'          => 'Use at least 10 characters.',
            'new.different'    => 'Choose a password you have not used for this account.',
            'repeat.same'      => 'The two passwords do not match.',
        ]);

        $user = auth()->user();

        if (! \Illuminate\Support\Facades\Hash::check($data['current'], $user->password)) {
            return back()->withErrors(['current' => 'Your current password is incorrect.']);
        }

        $user->update([
            'password'             => \Illuminate\Support\Facades\Hash::make($data['new']),
            'must_change_password' => false,
        ]);

        // ↓ added
        $this->audit->log('auth.password_changed', [
            'item_type' => \App\Models\User::class,
            'item_id'   => $user->id,
        ]);

        return $this->redirectAfterSignIn($user)->with('success', 'Your password was changed.');
    }

    public function showAcceptInvitation(string $token)
{
    $link = app(\App\Services\Auth\SecureLinkService::class)->find($token, 'user_invite');

    if (! $link) {
        return view('auth.link-invalid');
    }

    return view('auth.accept-invitation', compact('token', 'link'));
}

public function acceptInvitation(\Illuminate\Http\Request $request, string $token)
{
    $data = $request->validate([
        'new'    => ['required', 'string', 'min:10'],
        'repeat' => ['required', 'same:new'],
    ], [
        'new.min'     => 'Use at least 10 characters.',
        'repeat.same' => 'The two passwords do not match.',
    ]);

    $linkService = app(\App\Services\Auth\SecureLinkService::class);
    $link = $linkService->find($token, 'user_invite');

    if (! $link) {
        return view('auth.link-invalid');
    }

    $user = $link->user;
    $user->update([
        'password'             => \Illuminate\Support\Facades\Hash::make($data['new']),
        'must_change_password' => false,
        'last_sign_in_at'      => now(),
    ]);

    $linkService->consume($link, $request->ip());
    \Illuminate\Support\Facades\Auth::guard('web')->login($user);

    $this->audit->log('auth.sign_in', [
        'item_type' => \App\Models\User::class,
        'item_id'   => $user->id,
    ]);

    return $this->redirectAfterSignIn($user)->with('success', 'Welcome. Your account is ready.');
}

    private function redirectAfterSignIn(\App\Models\User $user)
    {
        $fieldCodes = ['technician', 'team_lead'];
        if (in_array($user->role?->code, $fieldCodes, true)) {
            return redirect()->route('technician.jobs.index');
        }
        return redirect()->route('tenant.dashboard');
    }
}