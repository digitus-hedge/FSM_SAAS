<?php

/*
| How a company is found in a request (CLAUDE.md, Tenancy).
|
|   path       fsm.aiywah.com/{slug}/...   (default). Root = central login, /admin = platform console.
|   subdomain  {slug}.fsm.aiywah.com/...   (the earlier layout; needs wildcard DNS and certificate).
*/
return [

    'mode' => env('TENANCY_MODE', 'path') === 'subdomain' ? 'subdomain' : 'path',

    /*
    | First path segments (path mode) and subdomains that are never a company address.
    | Every first segment of a route outside the company group, and every top-level entry of public/, must be here:
    | tests/Feature/Tenancy/PathTenancyTest checks it. CompanyAddress refuses these as a new company's address.
    */
    'reserved' => [
        // earlier go-live list (TEN-D2)
        'admin', 'www', 'api', 'app', 'mail', 'webmail', 'smtp', 'ftp', 'cpanel', 'portal',
        'support', 'help', 'docs', 'status', 'static', 'assets', 'cdn', 'dev', 'staging',
        'test', 'localhost', 'ns1', 'ns2', 'platform', 'login', 'aiywah',
        // root and console paths of path mode
        'auth', 'logout', 'two-factor', 'profile', 'invitation', 'up', 'i', 'q', 'sanctum',
        // public/ and build output
        'storage', 'build', 'js', 'css', 'vendor', 'images', 'favicon.ico', 'robots.txt', 'index.php',
    ],

    // Session cookies in path mode: one per company (path /{slug}), one for the console (path /admin), one for the root (path /).
    'cookies' => [
        'company_prefix' => 'fsm_co_',
        'console'        => 'fsm_console',
        'central'        => 'fsm_central',
    ],
];
