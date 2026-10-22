<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('web')->user();

        if ($user && $user->must_change_password) {
            // Allow the password-change pages and logout; block everything else.
            if ($request->routeIs('password.change', 'password.change.submit', 'logout')) {
                return $next($request);
            }
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}