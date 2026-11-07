<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DenyClientRole
{
    public function handle(Request $request, Closure $next)
    {
        $clientCodes = ['client_user', 'client_admin', 'client_viewer'];
        abort_if(in_array($request->user()?->role?->code, $clientCodes, true), 403);
        return $next($request);
    }
}