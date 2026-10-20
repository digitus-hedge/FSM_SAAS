<?php
namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $tenant = Tenant::current();

        // On a tenant subdomain → company login carrying the tenant's branding.
        if ($tenant) {
            return view('auth.company-login', compact('tenant'));
        }

        // No tenant context (localhost or admin host) → platform login.
        return view('auth.platform-login');
    }

    public function login(Request $request)
{
    $data = $request->validate([
        'identifier' => ['required', 'email', 'max:190'],
        'password'   => ['required', 'string'],
    ], [
        'identifier.required' => 'Email, phone or password is incorrect.',
        'identifier.email'    => 'Email, phone or password is incorrect.',
        'password.required'   => 'Email, phone or password is incorrect.',
    ]);

    $tenant = \App\Models\Tenant::current();
    if (! $tenant) {
        return back()->withErrors(['identifier' => 'Email, phone or password is incorrect.'])
                     ->onlyInput('identifier');
    }

    // Scope the lookup to this tenant. AT-22: same email in two companies = two users.
    $user = \App\Models\User::where('tenant_id', $tenant->id)
        ->where('email', $data['identifier'])
        ->first();

    if (! $user || ! \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
        return back()->withErrors(['identifier' => 'Email, phone or password is incorrect.'])
                     ->onlyInput('identifier');
    }

    if ($user->deactivated_at) {
        return back()->withErrors(['identifier' => 'Email, phone or password is incorrect.'])
                     ->onlyInput('identifier');
    }

    \Illuminate\Support\Facades\Auth::guard('web')->login($user, $request->boolean('remember'));
    $user->update(['last_sign_in_at' => now()]);

    return redirect()->intended(route('tenant.dashboard'));
}

    public function logout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}