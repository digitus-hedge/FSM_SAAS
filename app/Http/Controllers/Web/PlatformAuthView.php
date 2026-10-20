<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Auth\PlatformSignInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformAuthView extends Controller
{
    public function __construct(private PlatformSignInService $signIn) {}

    public function login()
    {
        if (Auth::guard('platform')->check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('auth.platform-login');
    }

    public function loginStub(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'email', 'max:190'],
            'password'   => ['required', 'string'],
        ], [
            // Deliberately uniform message for both missing fields.
            'identifier.required' => 'Email, phone or password is incorrect.',
            'identifier.email'    => 'Email, phone or password is incorrect.',
            'password.required'   => 'Email, phone or password is incorrect.',
        ]);

        $result = $this->signIn->attempt(
            email:     $data['identifier'],
            password:  $data['password'],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return match ($result['status']) {
            'ok'     => redirect()->intended($result['redirect']),
            'locked' => back()->withErrors([
                'identifier' => "Too many attempts. Try again in {$result['minutes']} minutes.",
            ])->onlyInput('identifier'),
            default  => back()->withErrors([
                'identifier' => 'Email, phone or password is incorrect.',
            ])->onlyInput('identifier'),
        };
    }

    public function logout(Request $request)
    {
        $user = Auth::guard('platform')->user();
        if ($user) {
            $this->signIn->signOut($user);
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('platform.login');
    }
}