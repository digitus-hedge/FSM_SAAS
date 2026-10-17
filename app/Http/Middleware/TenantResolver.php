<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        $host    = $request->getHost();                        // 127.0.0.1 or acme.aiywah.test
        $appHost = parse_url(config('app.url'), PHP_URL_HOST); // aiywah.test

        // Not a tenant subdomain → treat as platform request.
        // Covers 127.0.0.1, localhost, or the bare app host (aiywah.test).
        if ($host === $appHost || ! str_ends_with($host, '.' . $appHost)) {
            return $next($request);
        }

        $slug = str($host)->before('.' . $appHost)->toString();

        // Reserved subdomains that aren't tenants.
        if (in_array($slug, ['www', 'admin', 'api', 'app'], true)) {
            return $next($request);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant || ! $tenant->isActive()) {
            throw new NotFoundHttpException("No active workspace at '{$slug}'.");
        }

        // Enforce membership if someone is logged in. Platform admins bypass.
        $user = $request->user();
        if ($user && ! $user->hasAccessToTenant($tenant)) {
            abort(403, "You don't have access to this workspace.");
        }

        Tenant::setCurrent($tenant);
        app()->instance(Tenant::class, $tenant);
        $request->attributes->set('tenant', $tenant);

        // Scope all Spatie role/permission checks to this tenant.
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return $next($request);
    }
}