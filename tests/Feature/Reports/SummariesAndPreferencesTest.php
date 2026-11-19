<?php

namespace Tests\Feature\Reports;

use App\Mail\DigestMail;
use App\Models\DailySummaryRun;
use App\Models\NotificationDigestItem;
use App\Models\NotificationPreference;
use App\Models\ServiceRequest;
use App\Models\UserNotification;
use App\Services\Forms\FormException;
use App\Services\Notifications\DailySummary;
use App\Services\Notifications\DigestSender;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\Preferences;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Forms\FormsTestCase;

/** Reports-Summaries-Preferences spec 10.3 and 10.4: the daily summary and personal preferences (RSP-040 to RSP-072). */
class SummariesAndPreferencesTest extends FormsTestCase
{
    private function notify(string $event = 'invoice.overdue', $user = null, array $vars = []): void
    {
        $user ??= $this->u['finance'];
        app(Preferences::class)->forget($user);
        app(NotificationService::class)->notify($event, [$user], $vars + ['n' => 1, 'invoice_number' => 'INV-1', 'client' => 'Acme', 'balance' => '10', 'days' => 3, 'max' => 3, 'recipients' => 'x'], '/x');
    }

    private function pref($user, string $event, string $channel, string $freq): void
    {
        app(Preferences::class)->set($user->fresh()->load('role'), $event, $channel, $freq);
    }

    private function atLocal(string $time): void
    {
        // the test company is in Dubai (UTC+4)
        Carbon::setTestNow(Carbon::parse("2026-11-19 {$time}:00", 'Asia/Dubai')->utc());
    }

    // ───────────── 10.3 daily summary ─────────────

    private function waitingWork(): void
    {
        $sr = $this->makeServiceRequest($this->t, $this->client, 'approved');
        $sr->forceFill(['scheduled_eta_at' => '2026-11-19 05:00:00'])->save();   // today in Dubai
    }

    #[Test]
    public function rsp_040_the_job_at_the_configured_time_sends_one_summary_to_each_user_with_it_on(): void
    {
        $this->waitingWork();
        $this->atLocal('07:00');
        Artisan::call('summary:daily');

        $n = UserNotification::where('event_key', 'summary.daily')->where('user_id', $this->u['operations_manager']->id)->get();
        $this->assertCount(1, $n);
        $this->assertStringContainsString('1 requests are scheduled today.', $n[0]->body);
        $this->assertStringContainsString('1 approved requests have no technician.', $n[0]->body);
        $this->assertSame('Your daily summary for 19 Nov 2026', $n[0]->title);
    }

    #[Test]
    public function rsp_041_running_the_job_twice_the_same_day_sends_nothing_twice(): void
    {
        $this->waitingWork();
        $this->atLocal('07:00');
        Artisan::call('summary:daily');
        Artisan::call('summary:daily');

        $this->assertSame(1, UserNotification::where('event_key', 'summary.daily')->where('user_id', $this->u['operations_manager']->id)->count());
        $this->assertSame(1, DailySummaryRun::withoutGlobalScopes()->where('user_id', $this->u['operations_manager']->id)->count());
    }

    #[Test]
    public function rsp_042_a_weekday_that_is_not_selected_and_a_wrong_hour_send_nothing(): void
    {
        $this->waitingWork();
        $this->as('company_admin')->post(route('admin.summary-settings.update', 'operations_manager'), ['send_time' => '07:00', 'days' => [1, 2], 'blocks' => ['today_requests'], 'is_active' => 1])->assertRedirect();

        $this->atLocal('07:00');   // 19 Nov 2026 is a Thursday (4)
        Artisan::call('summary:daily');
        $this->assertSame(0, UserNotification::where('event_key', 'summary.daily')->count());

        $this->as('company_admin')->post(route('admin.summary-settings.update', 'operations_manager'), ['send_time' => '07:00', 'days' => [4], 'blocks' => ['today_requests'], 'is_active' => 1]);
        $this->atLocal('09:00');
        Artisan::call('summary:daily');
        $this->assertSame(0, UserNotification::where('event_key', 'summary.daily')->count(), 'wrong hour');
    }

    #[Test]
    public function rsp_043_a_quiet_day_sends_nothing(): void
    {
        $this->atLocal('07:00');
        Artisan::call('summary:daily');

        $this->assertSame(0, UserNotification::where('event_key', 'summary.daily')->count());
    }

    #[Test]
    public function rsp_044_a_technicians_summary_lists_only_their_own_jobs(): void
    {
        $tech = $this->u['field_technician'];
        $other = $this->makeUser($this->t, 'field_technician', 'o8@fab.test');
        foreach ([[$tech->id, 'Mine job'], [$other->id, 'Theirs job']] as [$id, $title]) {
            $sr = $this->makeServiceRequest($this->t, $this->client, 'assigned');
            $sr->forceFill(['assigned_tech_user_id' => $id, 'scheduled_eta_at' => '2026-11-19 05:30:00', 'title' => $title])->save();
        }
        $this->atLocal('07:00');
        Artisan::call('summary:daily');

        $body = UserNotification::where('event_key', 'summary.daily')->where('user_id', $tech->id)->value('body');
        $this->assertStringContainsString('Mine job', $body);
        $this->assertStringNotContainsString('Theirs job', $body);
    }

    #[Test]
    public function rsp_045_a_finance_summary_has_money_blocks_and_no_job_list(): void
    {
        $this->makeInvoice($this->t, $this->client, ['status' => 'sent', 'due_date' => '2026-11-19']);
        $this->makeInvoice($this->t, $this->client, ['status' => 'overdue', 'due_date' => '2026-11-01']);
        $this->waitingWork();
        $this->atLocal('07:00');
        Artisan::call('summary:daily');

        $body = UserNotification::where('event_key', 'summary.daily')->where('user_id', $this->u['finance']->id)->value('body');
        $this->assertStringContainsString('1 invoices are due today.', $body);
        $this->assertStringContainsString('1 invoices are overdue.', $body);
        $this->assertStringNotContainsString('scheduled today', $body);
    }

    #[Test]
    public function rsp_046_two_companies_in_different_time_zones_each_get_theirs_at_their_own_hour(): void
    {
        $this->waitingWork();
        $ny = $this->makeTenant(slug: 'ny8', tz: 'America/New_York');
        $nyOps = $this->makeUser($ny, 'operations_manager');
        $nyReq = $this->makeServiceRequest($ny, $this->makeClient($ny), 'approved');
        $nyReq->forceFill(['scheduled_eta_at' => '2026-11-19 13:00:00'])->save();

        Carbon::setTestNow(Carbon::parse('2026-11-19 03:00:00', 'UTC'));   // 07:00 in Dubai, 22:00 the evening before in New York
        Artisan::call('summary:daily');
        $this->assertSame(1, UserNotification::where('event_key', 'summary.daily')->where('user_id', $this->u['operations_manager']->id)->count());
        $this->assertSame(0, UserNotification::where('event_key', 'summary.daily')->where('user_id', $nyOps->id)->count());

        Carbon::setTestNow(Carbon::parse('2026-11-19 12:00:00', 'UTC'));   // 07:00 in New York
        Artisan::call('summary:daily');
        $this->assertSame(1, UserNotification::where('event_key', 'summary.daily')->where('user_id', $nyOps->id)->count());
    }

    #[Test]
    public function rsp_047_mail_goes_through_the_log_mailer_and_nothing_real_is_sent(): void
    {
        Mail::fake();
        $this->waitingWork();
        $this->atLocal('07:00');
        Artisan::call('summary:daily');

        Mail::assertSent(\App\Mail\NotificationMail::class, fn ($m) => $m->hasTo($this->u['operations_manager']->email));
        $this->assertSame('log', config('mail.default'));
    }

    #[Test]
    public function rsp_048_to_050_the_settings_belong_to_the_company_admin_and_a_user_can_turn_their_own_off(): void
    {
        $body = ['send_time' => '08:30', 'days' => [1, 2, 3], 'blocks' => ['unassigned'], 'is_active' => 1];
        $this->as('operations_manager')->post(route('admin.summary-settings.update', 'operations_manager'), $body)->assertForbidden();
        $this->as('operations_manager')->get(route('admin.summary-settings.index'))->assertOk()->assertSee('You can look at the settings but not change them.');

        $this->as('company_admin')->post(route('admin.summary-settings.update', 'operations_manager'), $body)->assertRedirect();
        $cfg = app(DailySummary::class)->config($this->t->id, 'operations_manager');
        $this->assertSame('08:30', $cfg['send_time']);
        $this->assertSame(['unassigned'], $cfg['blocks']);
        $this->assertTrue(\App\Models\ActivityLog::where('action', 'summary_settings.changed')->exists());

        $this->waitingWork();
        $ops = $this->u['operations_manager'];
        $this->as('operations_manager')->post(route('notifications.preferences.update'), ['daily_summary_on' => 0])->assertRedirect();
        $this->assertFalse((bool) $ops->fresh()->daily_summary_on);
        $this->atLocal('08:30');
        Artisan::call('summary:daily');
        $this->assertSame(0, UserNotification::where('event_key', 'summary.daily')->where('user_id', $ops->id)->count());
    }

    // ───────────── 10.4 preferences and digests ─────────────

    #[Test]
    public function rsp_060_a_user_with_no_preference_rows_gets_everything_at_once(): void
    {
        Mail::fake();
        $this->notify();

        $this->assertSame(1, UserNotification::where('event_key', 'invoice.overdue')->count());
        Mail::assertSent(\App\Mail\NotificationMail::class, 1);
        $this->assertSame(0, NotificationDigestItem::withoutGlobalScopes()->count());
    }

    #[Test]
    public function rsp_061_off_sends_no_email_but_the_in_app_message_is_still_written(): void
    {
        Mail::fake();
        $this->pref($this->u['finance'], 'invoice.overdue', 'email', 'off');
        $this->notify();

        Mail::assertNothingSent();
        $this->assertSame(1, UserNotification::where('event_key', 'invoice.overdue')->count());
    }

    #[Test]
    public function rsp_062_and_063_a_daily_digest_is_held_and_sent_once_at_the_users_time_grouped(): void
    {
        Mail::fake();
        $f = $this->u['finance'];
        $this->pref($f, 'invoice.overdue', 'email', 'daily_digest');
        $this->notify();
        $this->notify();

        Mail::assertNothingSent();
        $this->assertSame(2, NotificationDigestItem::withoutGlobalScopes()->whereNull('sent_at')->count());

        Carbon::setTestNow(Carbon::parse('2026-11-19 03:00:00', 'UTC'));   // 07:00 in Dubai: not their 08:00
        app(DigestSender::class)->run(now());
        Mail::assertNothingSent();

        Carbon::setTestNow(Carbon::parse('2026-11-19 04:00:00', 'UTC'));   // 08:00 in Dubai
        $this->assertSame(1, app(DigestSender::class)->run(now())['sent']);
        Mail::assertSent(DigestMail::class, fn ($m) => $m->hasTo($f->email) && $m->items->count() === 2 && $m->company === 'FAB Ltd');
        $this->assertSame(0, app(DigestSender::class)->run(now())['sent'], 'never twice');
    }

    #[Test]
    public function an_hourly_digest_goes_at_the_next_run(): void
    {
        Mail::fake();
        $this->pref($this->u['finance'], 'invoice.overdue', 'email', 'hourly_digest');
        $this->notify();

        $this->assertSame(1, app(DigestSender::class)->run(now())['sent']);
        Mail::assertSent(DigestMail::class, 1);
    }

    #[Test]
    public function rsp_064_more_than_50_held_items_show_50_lines_and_and_n_more(): void
    {
        Mail::fake();
        $f = $this->u['finance'];
        $this->pref($f, 'invoice.overdue', 'email', 'hourly_digest');
        foreach (range(1, 53) as $i) {
            NotificationDigestItem::create(['tenant_id' => $this->t->id, 'user_id' => $f->id, 'event_key' => 'invoice.overdue', 'channel' => 'email', 'frequency' => 'hourly_digest', 'title' => "Item {$i}", 'created_at' => now()]);
        }
        app(DigestSender::class)->run(now());

        Mail::assertSent(DigestMail::class, fn ($m) => $m->items->count() === 50 && $m->more === 3);
        $this->assertSame(0, NotificationDigestItem::withoutGlobalScopes()->whereNull('sent_at')->count());
    }

    #[Test]
    public function rsp_065_items_older_than_seven_days_are_dropped(): void
    {
        Mail::fake();
        $f = $this->u['finance'];
        NotificationDigestItem::create(['tenant_id' => $this->t->id, 'user_id' => $f->id, 'event_key' => 'invoice.overdue', 'channel' => 'email', 'frequency' => 'hourly_digest', 'title' => 'Old', 'created_at' => now()->subDays(8)]);

        $r = app(DigestSender::class)->run(now());
        $this->assertSame(1, $r['dropped']);
        Mail::assertNothingSent();
    }

    #[Test]
    public function rsp_066_a_critical_event_cannot_be_delayed_or_turned_off_on_every_channel(): void
    {
        $tech = $this->u['field_technician'];
        foreach (['hourly_digest', 'daily_digest'] as $freq) {
            try {
                $this->pref($tech, 'sr.assigned', 'email', $freq);
                $this->fail('a digest was allowed');
            } catch (FormException $e) {
                $this->assertSame('PREFERENCE_CRITICAL', $e->errorCode);
                $this->assertSame('This message cannot be turned off. You can choose email or in-app.', $e->getMessage());
            }
        }
        // 067: email at once and in-app off is allowed
        $this->pref($tech, 'sr.assigned', 'in_app', 'off');
        $this->assertSame('off', app(Preferences::class)->frequency($tech->fresh(), 'sr.assigned', 'in_app'));
        // 068: turning off the last channel that is still at once is refused
        $this->pref($tech, 'sr.assigned', 'push', 'off');
        $this->expectException(FormException::class);
        $this->pref($tech, 'sr.assigned', 'email', 'off');
    }

    #[Test]
    public function only_sr_assigned_and_sla_breached_are_critical_and_invoice_overdue_is_not(): void
    {
        $p = app(Preferences::class);
        $this->assertTrue($p->isCritical('sr.assigned'));
        $this->assertTrue($p->isCritical('sla.breached'));
        $this->assertFalse($p->isCritical('invoice.overdue'));
        $this->assertArrayHasKey('sr.assigned', config('notifications.events'));
        $this->assertArrayHasKey('sla.breached', config('notifications.events'));
    }

    #[Test]
    public function assigning_a_request_tells_the_technician_whichever_screen_did_it(): void
    {
        $sr = $this->makeServiceRequest($this->t, $this->client, 'approved');
        $this->as('operations_manager')->post(route('service-requests.assign', $sr), ['technician_id' => $this->u['field_technician']->id, 'arrival_time' => now()->addDay()->toDateTimeString()])->assertRedirect();

        $this->assertSame(1, UserNotification::where('event_key', 'sr.assigned')->where('user_id', $this->u['field_technician']->id)->count());
    }

    #[Test]
    public function rsp_069_a_user_cannot_change_someone_elses_preferences(): void
    {
        $this->as('front_desk')->post(route('notifications.preferences.update'), ['prefs' => ['invoice.overdue' => ['email' => 'off']], 'user_id' => $this->u['finance']->id])->assertRedirect();

        $this->assertSame(0, NotificationPreference::withoutGlobalScopes()->where('user_id', $this->u['finance']->id)->count());
        $this->assertSame(1, NotificationPreference::withoutGlobalScopes()->where('user_id', $this->u['front_desk']->id)->count());
    }

    #[Test]
    public function rsp_070_a_client_user_has_no_preferences_page(): void
    {
        \App\Models\Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => 'client_admin'], ['name' => 'Client Admin', 'is_standard' => false]);
        $c = $this->makeUser($this->t, 'client_admin', 'ca9@fab.test');

        $this->actingAs($c)->get(route('notifications.preferences'))->assertForbidden();
        $this->expectException(FormException::class);
        app(Preferences::class)->set($c->fresh()->load('role'), 'invoice.overdue', 'email', 'off');
    }

    #[Test]
    public function rsp_071_a_digest_that_fails_is_retried_the_next_hour_and_never_sent_twice(): void
    {
        $f = $this->u['finance'];
        NotificationDigestItem::create(['tenant_id' => $this->t->id, 'user_id' => $f->id, 'event_key' => 'invoice.overdue', 'channel' => 'email', 'frequency' => 'hourly_digest', 'title' => 'One', 'created_at' => now()]);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));
        $this->assertSame(0, app(DigestSender::class)->run(now())['sent']);
        $this->assertSame(1, NotificationDigestItem::withoutGlobalScopes()->whereNull('sent_at')->count());
    }

    #[Test]
    public function rsp_072_there_is_no_whatsapp_channel_anywhere(): void
    {
        $this->assertNotContains('whatsapp', Preferences::CHANNELS);
        $enum = \Illuminate\Support\Facades\DB::selectOne("SHOW COLUMNS FROM notification_preferences WHERE Field = 'channel'")->Type;
        $this->assertStringNotContainsStringIgnoringCase('whatsapp', $enum);
        $this->as('operations_manager')->get(route('notifications.preferences'))->assertOk()->assertDontSee('WhatsApp');
    }

    #[Test]
    public function the_preferences_page_shows_the_critical_lock_and_saves_choices(): void
    {
        $this->as('finance')->get(route('notifications.preferences'))->assertOk()->assertSee('Always on')->assertSee('Notification settings');
        $this->as('finance')->post(route('notifications.preferences.update'), [
            'prefs' => ['invoice.overdue' => ['email' => 'daily_digest', 'push' => 'off']], 'digest_daily_time' => '09:15', 'daily_summary_on' => 1,
        ])->assertRedirect();

        $f = $this->u['finance']->fresh();
        $this->assertSame('daily_digest', app(Preferences::class)->frequency($f, 'invoice.overdue', 'email'));
        $this->assertSame('09:15:00', $f->digest_daily_time);
        $this->assertTrue(\App\Models\ActivityLog::where('action', 'notification_preference.changed')->exists());
    }

    #[Test]
    public function the_browser_notification_endpoint_returns_only_what_is_set_to_at_once(): void
    {
        $f = $this->u['finance'];
        $this->notify('invoice.overdue');

        $res = $this->as('finance')->getJson(route('notifications.latest', ['after' => 0]))->assertOk();
        $this->assertCount(1, $res->json('items'));
        $this->assertGreaterThan(0, $res->json('last'));

        $this->pref($f, 'invoice.overdue', 'push', 'off');
        $this->assertCount(0, $this->as('finance')->getJson(route('notifications.latest', ['after' => 0]))->json('items'));
    }
}
