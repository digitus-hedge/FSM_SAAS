<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Path mode: old {slug}.host links and /Capital/ company paths are redirected before routing (also for URLs that match no route).
        $middleware->prepend(\App\Http\Middleware\RedirectLegacyTenantUrls::class);

        // All global web middleware in ONE call — Laravel 12 only honours the last.
        // ConfigureSessionCookie picks the session cookie (per company, console, root) and must run before StartSession.
        $middleware->web(prepend: [
            \App\Http\Middleware\ConfigureSessionCookie::class,
        ], append: [
            \App\Http\Middleware\TenantResolver::class,
            \App\Http\Middleware\EnforceImpersonation::class,
            \App\Http\Middleware\EnsureVendorActive::class,
            \App\Http\Middleware\ForcePasswordChange::class,
            \App\Http\Middleware\TouchDevice::class,
            \App\Http\Middleware\RouteTechnicianMobile::class,
        ]);

        // The company must be known BEFORE route model binding, so every BelongsToTenant model loaded from the URL is scoped by company.
        // The web group puts SubstituteBindings right after sign-in; the resolver is placed just before it (after the session and sign-in check it needs).
        $middleware->prependToPriorityList(before: \Illuminate\Routing\Middleware\SubstituteBindings::class, prepend: \App\Http\Middleware\TenantResolver::class);
        $middleware->prependToPriorityList(before: \Illuminate\Session\Middleware\StartSession::class, prepend: \App\Http\Middleware\ConfigureSessionCookie::class);

        // A guest who opens a client portal page goes to the client sign-in page (the intended address is kept, so signing in lands on that page);
        // other company pages go to the company's staff login, console pages to the console sign-in, anything else to the central login.
        // The sign-in check runs before TenantResolver, so path mode reads the company from the {tenant} route parameter here.
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if (! \App\Support\Tenancy::isPath()) {
                return $request->routeIs('portal.*') ? route('client.sign-in') : route('login');
            }
            if ($slug = $request->route()?->parameter('tenant')) {
                return route($request->routeIs('portal.*') ? 'client.sign-in' : 'login', ['tenant' => $slug]);
            }
            if ($request->is('admin', 'admin/*')) {
                return route('platform.login');
            }

            return route('central.login');
        });

        $middleware->alias([
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'tenant.role'    => \App\Http\Middleware\EnsureTenantRole::class,
            'platform.2fa'   => \App\Http\Middleware\EnsurePlatformTwoFactor::class,
            'role'           => \App\Http\Middleware\EnsureRole::class,
            'platform.host'  => \App\Http\Middleware\PlatformHostOnly::class,
            'level'          => \App\Http\Middleware\EnsurePermissionLevel::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
