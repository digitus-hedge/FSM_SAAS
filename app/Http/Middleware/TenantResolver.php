<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Finds the company of a request (CLAUDE.md, Tenancy). Runs before SubstituteBindings (bootstrap/app.php).
 *   path mode       the {tenant} route parameter (first path segment). Routes outside the company group have none: root or console.
 *   subdomain mode  {slug}.{APP_URL host}; the bare host and www/admin/api/app are the root.
 * Unknown or suspended company: 404. A signed-in user of another company is signed out and sent to this company's sign-in.
 */
class TenantResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        return Tenancy::isPath() ? $this->byPath($request, $next) : $this->byHost($request, $next);
    }

    private function byPath(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $slug  = $route?->parameter('tenant');

        if ($slug === null) {
            return $next($request);                                  // root or platform console
        }

        $tenant = $request->attributes->get('tenancy.tenant');       // looked up by ConfigureSessionCookie
        if (! $tenant instanceof Tenant || $tenant->slug !== $slug) {
            $tenant = Tenant::where('slug', $slug)->first();
        }
        if (! $tenant || ! $tenant->isActive()) {
            throw new NotFoundHttpException("No active workspace at '{$slug}'.");
        }

        // Controllers never receive the company as an argument, and their own parameters keep their positions.
        $route->forgetParameter('tenant');

        return $this->enter($request, $next, $tenant);
    }

    private function byHost(Request $request, Closure $next): Response
    {
        $host    = $request->getHost();
        $appHost = Tenancy::rootHost();

        if ($host === $appHost || ! str_ends_with($host, '.' . $appHost)) {
            return $next($request);
        }

        $slug = str($host)->before('.' . $appHost)->toString();

        if (in_array($slug, ['www', 'admin', 'api', 'app'], true)) {
            return $next($request);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant || ! $tenant->isActive()) {
            throw new NotFoundHttpException("No active workspace at '{$slug}'.");
        }

        return $this->enter($request, $next, $tenant);
    }

    private function enter(Request $request, Closure $next, Tenant $tenant): Response
    {
        Tenant::setCurrent($tenant);                                 // also the path-mode URL default for {tenant}
        app()->instance(Tenant::class, $tenant);
        $request->attributes->set('tenant', $tenant);

        // Signed in, but not as a user of this company: sign out and go to this company's sign-in.
        $user = Auth::guard('web')->user();
        if ($user && ! $user->hasAccessToTenant($tenant)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['identifier' => "Please sign in to {$tenant->name}."]);
        }

        // Scope all Spatie role/permission checks to this tenant.
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return $next($request);
    }
}
