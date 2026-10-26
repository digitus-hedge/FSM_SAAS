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
            \App\Http\Middleware\ForcePasswordChange::class,
            \App\Http\Middleware\TouchDevice::class,
            \App\Http\Middleware\RouteTechnicianMobile::class,

        ]);

        $middleware->alias([
            'platform.admin'      => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'tenant.role'         => \App\Http\Middleware\EnsureTenantRole::class,
            'platform.2fa'        => \App\Http\Middleware\EnsurePlatformTwoFactor::class,   // ← added
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();