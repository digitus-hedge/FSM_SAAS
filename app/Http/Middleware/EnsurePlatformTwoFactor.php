<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePlatformTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('platform')->user();

        if ($user && ! $user->two_factor_confirmed_at) {
            if ($request->routeIs('two-factor.setup', 'two-factor.confirm', 'platform.logout')) {
                return $next($request);
            }
            return redirect()->route('two-factor.setup')
                             ->with('warning', 'Set up two-factor sign-in to continue.');
        }

        return $next($request);
    }
}