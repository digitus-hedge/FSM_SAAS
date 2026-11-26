<?php

namespace Tests\Feature\Tenancy;

use App\Mail\InvoiceSentMail;
use App\Models\Tenant;
use App\Models\UserNotification;
use App\Rules\CompanyAddress;
use App\Services\Messaging\MessageLogger;
use App\Services\Notifications\NotificationService;
use App\Support\Tenancy;
use App\Support\TenantUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Currency\BillingTestCase;

/**
 * Path tenancy (CLAUDE.md, Tenancy): fsm.aiywah.com/{slug}/... for companies, /admin for the console, the root for the central sign-in.
 * The company from the path, per-company session cookies, the membership check, reserved first segments, the central hand-off,
 * links built outside a request, notification links, and the redirects of old addresses.
 */
class PathTenancyTest extends BillingTestCase
{
    use RefreshDatabase;

    private Tenant $a;
    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(Tenancy::isPath(), 'phpunit.xml runs the suite in path mode');
        $this->a = $this->makeTenant(slug: 'skyline');
        $this->b = $this->makeTenant(slug: 'harbour');
    }

    private function root(string $path = ''): string
    {
        return Tenancy::rootUrl() . $path;
    }

    // ── the company from the path ─────────────────────────────────────────────

    #[Test]
    public function the_first_path_segment_is_the_company(): void
    {
        $user = $this->makeUser($this->a, 'company_admin');

        $this->actingAs($user)->get($this->root('/skyline/dashboard'))->assertOk();
        $this->assertSame($this->a->id, Tenant::current()?->id);
        $this->assertSame($this->root('/skyline/dashboard'), route('tenant.dashboard'));   // the URL default follows the company
    }

    #[Test]
    public function an_unknown_or_suspended_company_is_a_404(): void
    {
        $this->get($this->root('/nobody/login'))->assertNotFound();

        $this->b->update(['status' => 'suspended']);
        $this->get($this->root('/harbour/login'))->assertNotFound();
    }

    #[Test]
    public function controller_arguments_do_not_shift_with_the_company_prefix(): void
    {
        $user = $this->makeUser($this->a, 'company_admin');
        $client = $this->makeClient($this->a);
        $sr = $this->makeServiceRequest($this->a, $client, 'in_progress');

        $this->actingAs($user)->get(route('service-requests.show', $sr))->assertOk()->assertSee($sr->sr_number);
        $this->assertSame($this->root('/skyline/service-requests/' . $sr->id), route('service-requests.show', $sr));
    }

    // ── root and console never reach the company group ───────────────────────

    #[Test]
    public function login_admin_and_up_never_reach_the_company_group(): void
    {
        $match = fn (string $uri, string $method = 'GET') => Route::getRoutes()->match(Request::create($this->root($uri), $method));

        $this->assertSame('central.login', $match('/login')->getName());
        $this->assertSame('central.login.attempt', $match('/login', 'POST')->getName());
        $this->assertSame('admin.dashboard', $match('/admin')->getName());
        $this->assertSame('platform.login', $match('/admin/login')->getName());
        $this->assertSame('up', ltrim($match('/up')->uri(), '/'));
        foreach (['/login', '/admin', '/admin/login', '/up', '/two-factor/challenge'] as $uri) {
            $this->assertNotContains('tenant', $match($uri)->parameterNames(), "{$uri} reached the company group");
        }

        // and the {tenant} requirement refuses every reserved word on its own as well
        foreach (Tenancy::reserved() as $word) {
            $this->assertDoesNotMatchRegularExpression('#^' . Tenancy::routePattern() . '$#', $word, "{$word} matches {tenant}");
        }
        $this->assertMatchesRegularExpression('#^' . Tenancy::routePattern() . '$#', 'skyline');
        $this->assertMatchesRegularExpression('#^' . Tenancy::routePattern() . '$#', 'admins');     // only the exact word is reserved
    }

    #[Test]
    public function every_first_segment_outside_the_company_group_is_a_reserved_address(): void
    {
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            $first = explode('/', ltrim($route->uri(), '/'))[0];
            if ($first !== '' && $first !== '{tenant}' && ! Tenancy::isReserved($first)) {
                $missing[] = "{$first} (" . $route->uri() . ')';
            }
        }
        foreach (scandir(public_path()) as $entry) {
            if ($entry !== '.' && $entry !== '..' && $entry !== '.htaccess' && ! Tenancy::isReserved($entry)) {
                $missing[] = "public/{$entry}";
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Add these to config/tenancy.php reserved');
    }

    #[Test]
    public function a_new_company_cannot_take_a_reserved_address(): void
    {
        foreach (['admin', 'login', 'auth', 'storage', 'build', 'two-factor', 'profile', 'invitation', 'sanctum'] as $word) {
            $this->assertTrue(Validator::make(['slug' => $word], ['slug' => [new CompanyAddress()]])->fails(), "{$word} was accepted");
        }
        $this->assertFalse(Validator::make(['slug' => 'brightwater'], ['slug' => [new CompanyAddress()]])->fails());
    }

    // ── sessions ───────────────────────────────────────────────────────────────

    #[Test]
    public function each_company_the_console_and_the_root_have_their_own_session_cookie(): void
    {
        $cookie = fn ($res, string $name) => collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === $name);

        $c = $cookie($this->get($this->root('/skyline/login')), 'fsm_co_skyline');
        $this->assertNotNull($c);
        $this->assertSame('/skyline', $c->getPath());

        $c = $cookie($this->get($this->root('/harbour/login')), 'fsm_co_harbour');
        $this->assertNotNull($c);
        $this->assertSame('/harbour', $c->getPath());

        $c = $cookie($this->get($this->root('/admin/login')), 'fsm_console');
        $this->assertNotNull($c);
        $this->assertSame('/admin', $c->getPath());

        $c = $cookie($this->get($this->root('/login')), 'fsm_central');
        $this->assertNotNull($c);
        $this->assertSame('/', $c->getPath());
    }

    #[Test]
    public function signed_in_at_one_company_another_company_asks_for_its_own_sign_in(): void
    {
        $this->makeUser($this->a, 'company_admin', 'ops@skyline.test');

        // a real sign-in at Skyline: the session lives in Skyline's cookie only
        $res = $this->post($this->root('/skyline/login'), ['identifier' => 'ops@skyline.test', 'password' => 'password']);
        $res->assertRedirect();
        $session = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === 'fsm_co_skyline');
        $this->assertNotNull($session);

        // the browser sends that cookie to /harbour too only if the names matched; they do not, so Harbour sees a guest
        $this->withCookie('fsm_co_skyline', $session->getValue())
            ->get($this->root('/harbour/dashboard'))
            ->assertRedirect($this->root('/harbour/login'));
    }

    #[Test]
    public function a_user_of_another_company_is_signed_out_and_sent_to_this_companys_sign_in(): void
    {
        $user = $this->makeUser($this->a, 'company_admin');

        $this->actingAs($user)->get($this->root('/harbour/dashboard'))
            ->assertRedirect($this->root('/harbour/login'))
            ->assertSessionHasErrors(['identifier' => 'Please sign in to HARBOUR Ltd.']);
        $this->assertGuest('web');
    }

    // ── central sign-in hand-off ─────────────────────────────────────────────

    #[Test]
    public function the_central_sign_in_hands_off_to_the_company_path(): void
    {
        $user = $this->makeUser($this->a, 'company_admin', 'boss@skyline.test');

        $res = $this->post($this->root('/login'), ['identifier' => 'boss@skyline.test', 'password' => 'password']);
        $location = $res->headers->get('Location');
        $this->assertMatchesRegularExpression('#^' . preg_quote($this->root('/skyline/auth/handoff/'), '#') . '[A-Za-z0-9]{48}$#', $location);

        $this->get($location)->assertRedirect($this->root('/skyline/dashboard'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    // ── links built outside a request ──────────────────────────────────────

    #[Test]
    public function a_queued_invoice_email_links_to_the_company_path(): void
    {
        $client  = $this->makeClient($this->a);
        $invoice = $this->makeInvoice($this->a, $client);
        $token   = $this->sendTo($invoice, 'pay@client.test');

        Tenant::setCurrent(null);                          // the queue worker: no company, no URL default
        Tenancy::applyUrlDefaults(null);
        $this->assertFalse(Tenancy::hasCompanyUrls());

        app('mailer')->getSymfonyTransport()->flush();
        app(MessageLogger::class)->send('pay@client.test', new InvoiceSentMail($token), 'invoice.sent', ['tenant_id' => $this->a->id]);

        $sent = collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => $m->toString())->implode("\n");
        $this->assertStringContainsString($this->root('/skyline/i/' . $token->token), quoted_printable_decode($sent));
        $this->assertFalse(Tenancy::hasCompanyUrls(), 'the job puts the URL default back');
    }

    #[Test]
    public function notification_links_are_kept_company_relative_and_opened_on_the_company_path(): void
    {
        $user = $this->makeUser($this->a, 'operations_manager');

        // what callers pass: a path built with the URL default, an absolute URL, a plain relative path
        foreach (['/skyline/service-requests/7', $this->root('/skyline/service-requests/7'), '/service-requests/7', 'http://skyline.localhost/service-requests/7'] as $url) {
            $this->assertSame('/service-requests/7', TenantUrl::relative($this->a, $url), $url);
        }
        $this->assertSame('https://elsewhere.test/x', TenantUrl::relative($this->a, 'https://elsewhere.test/x'));
        $this->assertSame($this->root('/skyline/service-requests/7'), TenantUrl::open($this->a, '/service-requests/7'));

        // built in a command (no company): TenantUrl::route() fills the company for the one call
        Tenant::setCurrent(null);
        Tenancy::applyUrlDefaults(null);
        $this->assertSame('/admin/usage', TenantUrl::route($this->a, 'admin.usage.index'));

        app(NotificationService::class)->notify('sr.approval_rejected', [$user], ['sr_number' => 'SR-1', 'reason' => 'x'], '/skyline/service-requests/7');
        $n = UserNotification::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame('/service-requests/7', $n->url);

        $this->actingAs($user)->get(route('notifications.open', $n))->assertRedirect($this->root('/skyline/service-requests/7'));
    }

    // ── old addresses ──────────────────────────────────────────────────────────

    #[Test]
    public function an_old_subdomain_link_goes_to_the_company_path(): void
    {
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME);
        $port   = parse_url(config('app.url'), PHP_URL_PORT);
        $old    = "{$scheme}://skyline." . Tenancy::rootHost() . ($port ? ":{$port}" : '');

        $this->get($old . '/i/abc123?x=1')->assertStatus(301)->assertRedirect($this->root('/skyline/i/abc123?x=1'));
        $this->get($old . '/')->assertStatus(301)->assertRedirect($this->root('/skyline'));
        $this->post($old . '/feedback/abc')->assertStatus(308);

        $console = "{$scheme}://admin." . Tenancy::rootHost() . ($port ? ":{$port}" : '');
        $this->get($console . '/admin/login')->assertRedirect($this->root('/admin/login'));
    }

    #[Test]
    public function a_company_path_with_capital_letters_goes_to_lowercase(): void
    {
        $this->get($this->root('/Skyline/login?next=1'))->assertStatus(301)->assertRedirect($this->root('/skyline/login?next=1'));
        $this->get($this->root('/SKYLINE'))->assertStatus(301)->assertRedirect($this->root('/skyline'));
        $this->get($this->root('/Nobody/login'))->assertNotFound();               // not a company: no redirect
    }

    #[Test]
    public function an_old_root_invoice_or_quote_link_goes_to_the_company_path(): void
    {
        $client  = $this->makeClient($this->a);
        $token   = $this->sendTo($this->makeInvoice($this->a, $client), 'pay@client.test');

        $this->get($this->root('/i/' . $token->token))->assertStatus(301)->assertRedirect($this->root('/skyline/i/' . $token->token));
        $this->get($this->root('/i/' . $token->token . '/pdf'))->assertRedirect($this->root('/skyline/i/' . $token->token . '/pdf'));
        $this->get($this->root('/i/unknown-token'))->assertNotFound();
        $this->get($this->root('/q/unknown-token'))->assertNotFound();
    }

    #[Test]
    public function guests_are_sent_to_the_right_sign_in(): void
    {
        $this->get($this->root('/skyline/dashboard'))->assertRedirect($this->root('/skyline/login'));
        $this->get($this->root('/skyline/portal'))->assertRedirect($this->root('/skyline/client/sign-in'));
        $this->get($this->root('/admin'))->assertRedirect($this->root('/admin/login'));
        $this->get($this->root('/'))->assertRedirect($this->root('/login'));
        $this->get($this->root('/no-such-page/at/all'))->assertNotFound();      // the 404 page itself renders on the root
    }
}
