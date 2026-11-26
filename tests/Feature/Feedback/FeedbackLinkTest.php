<?php

namespace Tests\Feature\Feedback;

use App\Mail\FeedbackRequestMail;
use App\Models\ClientContact;
use App\Models\SecureLink;
use App\Models\ServiceRequest;
use App\Models\TenantSetting;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Forms\FormsTestCase;

/**
 * Feedback by secure link (blueprint 7.7b, 9.2): a completed request sends the client contact one link, valid for feedback_link_days and
 * working once; the answer is a star rating (1 to 5), a score (0 to 10) and an optional comment. Now is 19 November 2026, 10:00 UTC.
 */
class FeedbackLinkTest extends FormsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function contact(string $email = 'priya@acme.test', array $over = []): ClientContact
    {
        return ClientContact::withoutGlobalScopes()->create(array_merge(['tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'name' => 'Priya', 'email' => $email, 'is_active' => true, 'is_primary' => true], $over));
    }

    protected function completed(array $over = []): ServiceRequest
    {
        $sr = $this->raise($this->makeCategory());
        $sr->forceFill(array_merge(['status' => 'in_progress'], $over))->save();
        $sr->update(['status' => 'completed']);

        return $sr->fresh();
    }

    /** The token in the last feedback message. */
    protected function token(?int $nth = null): string
    {
        $mails = Mail::sent(FeedbackRequestMail::class);
        $mail = $nth === null ? $mails->last() : $mails->values()->get($nth);
        $this->assertNotNull($mail, 'No feedback message was sent.');

        return basename(parse_url($mail->feedbackUrl, PHP_URL_PATH));
    }

    protected function open(string $token, $host = null)
    {
        return $this->get(($host ?? $this->onHost($this->t)) . '/feedback/' . $token);
    }

    protected function answer(string $token, array $data = [], $host = null)
    {
        return $this->post(($host ?? $this->onHost($this->t)) . '/feedback/' . $token, array_merge(['stars' => 5, 'score' => 10, 'comment' => 'Quick and tidy'], $data));
    }

    /* ---------- the message and the link ---------- */

    public function test_completing_a_request_sends_the_client_contact_the_feedback_link(): void
    {
        $this->contact('priya@acme.test');
        $this->contact('second@acme.test', ['is_primary' => false]);

        $sr = $this->completed();

        Mail::assertSent(FeedbackRequestMail::class, 1);
        Mail::assertSent(FeedbackRequestMail::class, fn ($m) => $m->hasTo('priya@acme.test') && ! $m->hasTo('second@acme.test') && $m->serviceRequest->is($sr));
        $url = Mail::sent(FeedbackRequestMail::class)->first()->feedbackUrl;
        $this->assertMatchesRegularExpression(\App\Support\Tenancy::isPath()
            ? '#^https?://[^/]+/fab/feedback/[A-Za-z0-9]{48}$#'                // path mode: root/{slug}/feedback/...
            : '#^https?://fab\.[^/]+/feedback/[A-Za-z0-9]{48}$#', $url);
        $this->assertStringContainsString($sr->sr_number, Mail::sent(FeedbackRequestMail::class)->first()->render());
    }

    public function test_the_message_is_in_the_message_log_without_the_link(): void
    {
        $this->contact();
        $this->completed();
        $token = $this->token();

        $row = DB::table('message_logs')->where('tenant_id', $this->t->id)->where('event_key', 'sr.completed')->first();

        $this->assertNotNull($row);
        $this->assertSame('sent', $row->status);
        $this->assertSame('priya@acme.test', $row->recipient_address);
        $this->assertStringNotContainsString($token, json_encode((array) $row));
        $this->assertStringNotContainsString($token, json_encode(DB::table('secure_links')->get()->all()));     // only its hash is stored
        $this->assertSame(1, DB::table('secure_links')->where('token_hash', hash('sha256', $token))->count());
    }

    public function test_the_link_is_valid_for_the_companys_feedback_link_days(): void
    {
        $this->contact();
        $this->completed();                                                          // no setting row: 7 days
        $this->assertSame('2026-11-26 10:00:00', SecureLink::where('purpose', 'client_feedback')->latest('id')->first()->expires_at->utc()->format('Y-m-d H:i:s'));

        TenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->t->id], ['feedback_link_days' => 3]);
        $this->completed();
        $this->assertSame('2026-11-22 10:00:00', SecureLink::where('purpose', 'client_feedback')->latest('id')->first()->expires_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_message_gives_the_last_day(): void
    {
        $this->contact();
        TenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->t->id], ['feedback_link_days' => 3]);
        $this->completed();

        $html = Mail::sent(FeedbackRequestMail::class)->first()->render();

        $this->assertStringContainsString('valid until 22 Nov 2026', $html);
        $this->assertStringContainsString('works once', $html);
    }

    public function test_no_contact_with_an_email_means_no_message_and_no_error(): void
    {
        $this->contact('', ['is_primary' => true]);
        $this->contact('gone@acme.test', ['is_active' => false, 'is_primary' => false]);

        $this->completed();

        Mail::assertNotSent(FeedbackRequestMail::class);
        $this->assertSame(0, SecureLink::where('purpose', 'client_feedback')->count());
    }

    public function test_a_trade_does_not_send_a_link_of_its_own_the_master_does(): void
    {
        $this->contact();
        $master = $this->raise($this->makeCategory());
        $trade = $master->replicate();
        $trade->forceFill(['sr_number' => 'TRADE-1', 'parent_sr_id' => $master->id, 'status' => 'in_progress'])->save();

        $trade->update(['status' => 'completed']);                                   // the master then becomes Completed too

        Mail::assertSent(FeedbackRequestMail::class, 1);
        Mail::assertSent(FeedbackRequestMail::class, fn ($m) => $m->serviceRequest->is($master) && ! $m->serviceRequest->is($trade));
    }

    public function test_saving_a_completed_request_again_does_not_send_another_link(): void
    {
        $this->contact();
        $sr = $this->completed();

        $sr->update(['title' => 'Renamed']);

        Mail::assertSent(FeedbackRequestMail::class, 1);
    }

    /* ---------- answering ---------- */

    public function test_the_client_opens_the_page_and_answers(): void
    {
        $this->contact();
        $sr = $this->completed();
        $token = $this->token();

        $this->open($token)->assertOk()->assertSee($sr->sr_number)->assertSee('name="stars"', false)->assertSee('name="score"', false);
        $this->answer($token, ['stars' => 4, 'score' => 9, 'comment' => '  Very good  '])->assertOk()->assertSee('Thank you');

        $fresh = $sr->fresh();
        $this->assertSame(4, (int) $fresh->client_star_rating);
        $this->assertSame(9, (int) $fresh->client_nps_score);
        $this->assertSame('Very good', $fresh->client_feedback_comment);
        $this->assertSame('link', $fresh->client_feedback_source);
        $this->assertSame('Priya', $fresh->client_feedback_by);
        $this->assertNotNull($fresh->client_feedback_at);
        $this->assertSame(1, DB::table('activity_logs')->where('tenant_id', $this->t->id)->where('action', 'feedback.received')->count());
    }

    public function test_the_comment_is_optional(): void
    {
        $this->contact();
        $sr = $this->completed();

        $this->answer($this->token(), ['comment' => ''])->assertOk();

        $this->assertNull($sr->fresh()->client_feedback_comment);
        $this->assertSame(5, (int) $sr->fresh()->client_star_rating);
    }

    public function test_the_operations_manager_is_told_and_a_low_rating_is_urgent(): void
    {
        $this->contact();
        $a = $this->completed();
        $this->answer($this->token(), ['stars' => 5, 'score' => 10]);
        $b = $this->completed();
        $this->answer($this->token(), ['stars' => 2, 'score' => 10]);
        $c = $this->completed();
        $this->answer($this->token(), ['stars' => 5, 'score' => 6]);
        $d = $this->completed();
        $this->answer($this->token(), ['stars' => 3, 'score' => 7]);

        $n = UserNotification::where('user_id', $this->u['operations_manager']->id)->where('event_key', 'feedback.received')->orderBy('id')->get();

        $this->assertCount(4, $n);
        $this->assertSame([false, true, true, false], $n->map(fn ($x) => str_starts_with($x->title, 'Urgent: '))->all());
        $this->assertSame(['normal', 'urgent', 'urgent', 'normal'], $n->pluck('data.priority')->all());
        $this->assertStringContainsString($b->sr_number, $n[1]->title);
        $this->assertCount(0, UserNotification::where('user_id', $this->u['finance']->id)->where('event_key', 'feedback.received')->get());
    }

    /** @dataProvider badAnswers */
    public function test_an_incomplete_or_out_of_range_answer_is_refused_and_the_link_stays_usable(array $data, string $field): void
    {
        $this->contact();
        $sr = $this->completed();
        $token = $this->token();

        $this->answer($token, $data)->assertSessionHasErrors($field);
        $this->assertNull($sr->fresh()->client_star_rating);

        $this->answer($token)->assertOk()->assertSee('Thank you');                 // the same link still works
    }

    public static function badAnswers(): array
    {
        return [
            'no stars'     => [['stars' => ''], 'stars'],
            'zero stars'   => [['stars' => 0], 'stars'],
            'six stars'    => [['stars' => 6], 'stars'],
            'text stars'   => [['stars' => 'five'], 'stars'],
            'no score'     => [['score' => ''], 'score'],
            'score of 11'  => [['score' => 11], 'score'],
            'negative'     => [['score' => -1], 'score'],
            'long comment' => [['comment' => str_repeat('x', 2001)], 'comment'],
        ];
    }

    public function test_the_ends_of_both_scales_are_accepted(): void
    {
        $this->contact();
        $a = $this->completed();
        $this->answer($this->token(), ['stars' => 1, 'score' => 0])->assertOk();
        $b = $this->completed();
        $this->answer($this->token(), ['stars' => 5, 'score' => 10])->assertOk();

        $this->assertSame([1, 0], [(int) $a->fresh()->client_star_rating, (int) $a->fresh()->client_nps_score]);
        $this->assertSame([5, 10], [(int) $b->fresh()->client_star_rating, (int) $b->fresh()->client_nps_score]);
    }

    /* ---------- one answer ---------- */

    public function test_a_used_link_shows_a_clear_page_and_changes_nothing(): void
    {
        $this->contact();
        $sr = $this->completed();
        $token = $this->token();
        $this->answer($token, ['stars' => 4, 'score' => 8, 'comment' => 'first'])->assertOk();

        $this->open($token)->assertStatus(410)->assertSee('Feedback already received')->assertSee('works once');
        $this->answer($token, ['stars' => 1, 'score' => 0, 'comment' => 'second'])->assertStatus(410)->assertSee('Feedback already received');

        $fresh = $sr->fresh();
        $this->assertSame([4, 8, 'first'], [(int) $fresh->client_star_rating, (int) $fresh->client_nps_score, $fresh->client_feedback_comment]);
        $this->assertSame(1, UserNotification::where('event_key', 'feedback.received')->count());
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'feedback.received')->count());
    }

    public function test_the_link_is_marked_used_with_the_address(): void
    {
        $this->contact();
        $this->completed();
        $token = $this->token();

        $this->answer($token);

        $link = SecureLink::where('token_hash', hash('sha256', $token))->first();
        $this->assertNotNull($link->used_at);
        $this->assertNotNull($link->request_ip);
    }

    /* ---------- expiry ---------- */

    public function test_an_expired_link_shows_a_clear_page_and_cannot_be_answered(): void
    {
        $this->contact();
        $sr = $this->completed();
        $token = $this->token();

        Carbon::setTestNow(Carbon::parse('2026-11-26 10:00:01', 'UTC'));                // one second after the 7 days

        $this->open($token)->assertStatus(410)->assertSee('This link has expired');
        $this->answer($token)->assertStatus(410)->assertSee('This link has expired');
        $this->assertNull($sr->fresh()->client_star_rating);
    }

    public function test_the_link_still_works_on_its_last_day(): void
    {
        $this->contact();
        $sr = $this->completed();
        $token = $this->token();

        Carbon::setTestNow(Carbon::parse('2026-11-26 09:59:59', 'UTC'));

        $this->answer($token)->assertOk()->assertSee('Thank you');
        $this->assertSame(5, (int) $sr->fresh()->client_star_rating);
    }

    public function test_a_form_opened_before_expiry_and_sent_after_it_is_refused(): void
    {
        $this->contact();
        $sr = $this->completed();
        $token = $this->token();
        $this->open($token)->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00', 'UTC'));
        $this->answer($token)->assertStatus(410)->assertSee('expired');

        $this->assertNull($sr->fresh()->client_star_rating);
    }

    /* ---------- wrong tenant, wrong token ---------- */

    public function test_another_companys_address_is_a_404_and_the_root_address_works(): void
    {
        $this->contact();
        $sr = $this->completed();
        $token = $this->token();
        $other = $this->makeTenant(slug: 'other');
        auth()->guard('web')->logout();                                                  // a visitor, not the Front Desk user who raised the request

        $this->open($token, $this->onHost($other))->assertNotFound();
        $this->answer($token, [], $this->onHost($other))->assertNotFound();
        $this->assertNull($sr->fresh()->client_star_rating);

        \App\Models\Tenant::setCurrent(null);                                           // a real request starts without a company; a test process keeps the last one
        if (\App\Support\Tenancy::isPath()) {
            // path mode: feedback links were always sent on the company address, so the root has no /feedback page; the company's own works
            $this->open($token, 'http://localhost')->assertNotFound();
            $this->open($token)->assertOk();                                            // the request's own company address
            $this->answer($token)->assertOk();
        } else {
            $this->open($token, 'http://localhost')->assertOk();
            $this->answer($token, [], 'http://localhost')->assertOk();
        }
        $this->assertSame(5, (int) $sr->fresh()->client_star_rating);
    }

    public function test_an_unknown_token_and_a_token_of_another_kind_are_a_404(): void
    {
        $this->contact();
        $this->completed();

        $this->open(str_repeat('a', 48))->assertNotFound();
        $this->answer(str_repeat('b', 48))->assertNotFound();

        $user = $this->u['company_admin'];
        $other = app(\App\Services\Auth\SecureLinkService::class)->create($user, 'password_reset', 60);
        $this->open($other)->assertNotFound();                                       // the right kind of token only
    }

    public function test_a_link_cannot_answer_for_another_request(): void
    {
        $this->contact();
        $a = $this->completed();
        $tokenA = $this->token();
        $b = $this->completed();

        $this->answer($tokenA, ['stars' => 1]);

        $this->assertSame(1, (int) $a->fresh()->client_star_rating);
        $this->assertNull($b->fresh()->client_star_rating);
    }

    /* ---------- reopen ---------- */

    public function test_a_reopened_job_gets_a_new_link_and_the_new_answer_replaces_the_old_one(): void
    {
        $this->contact();
        $sr = $this->completed();
        $first = $this->token();
        $this->answer($first, ['stars' => 2, 'score' => 3, 'comment' => 'bad'])->assertOk();

        $sr->forceFill(['status' => 'in_progress'])->save();
        $sr->update(['status' => 'completed']);
        $second = $this->token();

        $this->assertNotSame($first, $second);
        $this->open($first)->assertStatus(410);                                       // used
        $this->answer($second, ['stars' => 5, 'score' => 10, 'comment' => 'fixed'])->assertOk();

        $fresh = $sr->fresh();
        $this->assertSame([5, 10, 'fixed'], [(int) $fresh->client_star_rating, (int) $fresh->client_nps_score, $fresh->client_feedback_comment]);
    }

    public function test_an_unused_link_is_cancelled_when_a_new_one_is_made(): void
    {
        $this->contact();
        $sr = $this->completed();
        $first = $this->token();

        $sr->forceFill(['status' => 'in_progress'])->save();
        $sr->update(['status' => 'completed']);

        $this->assertNotSame(200, $this->open($first)->getStatusCode());
        $this->assertSame(1, SecureLink::where('purpose', 'client_feedback')->whereNull('used_at')->count());
    }

    /* ---------- the answer on the request page ---------- */

    public function test_the_request_page_shows_the_answer(): void
    {
        $this->contact();
        $sr = $this->completed();
        $this->answer($this->token(), ['stars' => 4, 'score' => 9, 'comment' => "Fast\nand clean"]);

        $html = $this->as('operations_manager')->get($this->onHost($this->t, '/service-requests/' . $sr->id))->assertOk()->getContent();

        foreach (['Client feedback', '4 of 5 stars', '9 of 10', 'Fast', 'and clean', 'by Priya (secure link)'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
    }

    public function test_the_request_page_has_no_feedback_card_before_an_answer(): void
    {
        $this->contact();
        $sr = $this->completed();

        $html = $this->as('operations_manager')->get($this->onHost($this->t, '/service-requests/' . $sr->id))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="client-feedback"', $html);
    }

    /* ---------- rate limit ---------- */

    public function test_the_route_is_rate_limited(): void
    {
        $this->contact();
        $this->completed();
        $token = $this->token();

        foreach (range(1, 20) as $i) {
            $this->assertSame(200, $this->open($token)->getStatusCode(), "request {$i}");
        }

        $this->open($token)->assertStatus(429);
        $this->answer($token)->assertStatus(429);                                    // the form shares the limit
    }

    public function test_the_routes_need_no_sign_in(): void
    {
        $this->contact();
        $this->completed();
        $token = $this->token();
        auth()->guard('web')->logout();

        $this->assertGuest();
        $this->open($token)->assertOk();
        $this->assertGuest();
    }
}
