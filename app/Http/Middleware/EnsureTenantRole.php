<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class EnsureTenantRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles)
    {
        $tenant = Tenant::current();
        $user   = $request->user();

        abort_unless($tenant, 404);
        abort_unless($user,   403, 'Sign in to continue.');

        if ($user->is_platform_admin) {
            return $next($request);
        }

        // $user->hasAnyRole already respects the current team scope.
        abort_unless($user->hasAnyRole($allowedRoles), 403, 'Insufficient permissions.');

        return $next($request);
    }
}