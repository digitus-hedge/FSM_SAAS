<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every office route sits behind sign-in, and the open routes are exactly the ones on the list below (CLAUDE.md, Known issues).
 * A signed-out visitor is sent to sign in: never a 403, 404 or 500 page that gives the existence of a screen away or breaks.
 * No database is needed: the sign-in check comes before any controller.
 */
class RouteAuthTest extends TestCase
{
    /** Open routes on the root address and the console (no company). Add to these lists only on purpose. */
    private const PUBLIC_ROOT_ROUTES = [
        'up',                              // health check
        '/',                               // redirects to the central sign-in (or the console)
        'sanctum/csrf-cookie',
        'admin/login',                     // platform owner sign-in
        'admin/two-factor/challenge',      // second step of the platform owner sign-in
        'login', 'login/choose',           // central sign-in and its workspace picker
        'two-factor/challenge',            // second step of the central sign-in
        'storage/{path}',                  // public files
    ];

    /** Path mode only: old root invoice and quote links, sent on to the company address (LegacyLinkController). */
    private const PUBLIC_LEGACY_ROUTES = ['i/{token}', 'i/{token}/pdf', 'q/{token}', 'q/{token}/pdf'];

    /** Open routes on a company address (path mode: under {tenant}/). */
    private const PUBLIC_COMPANY_ROUTES = [
        '/',                               // redirects to sign-in or the right dashboard
        'login',                           // company sign-in
        'auth/handoff/{token}',            // one-time hand-off after the central sign-in
        'login/technician', 'login/team-lead', 'field/login', 'field/login/team-lead',
        'invitation/{token}',              // accept an invitation
        'q/{token}', 'q/{token}/pdf', 'q/{token}/accept', 'q/{token}/reject',   // client quote link
        'feedback/{token}',                // client feedback link (GET and POST, rate limited)
        'i/{token}', 'i/{token}/pdf',      // client invoice link
        'client/sign-in', 'client/sign-in/sent', 'client/sign-in/{token}',      // client sign-in by link
        'two-factor/challenge',            // second step of a sign-in that is already under way
    ];

    /** @return list<string> every open route as route:list prints its URI, for the current tenancy mode */
    private function publicRoutes(): array
    {
        if (! \App\Support\Tenancy::isPath()) {
            return array_values(array_unique(array_merge(self::PUBLIC_ROOT_ROUTES, self::PUBLIC_COMPANY_ROUTES)));
        }

        $company = array_map(fn ($u) => $u === '/' ? '{tenant}' : '{tenant}/' . $u, self::PUBLIC_COMPANY_ROUTES);

        return array_merge(self::PUBLIC_ROOT_ROUTES, self::PUBLIC_LEGACY_ROUTES, $company);
    }

    private function behindSignIn(Route $route): bool
    {
        foreach (RouteFacade::gatherRouteMiddleware($route) as $mw) {
            if (is_string($mw) && (str_starts_with($mw, 'auth') || str_contains($mw, 'Authenticate'))) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function every_route_is_behind_sign_in_unless_it_is_on_the_public_list(): void
    {
        $open = [];
        foreach (RouteFacade::getRoutes() as $route) {
            if (! $this->behindSignIn($route) && ! in_array($route->uri(), $this->publicRoutes(), true)) {
                $open[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        $this->assertSame([], $open, "Routes outside sign-in that are not on the public list:\n" . implode("\n", $open));
    }

    #[Test]
    public function the_public_list_holds_no_route_that_does_not_exist(): void
    {
        $uris = collect(RouteFacade::getRoutes())->map->uri()->all();

        foreach ($this->publicRoutes() as $uri) {
            $this->assertContains($uri, $uris, "{$uri} is on the public list but is not a route");
        }
    }

    #[Test]
    public function a_signed_out_visitor_is_sent_to_sign_in_on_every_screen_that_needs_it(): void
    {
        $wrong = [];
        $count = 0;

        foreach (RouteFacade::getRoutes() as $route) {
            if (! $this->behindSignIn($route) || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            // {tenant} gets a company-shaped address: the sign-in check comes before the company is looked up
            $uri = '/' . ltrim(preg_replace('/\{[^}]+\}/', '1', str_replace('{tenant}', 'acme', $route->uri())), '/');
            $res = $this->get('http://localhost' . $uri);
            $count++;

            if (! $res->isRedirect()) {
                $wrong[] = "GET {$uri} gave {$res->getStatusCode()}";
            }
        }

        $this->assertGreaterThan(100, $count);
        $this->assertSame([], $wrong, implode("\n", $wrong));
    }

    #[Test]
    public function a_signed_out_post_to_an_office_route_is_also_turned_away_without_an_error_page(): void
    {
        foreach (['/admin/invoices/1/approve', '/admin/quotes/1/send', '/admin/vendors', '/admin/hold-reasons', '/admin/expenses-review/1/approve',
                  '/manage/users', '/manage/roles', '/notifications/read-all'] as $uri) {
            $status = $this->post('http://localhost' . (\App\Support\Tenancy::isPath() ? '/acme' : '') . $uri)->getStatusCode();
            $this->assertContains($status, [302, 419], "POST {$uri} gave {$status}"); // redirect to sign-in, or the CSRF check first
        }
    }
}
