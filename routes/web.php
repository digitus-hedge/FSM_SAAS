<?php

/*
| Web routes (CLAUDE.md, Tenancy). Order matters: the root and console routes are registered first, so /login, /admin/... and
| /up are never taken by the company group. The {tenant} requirement also refuses every reserved first segment (config/tenancy.php).
|
|   routes/central.php   root: central sign-in, its two-factor step, old root links, /dev/emails
|   routes/platform.php  /admin: the platform console
|   routes/tenant.php    every company page: under /{tenant} in path mode, unprefixed in subdomain mode
*/

use App\Support\Tenancy;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/central.php';
require __DIR__ . '/platform.php';

if (Tenancy::isPath()) {
    Route::prefix('{tenant}')->where(['tenant' => Tenancy::routePattern()])->group(__DIR__ . '/tenant.php');
} else {
    require __DIR__ . '/tenant.php';
}
