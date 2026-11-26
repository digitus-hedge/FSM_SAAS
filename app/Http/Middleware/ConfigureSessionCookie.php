<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Path mode: one session cookie per company, so companies stay apart as they did on separate hosts (CLAUDE.md, Tenancy).
 *   /{slug}/...  fsm_co_{slug}, path /{slug}
 *   /admin/...   fsm_console,   path /admin
 *   anything else (root: central login)  fsm_central, path /
 * Runs before StartSession (first in the web group and in the priority list). The remember-me cookie follows the same path.
 * Subdomain mode: nothing changes (host-only cookies, one per host).
 */
class ConfigureSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Tenancy::isPath()) {
            return $next($request);
        }

        $cookies = config('tenancy.cookies');
        $segment = (string) $request->segment(1);

        if ($segment === 'admin') {
            [$name, $path] = [$cookies['console'], '/admin'];
        } elseif (Tenancy::looksLikeCompany($segment) && ($tenant = Tenant::where('slug', $segment)->first())) {
            $request->attributes->set('tenancy.tenant', $tenant);       // TenantResolver reuses it (one query)
            [$name, $path] = [$cookies['company_prefix'] . $tenant->slug, '/' . $tenant->slug];
        } else {
            [$name, $path] = [$cookies['central'], '/'];
        }

        config(['session.cookie' => $name, 'session.path' => $path]);

        app('cookie')->setDefaultPathAndDomain($path, config('session.domain'), config('session.secure'), config('session.same_site'));
        app('session')->driver()->setName($name);                         // in case the store was built before this point

        return $next($request);
    }
}
