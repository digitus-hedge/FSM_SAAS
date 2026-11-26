<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * A company's own address, for links in messages that go outside the app (CLAUDE.md, Tenancy).
 *   path mode       scheme://{root host}[:port]/{slug}/{path}
 *   subdomain mode  scheme://{slug}.{root host}[:port]/{path}
 * $path is company-relative ("/service-requests/12"), the form notification rows keep.
 */
class TenantUrl
{
    public static function for(Tenant|int $tenant, string $path = ''): string
    {
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenant);
        $path   = ltrim($path, '/');

        if (Tenancy::isPath()) {
            return Tenancy::rootUrl() . '/' . $tenant->slug . ($path === '' ? '' : '/' . $path);
        }

        $host   = parse_url(config('app.url'), PHP_URL_HOST);
        $port   = parse_url(config('app.url'), PHP_URL_PORT);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';

        return "{$scheme}://{$tenant->slug}.{$host}" . ($port ? ":{$port}" : '') . '/' . $path;
    }

    /**
     * A link as notification rows keep it: company-relative ("/service-requests/12"). Takes what callers pass today
     * (a relative path, "/{slug}/..." built with the path-mode URL defaults, or an absolute URL on the root or a company host)
     * and drops the origin and the company's own prefix. Other absolute URLs are kept as they are.
     */
    public static function relative(Tenant|int $tenant, ?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::find($tenant);
        if (! $tenant) {
            return $url;
        }

        if (preg_match('#^https?://#i', $url)) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $root = strtolower(Tenancy::rootHost());
            if ($host !== $root && $host !== $tenant->slug . '.' . $root) {
                return $url;                                       // somewhere else entirely
            }
            $url = (parse_url($url, PHP_URL_PATH) ?: '/')
                 . (($q = parse_url($url, PHP_URL_QUERY)) ? '?' . $q : '')
                 . (($f = parse_url($url, PHP_URL_FRAGMENT)) ? '#' . $f : '');
        }

        $prefix = '/' . $tenant->slug;
        if ($url === $prefix) {
            return '/';
        }
        if (str_starts_with($url, $prefix . '/') || str_starts_with($url, $prefix . '?')) {
            return substr($url, strlen($prefix));
        }

        return $url;
    }

    /**
     * A company-relative link to a named company route ("/service-requests/12"), from anywhere: a request, a queued job or a command.
     * Path mode fills {tenant} with this company for the one call (no current company needed). For notification links.
     */
    public static function route(Tenant|int $tenant, string $name, mixed $parameters = []): string
    {
        $url = Tenancy::withUrlsFor($tenant, fn () => route($name, $parameters, false));

        return (string) self::relative($tenant, $url);
    }

    /** A stored notification link → where the browser goes. Relative links get the company address; absolute ones stay. */
    public static function open(Tenant|int $tenant, ?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return $stored;
        }

        return str_starts_with($stored, '/') ? self::for($tenant, self::relative($tenant, $stored)) : $stored;
    }
}
