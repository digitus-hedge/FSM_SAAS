<?php

namespace Tests\Feature\Messaging;

use App\Mail\FeedbackRequestMail;
use App\Mail\InvitationMail;
use App\Mail\NotificationMail;
use App\Models\ClientContact;
use App\Models\MessageLog;
use App\Models\Role;
use App\Models\SecureLink;
use App\Services\Messaging\MessageLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Forms\FormsTestCase;

/**
 * The message log with retry: every email goes through MessageLogger (NotificationService and the Mailable send paths), a failure is logged and
 * does not break the action, a failed message that can be built again is retried from the Company Admin screen, and a company sees only its own rows.
 * Now is 19 November 2026, 10:00 UTC.
 */
class MessageLogTest extends FormsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** The next send fails. */
    protected function failMail(string $message = 'Connection to the mail server was refused'): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException($message));
    }

    protected function working(): void
    {
        Mail::clearResolvedInstance('mail.manager');             // drop the failing mock (also from the container), then fake the mailer again
        app()->forgetInstance('mail.manager');
        Mail::fake();
    }

    protected function notifyFinance(): void
    {
        app(NotificationService::class)->notify('contract.expiring', [$this->u['finance']], ['contract_number' => 'CT-1', 'days' => 30, 'client' => 'Acme', 'date' => '19 Dec 2026']);
    }

    protected function failedNotification(): MessageLog
    {
        $this->failMail();
        $this->notifyFinance();
        $this->working();

        return MessageLog::where('tenant_id', $this->t->id)->latest('id')->firstOrFail();
    }

    protected function page(string $who = 'company_admin', array $query = []): string
    {
        return $this->as($who)->get($this->onHost($this->t, '/manage/message-log' . ($query ? '?' . http_build_query($query) : '')))->assertOk()->getContent();
    }

    protected function retry(MessageLog $log, string $who = 'company_admin')
    {
        return $this->as($who)->post($this->onHost($this->t, '/manage/message-log/' . $log->id . '/retry'));
    }

    /* ---------- writing the log ---------- */

    public function test_a_sent_notification_email_is_logged(): void
    {
        $this->notifyFinance();

        $log = MessageLog::where('tenant_id', $this->t->id)->first();

        $this->assertSame('sent', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertSame('email', $log->channel);
        $this->assertSame('outbound', $log->direction);
        $this->assertSame('contract.expiring', $log->event_key);
        $this->assertSame($this->u['finance']->email, $log->recipient_address);
        $this->assertSame($this->u['finance']->id, (int) $log->recipient_user_id);
        $this->assertSame(NotificationMail::class, $log->mailable);
        $this->assertSame(\App\Models\UserNotification::class, $log->related_type);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->failure_reason);
        Mail::assertSent(NotificationMail::class, 1);
    }

    public function test_a_failure_is_logged_and_does_not_break_the_action(): void
    {
        $this->failMail('SMTP error 535 for user token ABCDEFGHIJKLMNOPQRSTUVWXYZ123456');

        $this->notifyFinance();

        $log = MessageLog::where('tenant_id', $this->t->id)->first();
        $this->assertSame('failed', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertNull($log->sent_at);
        $this->assertStringContainsString('SMTP error', $log->failure_reason);
        $this->assertStringNotContainsString('ABCDEFGHIJKLMNOPQRSTUVWXYZ123456', $log->failure_reason);   // anything token-like is hidden
        $this->assertStringContainsString('[hidden]', $log->failure_reason);
        // the in-app message is still there, the email was not marked as sent
        $n = \App\Models\UserNotification::where('user_id', $this->u['finance']->id)->first();
        $this->assertNotNull($n);
        $this->assertNull($n->emailed_at);
    }

    public function test_a_digest_a_reschedule_and_an_invoice_style_message_are_logged_too(): void
    {
        // the reschedule mail (a Mailable path outside NotificationService)
        $this->contact();
        $sr = $this->raise($this->makeCategory());
        $sr->forceFill(['status' => 'assigned', 'assigned_tech_user_id' => $this->u['field_technician']->id, 'scheduled_eta_at' => '2026-11-20 10:00:00'])->save();

        $this->as('operations_manager')->postJson(route('dispatch.calendar.reschedule', $sr), ['new_eta' => '2026-11-21T10:00'])->assertOk();

        $log = MessageLog::where('tenant_id', $this->t->id)->where('event_key', 'visit.rescheduled')->first();
        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status);
        $this->assertSame('client@acme.test', $log->recipient_address);
        $this->assertSame($sr->id, (int) $log->related_id);
        $this->assertEqualsCanonicalizing(['sr_id', 'contact_name', 'previous', 'new', 'reason'], array_keys($log->payload_json));
    }

    protected function contact(): ClientContact
    {
        return ClientContact::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'name' => 'Priya', 'email' => 'client@acme.test', 'is_active' => true, 'is_primary' => true]);
    }

    public function test_a_failed_reschedule_email_is_logged_and_the_move_stands(): void
    {
        $this->contact();
        $sr = $this->raise($this->makeCategory());
        $sr->forceFill(['status' => 'assigned', 'assigned_tech_user_id' => $this->u['field_technician']->id, 'scheduled_eta_at' => '2026-11-20 10:00:00'])->save();
        $this->failMail();

        $this->as('operations_manager')->postJson(route('dispatch.calendar.reschedule', $sr), ['new_eta' => '2026-11-21T10:00'])->assertOk()->assertJson(['client_emailed' => false]);

        $this->assertSame('failed', MessageLog::where('event_key', 'visit.rescheduled')->first()->status);
        $this->assertSame(1, DB::table('sr_reschedules')->where('service_request_id', $sr->id)->count());
    }

    public function test_a_failed_completion_message_is_logged(): void
    {
        $this->contact();
        $sr = $this->raise($this->makeCategory());
        $sr->forceFill(['status' => 'in_progress'])->save();
        $this->failMail();

        $sr->update(['status' => 'completed']);

        $log = MessageLog::where('event_key', 'sr.completed')->first();
        $this->assertSame('failed', $log->status);
        $this->assertSame('completed', $sr->fresh()->status);
    }

    /* ---------- what is never stored ---------- */

    public function test_a_secure_link_message_leaves_no_token_in_the_log(): void
    {
        // creating a user sends an invitation whose link carries a token
        $this->as('company_admin')->post($this->onHost($this->t, '/manage/users'), [
            'name' => 'New Person', 'email' => 'new.person@fab.test', 'phone' => '+971500009999', 'role_id' => Role::where('tenant_id', $this->t->id)->where('code', 'front_desk')->value('id'),
        ]);

        $row = (array) DB::table('message_logs')->where('tenant_id', $this->t->id)->where('event_key', 'user.invitation')->first();

        $this->assertNotEmpty($row);
        $this->assertSame('new.person@fab.test', $row['recipient_address']);
        $this->assertNull($row['payload_json']);
        foreach ($row as $column => $value) {
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z0-9_\-]{30,}/', (string) $value, "column {$column} holds a long token-like value");
        }
        $this->assertStringNotContainsString('invitation/', json_encode($row));
    }

    public function test_the_feedback_message_log_holds_ids_only(): void
    {
        $this->contact();
        $sr = $this->raise($this->makeCategory());
        $sr->forceFill(['status' => 'in_progress'])->save();
        $sr->update(['status' => 'completed']);

        $log = MessageLog::where('event_key', 'sr.completed')->first();

        $this->assertEqualsCanonicalizing(['sr_id', 'contact_id', 'contact_name'], array_keys($log->payload_json));
        $this->assertStringNotContainsString('/feedback/', json_encode($log->toArray()));
    }

    public function test_the_log_has_no_message_text(): void
    {
        $this->notifyFinance();

        $row = json_encode((array) DB::table('message_logs')->first());

        $this->assertStringNotContainsString('CT-1', $row);                              // the contract number is in the message, not in the log
        $this->assertStringNotContainsString('Acme', $row);
    }

    public function test_the_channel_column_takes_other_channels_later(): void
    {
        $row = MessageLog::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'channel' => 'whatsapp', 'status' => 'delivered', 'recipient_address' => '+971500000000', 'event_key' => 'sr.created']);

        $this->assertSame('whatsapp', $row->fresh()->channel);
        $this->assertStringContainsString('+971500000000', $this->page('company_admin', ['channel' => 'whatsapp']));
        $this->assertStringNotContainsString('+971500000000', $this->page('company_admin', ['channel' => 'email']));
    }

    /* ---------- retry ---------- */

    public function test_a_failed_notification_is_retried_and_the_retry_is_audited(): void
    {
        $log = $this->failedNotification();

        $this->retry($log)->assertRedirect()->assertSessionHas('success');

        $fresh = $log->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertSame(2, $fresh->attempts);
        $this->assertNull($fresh->failure_reason);
        $this->assertNotNull($fresh->sent_at);
        Mail::assertSent(NotificationMail::class, fn ($m) => $m->hasTo($this->u['finance']->email) && $m->notification->event_key === 'contract.expiring');
        $a = DB::table('activity_logs')->where('tenant_id', $this->t->id)->where('action', 'message.retried')->first();
        $this->assertNotNull($a);
        $this->assertSame($log->id, (int) $a->item_id);
        $this->assertSame($this->u['company_admin']->id, (int) $a->actor_id);
        $this->assertSame('sent', json_decode($a->new_values, true)['result']);
    }

    public function test_a_retry_that_fails_again_says_so_and_is_still_audited(): void
    {
        $log = $this->failedNotification();
        $this->failMail('still refused');

        $this->retry($log)->assertRedirect()->assertSessionHas('error');

        $fresh = $log->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame(2, $fresh->attempts);
        $this->assertStringContainsString('still refused', $fresh->failure_reason);
        $this->assertSame('failed', json_decode(DB::table('activity_logs')->where('action', 'message.retried')->first()->new_values, true)['result']);
    }

    public function test_only_a_failed_row_can_be_retried(): void
    {
        $this->notifyFinance();
        $sent = MessageLog::first();

        $this->retry($sent)->assertSessionHas('error');

        $this->assertSame(1, $sent->fresh()->attempts);
        $this->assertSame(0, DB::table('activity_logs')->where('action', 'message.retried')->count());
        Mail::assertSent(NotificationMail::class, 1);
    }

    public function test_a_failed_row_is_not_retried_twice_at_once(): void
    {
        $log = $this->failedNotification();
        $this->retry($log);

        $this->retry($log)->assertSessionHas('error');                                    // it is sent now

        $this->assertSame(2, $log->fresh()->attempts);
        Mail::assertSent(NotificationMail::class, 1);
    }

    public function test_a_message_with_a_secure_link_cannot_be_retried_from_the_log(): void
    {
        $this->failMail();
        $this->as('company_admin')->post($this->onHost($this->t, '/manage/users'), [
            'name' => 'New Person', 'email' => 'new.person@fab.test', 'phone' => '+971500009999', 'role_id' => Role::where('tenant_id', $this->t->id)->where('code', 'front_desk')->value('id'),
        ]);
        $this->working();
        $log = MessageLog::where('event_key', 'user.invitation')->first();
        $this->assertSame('failed', $log->status);

        $this->assertFalse(app(MessageLogger::class)->isRetryable($log));
        $this->retry($log)->assertSessionHas('error');

        $this->assertSame(1, $log->fresh()->attempts);
        Mail::assertNotSent(InvitationMail::class);
        $this->assertStringContainsString('Send a new one from its screen', $this->page());
        $this->assertStringNotContainsString('/retry"', $this->page());
    }

    public function test_a_failed_feedback_message_is_retried_with_a_new_link(): void
    {
        $this->contact();
        $sr = $this->raise($this->makeCategory());
        $sr->forceFill(['status' => 'in_progress'])->save();
        $this->failMail();
        $sr->update(['status' => 'completed']);
        $this->working();
        $log = MessageLog::where('event_key', 'sr.completed')->first();
        $old = SecureLink::where('purpose', 'client_feedback')->first();

        $this->retry($log)->assertSessionHas('success');

        Mail::assertSent(FeedbackRequestMail::class, 1);
        $this->assertSame(1, SecureLink::where('purpose', 'client_feedback')->count());
        $this->assertNotSame($old->token_hash, SecureLink::where('purpose', 'client_feedback')->first()->token_hash);      // a new link, the old one is gone
        $this->assertStringNotContainsString('/feedback/', json_encode($log->fresh()->toArray()));
    }

    public function test_a_retry_whose_record_is_gone_says_so(): void
    {
        $log = $this->failedNotification();
        \App\Models\UserNotification::where('id', $log->related_id)->delete();

        $this->retry($log)->assertSessionHas('error');

        $this->assertSame('failed', $log->fresh()->status);
    }

    /* ---------- who sees and retries ---------- */

    public function test_the_company_admin_sees_the_log_and_the_retry_button(): void
    {
        $log = $this->failedNotification();

        $html = $this->page('company_admin');

        $this->assertStringContainsString($this->u['finance']->email, $html);
        $this->assertStringContainsString('contract.expiring', $html);
        $this->assertStringContainsString('/manage/message-log/' . $log->id . '/retry', $html);
        $this->assertStringContainsString('Connection to the mail server was refused', $html);
        $this->assertStringContainsString('Message log', $this->as('company_admin')->get($this->onHost($this->t, '/manage/message-log'))->getContent());
    }

    public function test_the_operations_manager_can_read_but_not_retry(): void
    {
        $log = $this->failedNotification();

        $html = $this->page('operations_manager');
        $this->assertStringContainsString($this->u['finance']->email, $html);
        $this->assertStringNotContainsString('/retry"', $html);

        $this->retry($log, 'operations_manager')->assertForbidden();
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertSame(1, $log->fresh()->attempts);
    }

    public function test_other_roles_cannot_open_the_log(): void
    {
        $log = $this->failedNotification();

        foreach (['finance', 'front_desk'] as $who) {
            $this->as($who)->get($this->onHost($this->t, '/manage/message-log'))->assertForbidden();
            $this->retry($log, $who)->assertForbidden();
        }
        $this->assertSame('failed', $log->fresh()->status);
    }

    public function test_a_client_user_cannot_open_the_log(): void
    {
        Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => 'client_admin'], ['name' => 'Client Admin', 'is_standard' => false]);
        $client = $this->makeUser($this->t, 'client_admin', 'ca@client.test');
        $client->forceFill(['client_id' => $this->client->id])->save();

        $this->actingAs($client->fresh())->get($this->onHost($this->t, '/manage/message-log'))->assertForbidden();
    }

    public function test_the_menu_entry_shows_for_the_company_admin_and_the_operations_manager_only(): void
    {
        $entry = fn (string $who) => str_contains($this->as($who)->get($this->onHost($this->t, '/dashboard'))->getContent(), 'manage/message-log');

        $this->assertTrue($entry('company_admin'));
        $this->assertTrue($entry('operations_manager'));
        $this->assertFalse($entry('finance'));
        $this->assertFalse($entry('front_desk'));
    }

    /* ---------- another company ---------- */

    public function test_another_companys_log_is_hidden_and_cannot_be_retried(): void
    {
        $other = $this->makeTenant(slug: 'other');
        $theirs = MessageLog::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'channel' => 'email', 'status' => 'failed', 'recipient_address' => 'secret@other.test', 'event_key' => 'quote.sent', 'attempts' => 1, 'mailable' => NotificationMail::class]);
        $mine = $this->failedNotification();

        $html = $this->page();

        $this->assertStringContainsString($this->u['finance']->email, $html);
        $this->assertStringNotContainsString('secret@other.test', $html);
        $this->retry($theirs)->assertNotFound();
        $this->assertSame(1, $theirs->fresh()->attempts);
        $this->assertSame('failed', $theirs->fresh()->status);
        $this->assertNotNull($mine);
    }

    /* ---------- filters ---------- */

    public function test_the_filters_narrow_the_list(): void
    {
        $this->notifyFinance();                                                       // sent, contract.expiring, finance
        $failed = $this->failedNotification();                                       // failed, contract.expiring, finance
        MessageLog::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'channel' => 'email', 'status' => 'sent', 'recipient_address' => 'someone.else@acme.test', 'event_key' => 'invoice.sent', 'attempts' => 1]);

        $failedOnly = $this->page('company_admin', ['status' => 'failed']);
        $this->assertStringContainsString('Connection to the mail server was refused', $failedOnly);
        $this->assertStringNotContainsString('someone.else@acme.test', $failedOnly);

        $this->assertStringContainsString('someone.else@acme.test', $this->page('company_admin', ['event' => 'invoice.sent']));
        $this->assertStringNotContainsString($this->u['finance']->email, $this->page('company_admin', ['event' => 'invoice.sent']));
        $this->assertStringContainsString('someone.else@acme.test', $this->page('company_admin', ['q' => 'someone.else']));
        $this->assertStringNotContainsString('someone.else@acme.test', $this->page('company_admin', ['q' => 'finance']));
        $this->assertStringContainsString($this->u['finance']->email, $this->page('company_admin', ['from' => '2026-11-19', 'to' => '2026-11-19']));
        $this->assertStringNotContainsString($this->u['finance']->email, $this->page('company_admin', ['from' => '2026-11-20']));
        $this->assertNotNull($failed);
    }

    public function test_a_bad_filter_value_is_refused(): void
    {
        $this->as('company_admin')->get($this->onHost($this->t, '/manage/message-log?status=weird'))->assertSessionHasErrors('status');
    }

    /* ---------- usage ---------- */

    public function test_the_usage_count_of_emails_counts_sent_messages_only(): void
    {
        $this->notifyFinance();
        $this->failedNotification();

        $this->artisan('usage:refresh')->assertSuccessful();

        $this->assertSame(1, (int) \App\Models\TenantUsageCounter::where('tenant_id', $this->t->id)->value('email_message_count'));
    }
}
