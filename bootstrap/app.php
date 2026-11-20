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
        // All global web middleware in ONE call — Laravel 12 only honours the last.
        $middleware->web(append: [
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

        // A guest who opens a client portal page goes to the client sign-in page (the intended address is kept, so signing in lands on that page);
        // everyone else goes to the staff login.
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => $request->is('portal', 'portal/*') ? route('client.sign-in') : route('login'));

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
