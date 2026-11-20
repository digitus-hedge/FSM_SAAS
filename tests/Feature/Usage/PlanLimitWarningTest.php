<?php

namespace Tests\Feature\Usage;

use App\Mail\NotificationMail;
use App\Models\Attachment;
use App\Models\ServiceRequest;
use App\Models\SrStatusHistory;
use App\Models\TenantUsageCounter;
use App\Models\UserNotification;
use App\Services\Usage\PlanLimitWarnings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Forms\FormsTestCase;

/**
 * plan.limit_warning (blueprint 6, 7.9a, 9.2, decision 42): the Company Admin is told once at 80 and once at 100 percent of each plan limit
 * for each month, in-app and by email, from the nightly `usage:refresh`. The test company has 6 staff users, so a user limit of 7 is 85.7 percent.
 * Now is 19 November 2026.
 */
class PlanLimitWarningTest extends FormsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function limits(array $limits): void
    {
        $plan = $this->t->plan;
        $plan->forceFill(array_merge(['user_limit' => null, 'requests_per_month_limit' => null, 'storage_gb_limit' => null], $limits))->save();
    }

    protected function refresh(): void
    {
        $this->artisan('usage:refresh')->assertSuccessful();
    }

    protected function warnings(?string $role = 'company_admin')
    {
        return UserNotification::where('user_id', $this->u[$role]->id)->where('event_key', 'plan.limit_warning')->orderBy('id')->get();
    }

    protected function counter(): TenantUsageCounter
    {
        return TenantUsageCounter::where('tenant_id', $this->t->id)->where('year', 2026)->where('month', 11)->firstOrFail();
    }

    /* ---------- users ---------- */

    public function test_nothing_is_sent_below_80_percent(): void
    {
        $this->limits(['user_limit' => 8]);                                  // 6 of 8 = 75 percent

        $this->refresh();

        $this->assertCount(0, $this->warnings());
        $this->assertSame(6, (int) $this->counter()->user_count);
    }

    public function test_the_company_admin_is_warned_once_at_80_percent(): void
    {
        $this->limits(['user_limit' => 7]);                                  // 85.7 percent

        $this->refresh();
        $this->refresh();
        $this->refresh();

        $n = $this->warnings();
        $this->assertCount(1, $n);
        $this->assertSame('Users is at 85.7% of your plan limit', $n[0]->title);
        $this->assertSame('6 of 7 users used in November 2026. Nothing is blocked. Contact Aiywah if you need a higher limit.', $n[0]->body);
        $this->assertEquals(['limit' => 'users', 'threshold' => 80, 'year' => 2026, 'month' => 11], $n[0]->data);
        $this->assertSame('/admin/usage', $n[0]->url);
        $this->assertTrue($this->counter()->hasWarned('users', 80));
        $this->assertFalse($this->counter()->hasWarned('users', 100));
    }

    public function test_it_goes_to_the_company_admin_only_and_by_email_as_well(): void
    {
        $this->limits(['user_limit' => 7]);

        $this->refresh();

        foreach (['operations_manager', 'finance', 'front_desk', 'team_lead', 'field_technician'] as $role) {
            $this->assertCount(0, $this->warnings($role), $role);
        }
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo($this->u['company_admin']->email) && $m->notification->event_key === 'plan.limit_warning');
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->notification->event_key === 'plan.limit_warning' && ! $m->hasTo($this->u['finance']->email));
    }

    public function test_a_second_message_goes_out_at_100_percent_and_never_again(): void
    {
        $this->limits(['user_limit' => 7]);
        $this->refresh();

        $this->makeUser($this->t, 'front_desk', 'second.desk@fab.test');      // 7 of 7
        $this->refresh();
        $this->refresh();

        $n = $this->warnings();
        $this->assertCount(2, $n);
        $this->assertSame('You have reached your plan limit for Users', $n[1]->title);
        $this->assertStringContainsString('7 of 7 users used in November 2026.', $n[1]->body);
        $this->assertStringContainsString('Nothing is blocked and work in progress continues. Contact Aiywah to upgrade.', $n[1]->body);
        $this->assertSame(100, $n[1]->data['threshold']);
        $this->assertTrue($this->counter()->hasWarned('users', 80));
        $this->assertTrue($this->counter()->hasWarned('users', 100));
    }

    public function test_a_limit_found_already_at_100_sends_one_message_and_marks_both_points(): void
    {
        $this->limits(['user_limit' => 6]);                                  // 6 of 6

        $this->refresh();
        $this->refresh();

        $n = $this->warnings();
        $this->assertCount(1, $n);
        $this->assertSame(100, $n[0]->data['threshold']);
        $this->assertTrue($this->counter()->hasWarned('users', 80));
        $this->assertTrue($this->counter()->hasWarned('users', 100));
    }

    public function test_going_back_under_and_over_again_in_the_same_month_does_not_repeat(): void
    {
        $this->limits(['user_limit' => 7]);
        $this->refresh();
        $this->limits(['user_limit' => 20]);
        $this->refresh();
        $this->limits(['user_limit' => 7]);
        $this->refresh();

        $this->assertCount(1, $this->warnings());
    }

    public function test_a_new_month_starts_again(): void
    {
        $this->limits(['user_limit' => 7]);
        $this->refresh();
        $this->assertCount(1, $this->warnings());

        Carbon::setTestNow(Carbon::parse('2026-12-03 10:00:00', 'UTC'));
        $this->refresh();
        $this->refresh();

        $this->assertCount(2, $this->warnings());
        $this->assertTrue(TenantUsageCounter::where('tenant_id', $this->t->id)->where('year', 2026)->where('month', 12)->firstOrFail()->hasWarned('users', 80));
    }

    public function test_a_plan_with_no_number_never_warns(): void
    {
        $this->limits(['user_limit' => null]);
        $this->refresh();
        $this->limits(['user_limit' => 0]);
        $this->refresh();

        $this->assertCount(0, $this->warnings());
    }

    public function test_the_check_can_be_called_directly_and_says_what_it_sent(): void
    {
        $this->limits(['user_limit' => 7]);
        $this->refresh();                                                    // sends 80 and creates the counter

        $this->makeUser($this->t, 'front_desk', 'second.desk@fab.test');
        $counter = $this->counter();
        $counter->user_count = 7;
        $counter->save();

        $sent = app(PlanLimitWarnings::class)->check($this->t->fresh(), $counter->fresh());

        $this->assertSame(['users' => 100], $sent);
        $this->assertSame([], app(PlanLimitWarnings::class)->check($this->t->fresh(), $counter->fresh()));
    }

    /* ---------- requests (blueprint 6: what counts) ---------- */

    protected function monthsRequests(int $n): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $this->raise($this->makeCategory());
        }

        return $out;
    }

    public function test_requests_of_the_month_count_and_warn_at_80_percent(): void
    {
        $this->limits(['requests_per_month_limit' => 5]);
        $this->monthsRequests(4);                                            // 4 of 5

        $this->refresh();

        $this->assertSame(4, (int) $this->counter()->requests_count);
        $n = $this->warnings();
        $this->assertCount(1, $n);
        $this->assertSame('Service requests this month is at 80% of your plan limit', $n[0]->title);
        $this->assertStringContainsString('4 of 5 requests used in November 2026.', $n[0]->body);
    }

    public function test_a_request_cancelled_while_pending_does_not_count_but_a_later_cancel_does(): void
    {
        $this->limits(['requests_per_month_limit' => 100]);
        [$pending, $approved, $plain] = $this->monthsRequests(3);
        SrStatusHistory::log($pending, 'pending', 'cancelled');
        $pending->forceFill(['status' => 'cancelled'])->save();
        SrStatusHistory::log($approved, 'approved', 'cancelled');
        $approved->forceFill(['status' => 'cancelled'])->save();

        $this->refresh();

        $this->assertSame(2, (int) $this->counter()->requests_count);
    }

    public function test_last_months_requests_and_other_companys_requests_do_not_count(): void
    {
        $this->limits(['requests_per_month_limit' => 100]);
        [$old, $new] = $this->monthsRequests(2);
        \Illuminate\Support\Facades\DB::table('service_requests')->where('id', $old->id)->update(['created_at' => '2026-10-31 10:00:00']);
        $other = $this->makeTenant(slug: 'other');
        $theirs = $new->replicate();
        $theirs->forceFill(['tenant_id' => $other->id, 'sr_number' => 'OTHER-1'])->save();

        $this->refresh();

        $this->assertSame(1, (int) $this->counter()->requests_count);
    }

    public function test_child_requests_count_like_any_other(): void
    {
        $this->limits(['requests_per_month_limit' => 100]);
        [$parent] = $this->monthsRequests(1);
        $child = $parent->replicate();
        $child->forceFill(['sr_number' => 'CHILD-1', 'parent_sr_id' => $parent->id])->save();

        $this->refresh();

        $this->assertSame(2, (int) $this->counter()->requests_count);
    }

    /* ---------- storage ---------- */

    public function test_storage_is_the_size_of_the_files_in_gb(): void
    {
        $this->limits(['storage_gb_limit' => 1]);
        $sr = $this->raise($this->makeCategory());
        $this->file($sr, 912680550);                                         // 0.85 GB

        $this->refresh();

        $this->assertSame(912680550, (int) $this->counter()->storage_bytes);
        $n = $this->warnings();
        $this->assertCount(1, $n);
        $this->assertSame('File storage is at 85% of your plan limit', $n[0]->title);
        $this->assertStringContainsString('0.85 of 1 GB used in November 2026.', $n[0]->body);
    }

    public function test_deleted_files_do_not_count(): void
    {
        $this->limits(['storage_gb_limit' => 1]);
        $sr = $this->raise($this->makeCategory());
        $this->file($sr, 912680550)->delete();

        $this->refresh();

        $this->assertSame(0, (int) $this->counter()->storage_bytes);
        $this->assertCount(0, $this->warnings());
    }

    protected function file(ServiceRequest $sr, int $bytes): Attachment
    {
        return Attachment::create(['tenant_id' => $this->t->id, 'attachable_type' => ServiceRequest::class, 'attachable_id' => $sr->id, 'kind' => 'sr_attachment',
            'storage_path' => 'sr/' . $sr->id . '/f.bin', 'original_name' => 'f.bin', 'mime_type' => 'application/octet-stream', 'size_bytes' => $bytes, 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid()]);
    }

    /* ---------- who is checked ---------- */

    public function test_each_limit_has_its_own_message(): void
    {
        $this->limits(['user_limit' => 7, 'requests_per_month_limit' => 4]);
        $this->monthsRequests(4);

        $this->refresh();

        $this->assertEqualsCanonicalizing(['users', 'requests'], $this->warnings()->pluck('data.limit')->all());
        $this->assertSame(100, $this->warnings()->firstWhere('data.limit', 'requests')->data['threshold']);
    }

    public function test_a_suspended_company_is_counted_but_not_messaged(): void
    {
        $this->limits(['user_limit' => 7]);
        $this->t->forceFill(['status' => 'suspended'])->save();

        $this->refresh();

        $this->assertSame(6, (int) $this->counter()->user_count);
        $this->assertCount(0, $this->warnings());
    }

    public function test_another_company_is_checked_on_its_own_numbers_and_tells_its_own_admin(): void
    {
        $this->limits(['user_limit' => 100]);
        $small = $this->plan('tiny', ['forms' => true]);
        $small->forceFill(['user_limit' => 1])->save();
        $other = $this->makeTenant($small, slug: 'other');
        $theirAdmin = $this->makeUser($other, 'company_admin');

        $this->refresh();

        $this->assertCount(0, $this->warnings());                            // our company is far from its limit
        $this->assertSame(1, UserNotification::where('user_id', $theirAdmin->id)->where('event_key', 'plan.limit_warning')->count());
    }

    /* ---------- the page ---------- */

    public function test_the_usage_page_shows_the_refreshed_figures(): void
    {
        $this->limits(['requests_per_month_limit' => 10]);
        $this->monthsRequests(3);
        $this->refresh();

        $html = $this->as('company_admin')->get($this->onHost($this->t, '/admin/usage'))->assertOk()->getContent();

        $this->assertStringContainsString('Service requests this month', $html);
        $this->assertMatchesRegularExpression('/\b3\b[^<]*(of|\/)[^<]*\b10\b/s', $html);
    }
}
