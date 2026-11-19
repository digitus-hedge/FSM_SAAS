<?php

namespace Tests\Feature\Currency;

use App\Mail\InvoiceOverdueMail;
use App\Mail\NotificationMail;
use App\Models\ActivityLog;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\TenantSetting;
use App\Models\UserNotification;
use App\Services\Billing\OverdueReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

/**
 * Sections 10.8 and 10.9: reminders and settings (LED-03, LED-04), the overdue message (LED-05), CP-071 to CP-092.
 * The company is in Dubai (UTC+4); "day N" below means 0:00 to 23:59 of 2026-11-N in Dubai, and the job runs at 09:00.
 * WhatsApp is not built yet, so CP-087 and CP-089 are not covered here (reminders go by email, spec D16).
 */
class OverdueRemindersTest extends BillingTestCase
{
    private function tenant(array $settings = [], $plan = null)
    {
        $t = $this->makeTenant($plan, tz: 'Asia/Dubai', slug: 'od' . (\App\Models\Tenant::count() + 1));
        if ($settings) {
            TenantSetting::forTenant($t->id)->update($settings);
        }

        return $t;
    }

    /** Run the daily job for the company as it would run at 09:00 on the given November day. */
    private function runDay($t, int $day): array
    {
        Carbon::setTestNow(Carbon::parse(sprintf('2026-11-%02d 05:00:00', $day), 'UTC')); // 09:00 in Dubai

        return app(OverdueReminderService::class)->runForTenant($t, Carbon::now('Asia/Dubai')->startOfDay());
    }

    /** An invoice due on the 1st with a contact it was sent to. */
    private function invoice($t, array $extra = [], string $email = 'client@acme.test'): Invoice
    {
        $inv = $this->makeInvoice($t, $this->makeClient($t, 'Acme Client'), array_merge([
            'due_date' => '2026-11-01', 'status' => 'sent', 'total' => 100, 'balance' => 100,
        ], $extra));
        $this->sendTo($inv, $email);

        return $inv;
    }

    private function remindersSent(Invoice $inv): int
    {
        return InvoiceEvent::where('invoice_id', $inv->id)->where('type', 'reminder_sent')->count();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    #[Test]
    public function CP_071_defaults_on_a_new_professional_company(): void
    {
        $t = $this->tenant();
        $s = TenantSetting::forTenant($t->id)->fresh();

        $this->assertTrue($s->overdue_reminders_enabled);
        $this->assertSame([1, 7, 4], [$s->overdue_first_after_days, $s->overdue_repeat_days, $s->overdue_max_reminders]);
    }

    #[Test]
    public function CP_072_an_invoice_due_on_the_1st_gets_reminders_on_the_2nd_9th_16th_and_23rd_then_none(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t);
        $sentOn = [];

        foreach (range(1, 30) as $day) {
            if ($this->runDay($t, $day)['reminders']) {
                $sentOn[] = $day;
            }
        }

        $this->assertSame([2, 9, 16, 23], $sentOn);
        $this->assertSame(4, $inv->fresh()->reminder_count);
        $this->assertSame('overdue', $inv->fresh()->status);
    }

    #[Test]
    public function the_invoice_is_marked_overdue_from_the_day_after_its_due_date(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t);

        $this->runDay($t, 1);
        $this->assertSame('sent', $inv->fresh()->status);

        $this->runDay($t, 2);
        $this->assertSame('overdue', $inv->fresh()->status);
    }

    #[Test]
    public function CP_073_paid_on_the_10th_gets_no_reminder_after_it(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t);
        $this->runDay($t, 2);
        $this->runDay($t, 9);

        $inv->forceFill(['status' => 'paid', 'balance' => 0])->save();
        foreach (range(10, 30) as $day) {
            $this->runDay($t, $day);
        }

        $this->assertSame(2, $this->remindersSent($inv));
    }

    #[Test]
    public function CP_074_voided_or_written_off_invoices_stop_getting_reminders(): void
    {
        $t = $this->tenant();
        $void = $this->invoice($t);
        $off  = $this->invoice($t);
        $this->runDay($t, 2);

        $void->forceFill(['status' => 'void'])->save();
        $off->forceFill(['status' => 'written_off', 'balance' => 0])->save();
        $this->runDay($t, 9);

        $this->assertSame(1, $this->remindersSent($void));
        $this->assertSame(1, $this->remindersSent($off));
    }

    #[Test]
    public function CP_075_pause_with_a_reason_then_resume_on_the_20th_the_next_reminder_goes_on_the_23rd(): void
    {
        $t = $this->tenant();
        $finance = $this->makeUser($t, 'finance');
        $inv = $this->invoice($t);
        $svc = app(OverdueReminderService::class);
        $this->runDay($t, 2); // reminder 1

        Carbon::setTestNow(Carbon::parse('2026-11-03 05:00:00', 'UTC'));
        $svc->pause($inv->fresh(), 'Client is disputing the invoice', $finance);
        foreach ([9, 16] as $day) {
            $this->assertSame(0, $this->runDay($t, $day)['reminders'], "day {$day}"); // none while paused
        }

        Carbon::setTestNow(Carbon::parse('2026-11-20 05:00:00', 'UTC'));
        $svc->resume($inv->fresh(), $finance);
        $sentOn = [];
        foreach (range(20, 30) as $day) {
            if ($this->runDay($t, $day)['reminders']) {
                $sentOn[] = $day;
            }
        }

        $this->assertSame([23, 30], $sentOn); // the first goes on the 23rd (nothing is made up for the 9th and 16th), then every 7 days
        $this->assertSame(3, $inv->fresh()->reminder_count);
        $this->assertTrue(ActivityLog::where('action', 'invoice.reminders_paused')->exists());
        $this->assertTrue(ActivityLog::where('action', 'invoice.reminders_resumed')->exists());
    }

    #[Test]
    public function CP_076_pause_without_a_reason_is_refused(): void
    {
        $t = $this->tenant();
        $finance = $this->makeUser($t, 'finance');
        $inv = $this->invoice($t, ['status' => 'overdue']);

        $this->actingAs($finance)->post(route('admin.invoices.reminders.pause', $inv), ['reason' => 'ab'])
            ->assertSessionHasErrors(['reason' => 'Enter a reason of 3 to 255 characters.']);
        $this->actingAs($finance)->post(route('admin.invoices.reminders.pause', $inv), [])->assertSessionHasErrors('reason');
        $this->assertNull($inv->fresh()->reminders_paused_at);

        $this->actingAs($finance)->post(route('admin.invoices.reminders.pause', $inv), ['reason' => 'Dispute with the client'])->assertRedirect();
        $this->assertNotNull($inv->fresh()->reminders_paused_at);
        $this->actingAs($finance)->post(route('admin.invoices.reminders.resume', $inv))->assertRedirect();
        $this->assertNull($inv->fresh()->reminders_paused_at);
    }

    #[Test]
    public function CP_077_reminders_turned_off_sends_none_but_overdue_marking_continues(): void
    {
        $t = $this->tenant(['overdue_reminders_enabled' => false]);
        $inv = $this->invoice($t);

        $r = $this->runDay($t, 2);

        $this->assertSame(0, $r['reminders']);
        $this->assertSame(1, $r['marked']);
        $this->assertSame(0, $inv->fresh()->reminder_count);
        Mail::assertNothingSent();
    }

    #[Test]
    public function CP_078_settings_out_of_range_are_refused_with_the_message(): void
    {
        $t = $this->tenant();
        $finance = $this->makeUser($t, 'finance');
        $ok = ['overdue_reminders_enabled' => 1, 'overdue_first_after_days' => 1, 'overdue_repeat_days' => 7, 'overdue_max_reminders' => 4];

        $this->actingAs($finance)->put(route('admin.reminder-settings.update'), array_merge($ok, ['overdue_first_after_days' => 31]))
            ->assertSessionHasErrors(['overdue_first_after_days' => 'Enter a whole number from 0 to 30.']);
        $this->actingAs($finance)->put(route('admin.reminder-settings.update'), array_merge($ok, ['overdue_repeat_days' => 0]))
            ->assertSessionHasErrors(['overdue_repeat_days' => 'Enter a whole number from 1 to 30.']);
        $this->actingAs($finance)->put(route('admin.reminder-settings.update'), array_merge($ok, ['overdue_max_reminders' => 11]))
            ->assertSessionHasErrors(['overdue_max_reminders' => 'Enter a whole number from 1 to 10.']);
        $this->actingAs($finance)->put(route('admin.reminder-settings.update'), array_merge($ok, ['overdue_max_reminders' => 'x']))
            ->assertSessionHasErrors('overdue_max_reminders');

        $this->actingAs($finance)->put(route('admin.reminder-settings.update'), array_merge($ok, ['overdue_first_after_days' => 0, 'overdue_repeat_days' => 3, 'overdue_max_reminders' => 10]))
            ->assertRedirect(route('admin.reminder-settings.edit'))->assertSessionHas('success', 'Reminder settings saved.');
        $s = TenantSetting::forTenant($t->id)->fresh();
        $this->assertSame([0, 3, 10], [$s->overdue_first_after_days, $s->overdue_repeat_days, $s->overdue_max_reminders]);
        $this->assertTrue(ActivityLog::where('action', 'settings.reminders_changed')->where('tenant_id', $t->id)->exists());
    }

    #[Test]
    public function the_settings_page_shows_the_current_values(): void
    {
        $t = $this->tenant(['overdue_repeat_days' => 5]);

        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.reminder-settings.edit'))
            ->assertOk()->assertSee('Send overdue reminders')->assertSee('value="5"', false);
    }

    #[Test]
    public function CP_079_a_settings_change_applies_from_the_next_days_run(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t);
        $this->runDay($t, 2); // reminder 1

        TenantSetting::forTenant($t->id)->update(['overdue_repeat_days' => 3]); // slots now fall on 2, 5, 8 ...
        $this->assertSame(1, $inv->fresh()->reminder_count);                    // nothing changed by saving
        $r = $this->runDay($t, 5);

        $this->assertSame(1, $r['reminders']);
        $this->assertSame(2, $inv->fresh()->reminder_count);
    }

    #[Test]
    public function CP_080_running_the_job_twice_the_same_day_sends_each_reminder_once(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t);

        $this->runDay($t, 2);
        $second = $this->runDay($t, 2);

        $this->assertSame(0, $second['reminders']);
        $this->assertSame(1, $inv->fresh()->reminder_count);
        $this->assertSame(1, $this->remindersSent($inv));
        Mail::assertSent(InvoiceOverdueMail::class, 1);
    }

    #[Test]
    public function CP_081_a_job_that_missed_three_days_sends_one_reminder_not_three(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t); // slots on 2, 9, 16 ...

        $this->assertSame(0, $this->runDay($t, 1)['reminders']);
        $this->assertSame(1, $this->runDay($t, 5)['reminders']); // 2, 3 and 4 were missed
        $this->assertSame(0, $this->runDay($t, 6)['reminders']);
        $this->assertSame(1, $inv->fresh()->reminder_count);
        $this->assertSame(1, $this->runDay($t, 9)['reminders']); // back on the schedule
    }

    #[Test]
    public function CP_083_every_reminder_is_listed_in_the_invoice_history(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t);
        $this->runDay($t, 2);
        $this->runDay($t, 9);

        $labels = InvoiceEvent::where('invoice_id', $inv->id)->where('type', 'reminder_sent')->orderBy('id')->pluck('label')->all();

        $this->assertSame(['Reminder 1 of 4 sent', 'Reminder 2 of 4 sent'], $labels);
        $this->assertSame(2, ActivityLog::where('action', 'invoice.reminder_sent')->where('item_id', $inv->id)->count());
    }

    #[Test]
    public function CP_084_a_starter_company_gets_no_reminders_and_no_settings(): void
    {
        $t = $this->tenant(plan: $this->starterPlan());
        $inv = $this->invoice($t);

        $r = $this->runDay($t, 2);

        $this->assertSame(0, $r['reminders']);
        $this->assertSame('overdue', $inv->fresh()->status); // the Release A overdue rule is unchanged
        Mail::assertNothingSent();
        $this->actingAs($this->makeUser($t, 'finance'))->get(route('admin.reminder-settings.edit'))->assertStatus(403);
    }

    #[Test]
    public function CP_085_the_command_runs_at_0900_in_each_companys_time_zone(): void
    {
        $dubai  = $this->tenant();
        $london = $this->makeTenant(tz: 'Europe/London', slug: 'lon1');
        $a = $this->invoice($dubai);
        $b = $this->makeInvoice($london, $this->makeClient($london), ['due_date' => '2026-11-01', 'status' => 'sent', 'total' => 100, 'balance' => 100]);

        Carbon::setTestNow(Carbon::parse('2026-11-02 05:00:00', 'UTC')); // 09:00 Dubai, 05:00 London
        $this->artisan('invoices:overdue-daily')->assertSuccessful();

        $this->assertSame('overdue', $a->fresh()->status);
        $this->assertSame('sent', $b->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', 'UTC')); // 09:00 London
        $this->artisan('invoices:overdue-daily')->assertSuccessful();
        $this->assertSame('overdue', $b->fresh()->status);
    }

    #[Test]
    public function CP_086_an_invoice_with_no_balance_is_never_reminded(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t, ['status' => 'overdue', 'balance' => 0]);

        $this->assertSame(0, $this->runDay($t, 2)['reminders']);
        $this->assertSame(0, $inv->fresh()->reminder_count);
    }

    #[Test]
    public function CP_088_the_contact_gets_the_email_with_the_invoice_link(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t, [], 'client@acme.test');

        $this->runDay($t, 2);

        Mail::assertSent(InvoiceOverdueMail::class, fn ($m) => $m->hasTo('client@acme.test') && $m->invoice->is($inv));
    }

    #[Test]
    public function CP_090_finance_gets_the_finance_email_for_each_reminder(): void
    {
        $t = $this->tenant();
        $f1 = $this->makeUser($t, 'finance', 'fin1@x.test');
        $f2 = $this->makeUser($t, 'finance', 'fin2@x.test');
        $off = $this->makeUser($t, 'finance', 'gone@x.test');
        $off->forceFill(['deactivated_at' => now()])->save();
        $inv = $this->invoice($t);

        $this->runDay($t, 2);

        Mail::assertSent(NotificationMail::class, 2);
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('fin1@x.test'));
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo('fin2@x.test'));
        Mail::assertNotSent(NotificationMail::class, fn ($m) => $m->hasTo('gone@x.test'));
    }

    #[Test]
    public function CP_091_only_the_contacts_the_invoice_was_sent_to_get_it(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t, [], 'sentto@acme.test');
        ClientContact::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'client_id' => $inv->client_id, 'name' => 'Other', 'email' => 'other@acme.test', 'is_active' => true]);
        $this->sendTo($inv, 'sentto@acme.test'); // a second link to the same address: one email only

        $this->runDay($t, 2);

        Mail::assertSent(InvoiceOverdueMail::class, 1);
        Mail::assertSent(InvoiceOverdueMail::class, fn ($m) => $m->hasTo('sentto@acme.test'));
        Mail::assertNotSent(InvoiceOverdueMail::class, fn ($m) => $m->hasTo('other@acme.test'));
    }

    #[Test]
    public function CP_092_the_messages_match_the_spec_word_for_word(): void
    {
        $t = $this->tenant();
        $this->makeUser($t, 'finance', 'fin@x.test');
        $inv = $this->invoice($t, ['invoice_number' => 'INV-2026-000007', 'currency_code' => 'USD', 'exchange_rate' => 3.6725, 'total' => 1250, 'balance' => 1250]);
        $this->runDay($t, 2);

        // Client email: subject and body
        Mail::assertSent(InvoiceOverdueMail::class, function (InvoiceOverdueMail $m) use ($inv) {
            $token = $m->token;
            $this->assertSame('Invoice INV-2026-000007 is overdue', $m->envelope()->subject);
            $html = $m->render();
            $this->assertStringContainsString('Invoice INV-2026-000007 for USD 1,250.00 was due on 01 Nov 2026 and is still unpaid. Please arrange payment or contact us.', $html);
            $this->assertStringContainsString('View invoice', $html);
            $this->assertStringContainsString(route('public.invoice.show', $token->token), $html);

            return true;
        });

        // Finance email: subject and body
        $n = UserNotification::where('event_key', 'invoice.overdue')->sole();
        $this->assertSame('Reminder 1 sent for invoice INV-2026-000007', $n->title);
        $this->assertSame('Acme Client. USD 1,250.00 unpaid, 1 days overdue. Reminder 1 of 4 was sent to client@acme.test.', $n->body);
    }

    #[Test]
    public function CP_104_company_a_cannot_pause_company_bs_invoice(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $inv = $this->invoice($b, ['status' => 'overdue']);

        $financeA = $this->makeUser($a, 'finance');
        $this->actingAs($financeA)->post(route('admin.invoices.reminders.pause', $inv), ['reason' => 'Trying it'])->assertNotFound();
        $this->actingAs($financeA)->post(route('admin.invoices.reminders.resume', $inv))->assertNotFound();
        $this->assertNull($inv->fresh()->reminders_paused_at);
    }

    #[Test]
    public function only_users_with_invoice_manage_can_pause_reminders(): void
    {
        $t = $this->tenant();
        $inv = $this->invoice($t, ['status' => 'overdue']);

        $this->actingAs($this->makeUser($t, 'operations_manager'))->post(route('admin.invoices.reminders.pause', $inv), ['reason' => 'Because'])->assertForbidden();
    }

    #[Test]
    public function the_job_only_touches_its_own_company(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $invA = $this->invoice($a);
        $invB = $this->invoice($b);

        $this->runDay($a, 2);

        $this->assertSame(1, $invA->fresh()->reminder_count);
        $this->assertSame(0, $invB->fresh()->reminder_count);
        $this->assertSame('sent', $invB->fresh()->status);
    }
}
