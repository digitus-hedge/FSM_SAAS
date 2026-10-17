<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('auth.login');
    }

public function login(Request $request)
{
    $data = $request->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (! Auth::attempt($data, $request->boolean('remember'))) {
        return back()->withErrors(['email' => 'Those credentials do not match any account.'])->onlyInput('email');
    }

    $request->session()->regenerate();
    $user = Auth::user();

    // On a tenant subdomain → tenant dashboard (if the user has access).
    if ($tenant = \App\Models\Tenant::current()) {
        if (! $user->hasAccessToTenant($tenant)) {
            Auth::logout();
            return back()->withErrors(['email' => 'This account does not belong to this workspace.']);
        }
        return redirect()->intended(route('tenant.dashboard'));
    }

    // On the platform host → platform admin.
    if ($user->is_platform_admin) {
        return redirect()->intended(route('admin.dashboard'));
    }

    Auth::logout();
    return back()->withErrors(['email' => 'This account cannot access the platform console.']);
}

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}