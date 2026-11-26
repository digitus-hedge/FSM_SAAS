<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\URL;

/** The tenancy mode (config/tenancy.php) and the helpers every part of the app shares. CLAUDE.md, Tenancy. */
class Tenancy
{
    /** Characters of a company address in a URL (CompanyAddress is stricter for new companies). */
    public const SLUG_PATTERN = '[a-z0-9][a-z0-9-]*';

    public static function isPath(): bool
    {
        return config('tenancy.mode') !== 'subdomain';
    }

    /** @return list<string> */
    public static function reserved(): array
    {
        return array_values(array_unique(array_map('strtolower', (array) config('tenancy.reserved', []))));
    }

    public static function isReserved(string $segment): bool
    {
        return in_array(strtolower($segment), self::reserved(), true);
    }

    /** Could this first path segment be a company address? (format and not reserved; the database decides the rest) */
    public static function looksLikeCompany(string $segment): bool
    {
        return $segment !== '' && preg_match('/^' . self::SLUG_PATTERN . '$/', $segment) === 1 && ! self::isReserved($segment);
    }

    /** The {tenant} route requirement: a company address, never a reserved first segment (so /login, /admin, /up never reach the company group). */
    public static function routePattern(): string
    {
        $words = implode('|', array_map(fn ($w) => preg_quote($w, '#'), self::reserved()));

        return '(?!(?:' . $words . ')(?:/|$))' . self::SLUG_PATTERN;
    }

    /**
     * Path mode: route() fills {tenant} with this company's address (null clears it). Tenant::setCurrent($tenant) calls it, so the
     * resolver, jobs and commands that set the current company build company links without passing the company. Subdomain mode: nothing to do.
     */
    public static function applyUrlDefaults(?Tenant $tenant): void
    {
        if (self::isPath()) {
            URL::defaults(['tenant' => $tenant?->slug]);
        }
    }

    /** Set the URL defaults (not the current company, which turns on the model scope) for $tenant while $fn runs, then put back what was there. */
    public static function withUrlsFor(Tenant|int|null $tenant, callable $fn): mixed
    {
        if (! self::isPath()) {
            return $fn();
        }
        $tenant   = is_int($tenant) ? Tenant::find($tenant) : $tenant;
        $previous = URL::getDefaultParameters()['tenant'] ?? null;
        URL::defaults(['tenant' => $tenant?->slug]);
        try {
            return $fn();
        } finally {
            URL::defaults(['tenant' => $previous]);
        }
    }

    /** Is route() building company URLs right now? (path mode: a {tenant} URL default is set; subdomain mode: a company host) */
    public static function hasCompanyUrls(): bool
    {
        return self::isPath() ? ! empty(URL::getDefaultParameters()['tenant']) : (bool) Tenant::current();
    }

    /** The sign-in page for wherever this request is: the company, the console or the central sign-in. For pages shown anywhere (errors). */
    public static function signInUrl(): string
    {
        if (! self::isPath() || self::hasCompanyUrls()) {
            return route('login');
        }

        return request()->is('admin', 'admin/*') ? route('platform.login') : route('central.login');
    }

    /** "Back to dashboard" for wherever this request is. */
    public static function dashboardUrl(bool $platform): string
    {
        if ($platform) {
            return route('admin.dashboard');
        }

        return self::hasCompanyUrls() ? route('tenant.dashboard') : self::signInUrl();
    }

    /** url() for a company path ("/admin/quotes/build") on the current company's address. For JS bases that append ids. */
    public static function url(string $path): string
    {
        $slug = Tenant::current()?->slug;

        return url(self::isPath() && $slug !== null ? '/' . $slug . '/' . ltrim($path, '/') : $path);
    }

    /** The request path inside the company ("technician/jobs"): path mode drops the leading "{slug}/". For $request->is()-style checks. */
    public static function companyPath(\Illuminate\Http\Request $request): string
    {
        $path = trim($request->path(), '/');
        $slug = Tenant::current()?->slug;

        if (self::isPath() && $slug !== null) {
            if ($path === $slug) {
                return '';
            }
            if (str_starts_with($path, $slug . '/')) {
                return substr($path, strlen($slug) + 1);
            }
        }

        return $path;
    }

    /** The root address, without a trailing slash: scheme://host[:port]. */
    public static function rootUrl(): string
    {
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $host   = parse_url(config('app.url'), PHP_URL_HOST);
        $port   = parse_url(config('app.url'), PHP_URL_PORT);

        return "{$scheme}://{$host}" . ($port ? ":{$port}" : '');
    }

    public static function rootHost(): string
    {
        return (string) parse_url(config('app.url'), PHP_URL_HOST);
    }
}
