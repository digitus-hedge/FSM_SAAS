<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class PlatformHostOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(Tenant::current(), 404);
        return $next($request);
    }
}
