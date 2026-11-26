<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Path mode only, global (runs before routing, so it also sees URLs that match no route).
 *
 *  1. Old company address {slug}.{root host}/anything → {root}/{slug}/anything (301, query kept). Links in emails sent
 *     before the switch, bookmarks, QR codes. Works whenever the wildcard DNS and certificate point here.
 *     www/admin/api/app.{root host} go to the same path on the root.
 *  2. A company path with capital letters (/Skyline/...) → lowercase (301). Cookie paths are case-sensitive.
 *
 * GET and HEAD get 301; other methods 308 (keeps the method and body).
 */
class RedirectLegacyTenantUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Tenancy::isPath()) {
            return $next($request);
        }

        $root = strtolower(Tenancy::rootHost());
        $host = strtolower($request->getHost());

        if ($root !== '' && $host !== $root && str_ends_with($host, '.' . $root)) {
            $sub  = substr($host, 0, -strlen('.' . $root));
            $path = '/' . ltrim($request->getPathInfo(), '/');
            $to   = in_array($sub, ['www', 'admin', 'api', 'app'], true) || ! Tenancy::looksLikeCompany($sub)
                ? $path
                : '/' . $sub . ($path === '/' ? '' : $path);

            return $this->redirect($request, Tenancy::rootUrl() . $to);
        }

        $segment = (string) $request->segment(1);
        if ($segment !== '' && $segment !== strtolower($segment)) {
            $lower = strtolower($segment);
            if (Tenancy::looksLikeCompany($lower) && Tenant::where('slug', $lower)->exists()) {
                $rest = substr(ltrim($request->getPathInfo(), '/'), strlen($segment));   // the segment is plain [A-Za-z0-9-], no encoding

                return $this->redirect($request, Tenancy::rootUrl() . '/' . $lower . $rest);
            }
        }

        return $next($request);
    }

    private function redirect(Request $request, string $url): Response
    {
        $query = $request->getQueryString();

        return redirect()->away($url . ($query ? '?' . $query : ''), $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308);
    }
}
