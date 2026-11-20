<?php

namespace App\Support;

use App\Models\Tenant;

/** A company's own address: {slug}.{APP_URL host}[:port] (CLAUDE.md, Tenancy). Used for links in messages that go outside the app. */
class TenantUrl
{
    public static function for(Tenant|int $tenant, string $path = ''): string
    {
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenant);
        $host   = parse_url(config('app.url'), PHP_URL_HOST);
        $port   = parse_url(config('app.url'), PHP_URL_PORT);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';

        return "{$scheme}://{$tenant->slug}.{$host}" . ($port ? ":{$port}" : '') . '/' . ltrim($path, '/');
    }
}
