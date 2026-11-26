<?php

namespace Tests\Feature\Platform;

use App\Models\ImpersonationSession;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\UserNotification;
use App\Services\Platform\Impersonation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Forms\FormsTestCase;

/**
 * Support impersonation (blueprint 7.9a, 9.2, decision 116): a reason, a time limit (30 minutes by default, 2 hours at most), a banner with End session,
 * the handoff token of the central sign-in, the blocked actions, and audit rows for start and end in the platform and the company audit.
 * Now is 19 November 2026, 10:00 UTC. The company is "fab".
 */
class ImpersonationTest extends FormsTestCase
{
    protected PlatformUser $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->owner = $this->platformUser('owner');
    }

    protected function platformUser(string $role): PlatformUser
    {
        static $n = 0;
        $n++;

        return PlatformUser::create(['name' => ucfirst($role) . ' ' . $n, 'email' => $role . $n . '@aiywah.test', 'password' => 'password', 'platform_role' => $role, 'two_factor_confirmed_at' => now()]);
    }

    protected function root(): void
    {
        Tenant::setCurrent(null);                       // a real request on the console address starts without a company
    }

    /** The console button: returns the response. */
    protected function start(array $data = [], ?PlatformUser $who = null, ?Tenant $tenant = null)
    {
        $this->root();

        return $this->actingAs($who ?? $this->owner, 'platform')
            ->post(route('admin.tenants.impersonate', $tenant ?? $this->t), array_merge(['reason' => 'Customer reported a missing invoice'], $data));
    }

    protected function impSession(): ?ImpersonationSession
    {
        return ImpersonationSession::latest('id')->first();
    }

    /** Starts a session and follows the handoff on the company address, so the company user is signed in. */
    protected function signedIn(array $data = []): ImpersonationSession
    {
        $res = $this->start($data)->assertRedirect();
        $token = basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));
        $this->handoff($token)->assertRedirect();
        auth()->shouldUse('web');                       // the console request used the platform guard as the default; the company pages use the web guard

        return $this->impSession();
    }

    protected function handoff(string $token)
    {
        return $this->get($this->onHost($this->t, '/auth/handoff/' . $token));
    }

    protected function co(string $path = '/dashboard'): string
    {
        return $this->onHost($this->t, $path);
    }

    protected function audits(string $action, ?int $tenantId = null)
    {
        return DB::table('activity_logs')->where('action', $action)->where('tenant_id', $tenantId)->get();
    }

    /* ---------- starting: reason and time ---------- */

    /** @dataProvider badReasons */
    public function test_a_reason_of_10_to_255_characters_is_required(?string $reason): void
    {
        $data = $reason === null ? ['reason' => ''] : ['reason' => $reason];

        $this->start($data)->assertSessionHasErrors('reason');

        $this->assertSame(0, ImpersonationSession::count());
        $this->assertCount(0, $this->audits('impersonation.started'));
        $this->assertCount(0, $this->audits('impersonation.started', $this->t->id));
    }

    public static function badReasons(): array
    {
        return ['empty' => [null], 'nine characters' => ['123456789'], 'only spaces' => ['              '], 'too long' => [str_repeat('x', 256)]];
    }

    public function test_the_edges_of_the_reason_are_accepted(): void
    {
        $this->start(['reason' => '1234567890'])->assertRedirect();
        $this->assertSame('1234567890', $this->impSession()->reason);
        $this->impSession()->forceFill(['ended_at' => now()])->save();

        $this->start(['reason' => str_repeat('y', 255)])->assertRedirect();
        $this->assertSame(255, mb_strlen($this->impSession()->reason));
    }

    public function test_the_default_time_is_30_minutes(): void
    {
        $this->start()->assertRedirect();

        $s = $this->impSession();
        $this->assertSame('2026-11-19 10:00:00', $s->started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 10:30:00', $s->expires_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_time_can_be_set_up_to_two_hours_and_no_more(): void
    {
        $this->start(['minutes' => 120])->assertRedirect();
        $this->assertSame('2026-11-19 12:00:00', $this->impSession()->expires_at->utc()->format('Y-m-d H:i:s'));
        $this->impSession()->forceFill(['ended_at' => now()])->save();

        $this->start(['minutes' => 121])->assertSessionHasErrors('minutes');
        $this->start(['minutes' => 0])->assertSessionHasErrors('minutes');
        $this->assertSame(1, ImpersonationSession::count());
    }

    public function test_the_service_refuses_a_time_over_the_limit_too(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(Impersonation::class)->start($this->owner, $this->t, null, 'A good enough reason', 121, '127.0.0.1');
    }

    /* ---------- who may ---------- */

    public function test_owner_and_support_may_but_finance_may_not(): void
    {
        $this->start([], $this->platformUser('support'))->assertRedirect();
        $this->impSession()->forceFill(['ended_at' => now()])->save();
        $this->assertSame(1, ImpersonationSession::count());

        $this->start([], $this->platformUser('finance'))->assertForbidden();

        $this->assertSame(1, ImpersonationSession::count());
    }

    public function test_a_visitor_who_is_not_signed_in_to_the_console_is_turned_away(): void
    {
        $this->root();

        $this->post(route('admin.tenants.impersonate', $this->t), ['reason' => 'Customer reported a missing invoice'])->assertRedirect();

        $this->assertSame(0, ImpersonationSession::count());
    }

    public function test_a_suspended_company_cannot_be_entered(): void
    {
        $this->t->forceFill(['status' => 'suspended'])->save();

        $this->start()->assertSessionHasErrors('tenant');
        $this->assertSame(0, ImpersonationSession::count());
    }

    public function test_the_company_address_has_no_console(): void
    {
        Tenant::setCurrent(null);

        $this->actingAs($this->owner, 'platform')->post($this->onHost($this->t, '/admin/tenants/' . $this->t->id . '/impersonate'), ['reason' => 'Customer reported a missing invoice'])->assertNotFound();
        $this->assertSame(0, ImpersonationSession::count());
    }

    /* ---------- who is signed in as ---------- */

    public function test_by_default_it_signs_in_as_a_company_admin(): void
    {
        $this->start()->assertRedirect();

        $this->assertSame($this->u['company_admin']->id, (int) $this->impSession()->target_user_id);
    }

    public function test_it_can_sign_in_as_another_staff_user_of_the_company(): void
    {
        $this->start(['user_id' => $this->u['finance']->id])->assertRedirect();

        $this->assertSame($this->u['finance']->id, (int) $this->impSession()->target_user_id);
    }

    public function test_it_cannot_sign_in_as_a_client_a_deactivated_user_or_another_companys_user(): void
    {
        \App\Models\Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => 'client_admin'], ['name' => 'Client Admin', 'is_standard' => false]);
        $client = $this->makeUser($this->t, 'client_admin', 'ca@client.test');
        $client->forceFill(['client_id' => $this->client->id])->save();
        $gone = $this->makeUser($this->t, 'front_desk', 'gone@fab.test');
        $gone->forceFill(['deactivated_at' => now()])->save();
        $other = $this->makeTenant(slug: 'other');
        $theirs = $this->makeUser($other, 'company_admin');

        foreach ([$client->id, $gone->id, $theirs->id, 999999] as $id) {
            $this->start(['user_id' => $id])->assertSessionHasErrors('user_id');
        }
        $this->assertSame(0, ImpersonationSession::count());
    }

    public function test_a_company_with_no_active_admin_cannot_be_entered_without_choosing_someone(): void
    {
        $this->u['company_admin']->forceFill(['deactivated_at' => now()])->save();

        $this->start()->assertSessionHasErrors('user_id');
    }

    /* ---------- one at a time ---------- */

    public function test_only_one_session_can_be_open_for_a_platform_user(): void
    {
        $this->start()->assertRedirect();

        $this->start(['reason' => 'Another reason for support'])->assertSessionHasErrors('reason');
        $this->assertSame(1, ImpersonationSession::count());

        app(Impersonation::class)->end($this->impSession(), 'ended');
        $this->start(['reason' => 'Another reason for support'])->assertRedirect();
        $this->assertSame(2, ImpersonationSession::count());
    }

    public function test_two_platform_users_may_each_have_a_session(): void
    {
        $this->start()->assertRedirect();

        $this->start([], $this->platformUser('support'))->assertRedirect();

        $this->assertSame(2, ImpersonationSession::count());
    }

    public function test_a_session_that_ran_out_does_not_block_the_next_one(): void
    {
        $this->start()->assertRedirect();
        Carbon::setTestNow(Carbon::parse('2026-11-19 11:00:00', 'UTC'));

        $this->start(['reason' => 'Another reason for support'])->assertRedirect();

        $this->assertSame('expired', ImpersonationSession::orderBy('id')->first()->end_reason);
    }

    /* ---------- the handoff ---------- */

    public function test_the_start_sends_the_browser_to_the_company_address_with_a_one_time_token(): void
    {
        $res = $this->start()->assertRedirect();

        $location = $res->headers->get('Location');
        $this->assertMatchesRegularExpression(\App\Support\Tenancy::isPath()
            ? '#^https?://[^/]+/fab/auth/handoff/[A-Za-z0-9]{48}$#'      // path mode: root/{slug}/...
            : '#^https?://fab\.[^/]+/auth/handoff/[A-Za-z0-9]{48}$#', $location);

        $token = basename($location);
        $this->handoff($token)->assertRedirect();
        $this->assertAuthenticatedAs($this->u['company_admin']->fresh(), 'web');

        auth('web')->logout();
        $this->handoff($token)->assertRedirect(route('login'));                      // the token works once
        $this->assertGuest('web');
    }

    public function test_the_handoff_token_expires_after_60_seconds(): void
    {
        $token = basename($this->start()->assertRedirect()->headers->get('Location'));

        Carbon::setTestNow(Carbon::parse('2026-11-19 10:01:05', 'UTC'));
        $this->handoff($token)->assertRedirect(route('login'));

        $this->assertGuest('web');
    }

    public function test_a_handoff_for_a_session_that_has_ended_signs_nobody_in(): void
    {
        $token = basename($this->start()->assertRedirect()->headers->get('Location'));
        app(Impersonation::class)->end($this->impSession(), 'ended');

        $this->handoff($token)->assertRedirect(route('login'));

        $this->assertGuest('web');
    }

    public function test_a_token_for_another_company_does_nothing_on_this_address(): void
    {
        $token = basename($this->start()->assertRedirect()->headers->get('Location'));
        $other = $this->makeTenant(slug: 'other');

        $this->get($this->onHost($other, '/auth/handoff/' . $token))->assertRedirect(route('login'));

        $this->assertGuest('web');
    }

    public function test_the_session_does_not_touch_the_users_sign_in_record_or_devices(): void
    {
        $before = $this->u['company_admin']->fresh()->last_sign_in_at;

        $this->signedIn();

        $this->assertEquals($before, $this->u['company_admin']->fresh()->last_sign_in_at);
        $this->assertSame(0, DB::table('user_devices')->where('user_id', $this->u['company_admin']->id)->count());
    }

    public function test_a_user_who_must_change_the_password_is_not_sent_to_that_page(): void
    {
        $this->u['company_admin']->forceFill(['must_change_password' => true])->save();
        $this->signedIn();

        $this->get($this->co('/dashboard'))->assertOk();
    }

    /* ---------- the banner ---------- */

    public function test_a_banner_with_end_session_shows_on_the_pages_of_the_session(): void
    {
        $this->signedIn(['reason' => 'Checking the invoice run']);

        foreach (['/dashboard', '/admin/clients', '/service-requests'] as $path) {
            $html = $this->get($this->co($path))->assertOk()->getContent();
            $this->assertStringContainsString('id="impersonation-banner"', $html, $path);
            $this->assertStringContainsString('Checking the invoice run', $html, $path);
            $this->assertStringContainsString('End session', $html, $path);
            $this->assertStringContainsString('/impersonation/end', $html, $path);
            $this->assertStringContainsString($this->owner->name, $html, $path);
        }
    }

    public function test_the_banner_gives_the_end_time_in_company_time(): void
    {
        $this->signedIn();

        $html = $this->get($this->co())->assertOk()->getContent();

        $this->assertStringContainsString('Ends at 14:30 (30 min left)', $html);          // 10:30 UTC is 14:30 in Dubai
    }

    public function test_no_banner_without_a_session(): void
    {
        $this->as('company_admin')->get($this->co())->assertOk()->assertDontSee('impersonation-banner');
    }

    /* ---------- ending and expiring ---------- */

    public function test_end_session_signs_out_ends_the_session_and_goes_to_the_console(): void
    {
        $s = $this->signedIn();

        $this->post($this->co('/impersonation/end'))->assertRedirect(rtrim(config('app.url'), '/') . '/admin');

        $this->assertGuest('web');
        $fresh = $s->fresh();
        $this->assertNotNull($fresh->ended_at);
        $this->assertSame('ended', $fresh->end_reason);
        $this->assertCount(1, $this->audits('impersonation.ended'));
        $this->assertCount(1, $this->audits('impersonation.ended', $this->t->id));
    }

    public function test_the_end_route_is_for_a_support_session_only(): void
    {
        $this->as('company_admin')->post($this->co('/impersonation/end'))->assertNotFound();

        auth('web')->logout();
        $this->post($this->co('/impersonation/end'))->assertRedirect();
    }

    public function test_signing_out_ends_the_session_as_signed_out(): void
    {
        $s = $this->signedIn();

        $this->post($this->co('/logout'))->assertRedirect();

        $this->assertSame('signed_out', $s->fresh()->end_reason);
        $this->assertCount(1, $this->audits('impersonation.ended', $this->t->id));
    }

    public function test_the_session_stops_when_its_time_is_up(): void
    {
        $s = $this->signedIn();
        $this->get($this->co())->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-11-19 10:30:01', 'UTC'));
        $this->get($this->co())->assertRedirect(route('login'));

        $this->assertGuest('web');
        $fresh = $s->fresh();
        $this->assertSame('expired', $fresh->end_reason);
        $this->assertNotNull($fresh->ended_at);
        $this->assertCount(1, $this->audits('impersonation.ended'));
        $this->assertCount(1, $this->audits('impersonation.ended', $this->t->id));
    }

    public function test_the_session_works_up_to_its_last_second(): void
    {
        $this->signedIn();

        Carbon::setTestNow(Carbon::parse('2026-11-19 10:29:59', 'UTC'));

        $this->get($this->co())->assertOk();
    }

    public function test_the_expire_command_closes_a_session_nobody_came_back_to(): void
    {
        $this->start()->assertRedirect();
        $this->start(['reason' => 'Another reason for support'], $this->platformUser('support'))->assertRedirect();
        $first = ImpersonationSession::orderBy('id')->first();
        $second = ImpersonationSession::orderByDesc('id')->first();
        $second->forceFill(['expires_at' => now()->addHours(1)])->save();

        Carbon::setTestNow(Carbon::parse('2026-11-19 10:45:00', 'UTC'));
        $this->artisan('impersonation:expire')->assertSuccessful();
        $this->artisan('impersonation:expire')->assertSuccessful();                    // twice: nothing more happens

        $this->assertSame('expired', $first->fresh()->end_reason);
        $this->assertNull($second->fresh()->ended_at);
        $this->assertCount(1, $this->audits('impersonation.ended', $this->t->id));
    }

    public function test_the_expire_command_is_scheduled_every_minute(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'impersonation:expire'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    /* ---------- blocked actions ---------- */

    public function test_passwords_and_two_factor_are_blocked(): void
    {
        $this->signedIn();

        $this->post($this->co('/change-password'), ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertForbidden();
        $this->get($this->co('/change-password'))->assertForbidden();
        $this->get($this->co('/two-factor/setup'))->assertForbidden();
        $this->post($this->co('/two-factor/disable'))->assertForbidden();
    }

    public function test_changing_users_and_roles_is_blocked_but_looking_is_not(): void
    {
        $this->signedIn();
        $other = $this->u['finance'];

        $this->get($this->co('/manage/users'))->assertOk();
        $this->get($this->co('/manage/roles'))->assertOk();
        $this->post($this->co('/manage/users'), ['name' => 'X Person', 'email' => 'x@fab.test'])->assertForbidden();
        $this->post($this->co('/manage/users/' . $other->id . '/deactivate'))->assertForbidden();
        $this->post($this->co('/manage/users/' . $other->id . '/temporary-password'))->assertForbidden();
        $this->post($this->co('/manage/roles'), ['name' => 'Extra'])->assertForbidden();
        $this->assertNull($other->fresh()->deactivated_at);
    }

    public function test_invoice_quote_and_payment_actions_are_blocked(): void
    {
        $invoice = $this->makeInvoice($this->t, $this->client);
        $sr = $this->makeServiceRequest($this->t, $this->client);
        $quote = $this->makeQuote($this->t, $this->client, $sr);
        $this->signedIn();

        foreach (['approve', 'void', 'send', 'payment', 'write-off', 'undo-write-off'] as $action) {
            $this->post($this->co('/admin/invoices/' . $invoice->id . '/' . $action))->assertForbidden();
        }
        $this->post($this->co('/admin/quotes/' . $quote->id . '/send'))->assertForbidden();
        $this->assertSame($invoice->status, $invoice->fresh()->status);
    }

    public function test_exports_are_blocked(): void
    {
        $this->signedIn();

        foreach (['/service-requests/export', '/admin/aging/export', '/admin/payments/export', '/admin/reports/sla/excel', '/admin/reports/sla/pdf'] as $path) {
            $this->get($this->co($path))->assertForbidden();
        }
    }

    public function test_ordinary_work_is_allowed_and_counted(): void
    {
        $s = $this->signedIn();

        $this->get($this->co('/admin/clients'))->assertOk();
        $this->post($this->co('/admin/hold-reasons'), ['label' => 'Waiting for the client', 'pauses_sla' => 1])->assertSessionHasNoErrors();

        $this->assertSame(1, (int) $s->fresh()->actions_count);                          // the change counts, the reading does not
        $this->assertTrue(\App\Models\HoldReason::where('label', 'Waiting for the client')->exists());
    }

    public function test_a_blocked_attempt_is_not_counted(): void
    {
        $s = $this->signedIn();

        $this->post($this->co('/manage/roles'), ['name' => 'Extra'])->assertForbidden();

        $this->assertSame(0, (int) $s->fresh()->actions_count);
    }

    /** @dataProvider names */
    public function test_the_block_list_by_route_name(?string $name, string $method, ?string $expected): void
    {
        $this->assertSame($expected, app(Impersonation::class)->blockedReason($name, $method));
    }

    public static function names(): array
    {
        return [
            ['password.change.submit', 'POST', 'change a password'],
            ['two-factor.setup', 'GET', 'change two-factor sign-in'],
            ['tenant.users.index', 'GET', null],
            ['tenant.users.store', 'POST', 'change users'],
            ['tenant.users.deactivate', 'POST', 'change users'],
            ['tenant.roles.index', 'GET', null],
            ['tenant.roles.update', 'PUT', 'change roles or permissions'],
            ['admin.invoices.approve', 'POST', 'approve an invoice'],
            ['admin.invoices.void', 'POST', 'void an invoice'],
            ['admin.invoices.send', 'POST', 'send an invoice'],
            ['admin.quotes.send', 'POST', 'send a quote'],
            ['admin.invoices.payment', 'POST', 'record a payment'],
            ['admin.invoices.payment.reverse', 'POST', 'reverse a payment'],
            ['admin.invoices.write-off', 'POST', 'write off a balance'],
            ['service-requests.export', 'GET', 'export data'],
            ['admin.aging.export', 'GET', 'export data'],
            ['admin.reports.excel', 'GET', 'export data'],
            ['admin.invoices.show', 'GET', null],
            ['admin.invoices.submit', 'POST', null],
            ['service-requests.index', 'GET', null],
            [null, 'GET', null],
        ];
    }

    /* ---------- audit ---------- */

    public function test_start_and_end_are_in_both_audits(): void
    {
        $s = $this->signedIn(['reason' => 'Checking the invoice run']);
        app(Impersonation::class)->end($s->fresh(), 'ended');

        foreach ([null, $this->t->id] as $tenantId) {
            $started = $this->audits('impersonation.started', $tenantId);
            $ended = $this->audits('impersonation.ended', $tenantId);
            $this->assertCount(1, $started, 'started, tenant ' . var_export($tenantId, true));
            $this->assertCount(1, $ended, 'ended, tenant ' . var_export($tenantId, true));
            $this->assertSame('platform_user', $started[0]->actor_type);
            $this->assertSame($this->owner->id, (int) $started[0]->actor_id);
            $this->assertSame($s->id, (int) $started[0]->item_id);
            $new = json_decode($started[0]->new_values, true);
            $this->assertSame('Checking the invoice run', $new['reason']);
            $this->assertSame('fab', $new['company']);
            $this->assertSame($s->id, (int) $started[0]->impersonation_session_id);
            $this->assertSame('ended', json_decode($ended[0]->new_values, true)['end_reason']);
        }
    }

    public function test_what_the_session_does_in_the_company_carries_the_session(): void
    {
        $s = $this->signedIn();
        $this->post($this->co('/admin/hold-reasons'), ['label' => 'Waiting for the client'])->assertSessionHasNoErrors();
        app(\App\Services\Audit\AuditLogger::class)->log('test.change', ['item_type' => 'test', 'item_id' => 1]);       // any audit row written during the session

        $rows = DB::table('activity_logs')->where('tenant_id', $this->t->id)->where('impersonation_session_id', $s->id)->pluck('action')->all();

        $this->assertContains('auth.sign_in', $rows);
        $this->assertContains('test.change', $rows);
        $this->assertContains('impersonation.started', $rows);
        $row = DB::table('activity_logs')->where('action', 'auth.sign_in')->where('impersonation_session_id', $s->id)->first();
        $this->assertSame($this->owner->id, (int) $row->impersonated_by_platform_user_id);
        $this->assertSame('support_session', json_decode($row->new_values, true)['channel']);
    }

    public function test_work_outside_a_session_carries_no_session(): void
    {
        $this->as('company_admin')->post($this->co('/admin/hold-reasons'), ['label' => 'Waiting for the client'])->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('activity_logs')->whereNotNull('impersonation_session_id')->count());
    }

    public function test_the_rows_stay_linked_in_each_chain(): void
    {
        $s = $this->signedIn();
        app(Impersonation::class)->end($s->fresh(), 'ended');

        // each chain (the company's, and the platform's with no company) links every row to the one before it
        foreach ([$this->t->id, null] as $tenantId) {
            $rows = DB::table('activity_logs')->where('tenant_id', $tenantId)->orderBy('id')->get();
            $this->assertGreaterThanOrEqual(2, $rows->count());
            $prev = null;
            foreach ($rows as $r) {
                $this->assertSame($prev, $r->prev_hash, 'row ' . $r->id . ' (' . $r->action . ')');
                $prev = $r->hash;
            }
        }
    }

    /* ---------- the Company Admin is told ---------- */

    public function test_every_company_admin_is_told_when_a_session_starts(): void
    {
        $second = $this->makeUser($this->t, 'company_admin', 'second.admin@fab.test');

        $this->start(['reason' => 'Checking the invoice run', 'minutes' => 60])->assertRedirect();

        foreach ([$this->u['company_admin'], $second] as $admin) {
            $n = UserNotification::where('user_id', $admin->id)->where('event_key', 'impersonation.started')->first();
            $this->assertNotNull($n, $admin->email);
            $this->assertStringStartsWith('Urgent:', $n->title);
            $this->assertStringContainsString($this->owner->name, $n->body);
            $this->assertStringContainsString('60 minutes', $n->body);
            $this->assertStringContainsString('Checking the invoice run', $n->body);
            $this->assertSame('urgent', $n->data['priority']);
        }
        foreach (['operations_manager', 'finance', 'front_desk'] as $role) {
            $this->assertSame(0, UserNotification::where('user_id', $this->u[$role]->id)->where('event_key', 'impersonation.started')->count(), $role);
        }
        Mail::assertSent(\App\Mail\NotificationMail::class, fn ($m) => $m->hasTo($this->u['company_admin']->email) && $m->notification->event_key === 'impersonation.started');
    }

    public function test_another_company_is_not_told(): void
    {
        $other = $this->makeTenant(slug: 'other');
        $theirAdmin = $this->makeUser($other, 'company_admin');

        $this->start()->assertRedirect();

        $this->assertSame(0, UserNotification::where('user_id', $theirAdmin->id)->count());
        $this->assertCount(0, $this->audits('impersonation.started', $other->id));
    }

    /* ---------- the console button ---------- */

    public function test_the_console_shows_the_button_to_owner_and_support_only(): void
    {
        $this->root();
        $button = fn (PlatformUser $u) => str_contains($this->actingAs($u, 'platform')->get('http://localhost/admin')->assertOk()->getContent(), 'admin/tenants/' . $this->t->id . '/impersonate');

        $this->assertTrue($button($this->owner));
        $this->assertTrue($button($this->platformUser('support')));
        $this->assertFalse($button($this->platformUser('finance')));
    }
}
