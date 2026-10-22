<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PlatformUser;
use App\Services\Auth\DeviceService;        // ← added
use App\Services\Auth\PlatformSignInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformAuthView extends Controller
{
    public function __construct(
        private PlatformSignInService $signIn,
        private DeviceService $devices,       // ← added
    ) {}

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

        if ($result['status'] === 'ok') {
            $pu = PlatformUser::where('email', $data['identifier'])->first();

            // 2FA enrolled → sign out of the guard and go to the challenge.
            if ($pu && $pu->two_factor_confirmed_at) {
                Auth::guard('platform')->logout();
                $request->session()->put('two_factor_pending', [
                    'guard'   => 'platform',
                    'user_id' => $pu->id,
                ]);
                return redirect()->route('two-factor.challenge');
            }

            // No 2FA (D8 mandatory for platform admins — EnsurePlatformTwoFactor
            // middleware will redirect them to setup after this).
            if ($pu) {
                $this->devices->register($pu, $request);
            }
            return redirect()->intended($result['redirect']);
        }

        return match ($result['status']) {
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
            $this->devices->markCurrentRevoked($request);   // ← added
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('platform.login');
    }
}