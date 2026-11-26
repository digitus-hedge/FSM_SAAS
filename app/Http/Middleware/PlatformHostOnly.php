<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;

/**
 * Console routes only off a company address. Path mode: the console lives at /admin, which the {tenant} group can never match,
 * so there is nothing to refuse. Subdomain mode: 404 when the host is a company.
 */
class PlatformHostOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(! Tenancy::isPath() && Tenant::current(), 404);
        return $next($request);
    }
}
