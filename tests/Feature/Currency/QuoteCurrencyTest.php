<?php

namespace Tests\Feature\Currency;

use App\Models\ActivityLog;
use App\Models\Quote;
use App\Models\QuoteToken;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/** Section 10.3, quotes: CP-019 to CP-026 (currency picker, rate, lock, revision, client views) and Finance building quotes. */
class QuoteCurrencyTest extends BillingTestCase
{
    private $tenant;
    private $finance;
    private $client;
    private $sr;
    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->tenant  = $this->makeTenant();
        $this->finance = $this->makeUser($this->tenant, 'finance');
        $this->client  = $this->makeClient($this->tenant);
        $this->sr      = $this->makeServiceRequest($this->tenant, $this->client, 'pending');
        $this->enableCurrency($this->tenant, 'USD');
        $this->quote   = $this->makeQuote($this->tenant, $this->client, $this->sr);
    }

    private function save(array $extra = [], bool $json = false)
    {
        $req = $this->actingAs($this->finance);
        $url = route('admin.quotes.update', $this->quote);

        return $json ? $req->putJson($url, $this->builderPayload($extra)) : $req->put($url, $this->builderPayload($extra));
    }

    #[Test]
    public function CP_019_a_usd_quote_for_an_aed_company_fills_the_rate_from_todays_stored_rate(): void
    {
        $this->save(['currency_code' => 'USD'])->assertRedirect();

        $q = $this->quote->fresh();
        $this->assertSame(['USD', '3.67250000', 'provider'], [$q->currency_code, (string) $q->exchange_rate, $q->rate_source]);
        $this->assertSame('3672.500', (string) $q->base_total);
    }

    #[Test]
    public function CP_020_finance_overwrites_the_rate_it_becomes_manual_audited_and_the_base_total_follows(): void
    {
        $this->save(['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '3.70'])->assertRedirect();

        $q = $this->quote->fresh();
        $this->assertSame(['3.70000000', 'manual', '3700.000'], [(string) $q->exchange_rate, $q->rate_source, (string) $q->base_total]);
        $log = ActivityLog::where('action', 'quote.rate_changed')->where('item_id', $q->id)->sole();
        $this->assertSame('AED', $log->old_values['currency']);
        $this->assertSame(['USD', '3.70000000', 'manual'], [$log->new_values['currency'], $log->new_values['rate'], $log->new_values['rate_source']]);
    }

    #[Test]
    public function a_plain_save_keeps_the_rate_it_does_not_refill_it(): void
    {
        $this->save(['currency_code' => 'USD'])->assertRedirect();
        $this->changeRate($this->tenant, 'USD', '3.9', 'manual'); // a newer stored rate must not change this document

        $this->save(['currency_code' => 'USD'])->assertRedirect();

        $this->assertSame('3.67250000', (string) $this->quote->fresh()->exchange_rate);
    }

    #[Test]
    public function CP_021_a_rate_of_zero_is_refused(): void
    {
        $this->save(['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '0'])->assertSessionHasErrors(['currency_code' => 'Enter a rate above 0.']);

        $this->save(['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '0'], true)
            ->assertStatus(422)->assertJson(['code' => 'VALIDATION_FAILED', 'message' => 'Enter a rate above 0.']);
        $this->assertSame('AED', $this->quote->fresh()->currency_code);
    }

    #[Test]
    public function CP_022_a_currency_that_is_not_turned_on_gives_currency_not_enabled(): void
    {
        $this->save(['currency_code' => 'EUR'], true)
            ->assertStatus(422)->assertJson(['code' => 'CURRENCY_NOT_ENABLED', 'message' => 'EUR is not turned on for your company.']);
    }

    #[Test]
    public function no_stored_rate_means_the_rate_is_required(): void
    {
        $this->enableCurrency($this->tenant, 'GBP', null); // turned on, but no rate stored yet

        $this->save(['currency_code' => 'GBP'], true)
            ->assertStatus(422)->assertJson(['code' => 'VALIDATION_FAILED', 'message' => 'Enter the rate for GBP.']);

        $this->save(['currency_code' => 'GBP', 'rate_manual' => 1, 'exchange_rate' => '4.6'])->assertRedirect();
        $this->assertSame('4.60000000', (string) $this->quote->fresh()->exchange_rate);
    }

    #[Test]
    public function CP_023_totals_are_in_usd_with_the_base_total_beside_them(): void
    {
        $this->save(['currency_code' => 'USD'])->assertRedirect();

        $this->actingAs($this->finance)->get(route('admin.quotes.show', $this->quote))
            ->assertOk()->assertSee('USD 1,000.00')->assertSee('≈ 3,672.50 AED')->assertSee('1 USD = 3.6725 AED · from provider');
    }

    #[Test]
    public function the_builder_shows_the_picker_the_rate_line_and_the_decimals_of_the_currency(): void
    {
        $this->save(['currency_code' => 'USD'])->assertRedirect();

        $this->actingAs($this->finance)->get(route('admin.quotes.edit', $this->quote))
            ->assertOk()->assertSee('id="cur-pick"', false)->assertSee('"3.67250000"', false)->assertSee('Change rate');
    }

    #[Test]
    public function CP_024_once_sent_the_currency_and_rate_are_fixed_and_a_change_gives_rate_locked(): void
    {
        $this->save(['currency_code' => 'USD'])->assertRedirect();
        $this->actingAs($this->finance)->post(route('admin.quotes.send', $this->quote), ['recipients' => ['Anyone']])->assertRedirect();
        $this->assertSame('sent', $this->quote->fresh()->status);

        $this->save(['currency_code' => 'AED'], true)->assertStatus(409)->assertJson(['code' => 'RATE_LOCKED', 'message' => 'The currency and rate cannot be changed after the quote is sent or the invoice is submitted.']);
        $this->save(['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '3.9'], true)->assertStatus(409)->assertJson(['code' => 'RATE_LOCKED']);

        $q = $this->quote->fresh();
        $this->assertSame(['USD', '3.67250000'], [$q->currency_code, (string) $q->exchange_rate]);
        // A sent quote is revised, never edited: its totals stay as sent.
        $this->save([], true)->assertStatus(422);
    }

    #[Test]
    public function CP_025_a_revision_keeps_the_currency_refills_the_rate_and_can_take_a_new_one(): void
    {
        $this->save(['currency_code' => 'USD'])->assertRedirect();
        $this->actingAs($this->finance)->post(route('admin.quotes.send', $this->quote), ['recipients' => ['Anyone']]);
        $this->changeRate($this->tenant, 'USD', '3.80000000'); // today's rate is now 3.80 (the quote carries 3.6725)

        $this->actingAs($this->finance)->post(route('admin.quotes.revise', $this->quote))->assertRedirect();

        $new = Quote::where('supersedes_quote_id', $this->quote->id)->sole();
        $this->assertSame([2, 'draft', 'USD', '3.80000000'], [$new->version_no, $new->status, $new->currency_code, (string) $new->exchange_rate]);
        $this->assertSame('3800.000', (string) $new->base_total);
        $this->assertSame('superseded', $this->quote->fresh()->status);

        $this->actingAs($this->finance)->put(route('admin.quotes.update', $new), $this->builderPayload(['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '3.75']))->assertRedirect();
        $this->assertSame('3.75000000', (string) $new->fresh()->exchange_rate);
    }

    #[Test]
    public function CP_026_the_client_link_page_and_pdf_show_usd_amounts_but_no_rate_and_no_base_amount(): void
    {
        $this->save(['currency_code' => 'USD'])->assertRedirect();
        $token = QuoteToken::create(['tenant_id' => $this->tenant->id, 'quote_id' => $this->quote->id, 'recipient_name' => 'Client', 'token' => QuoteToken::generate(), 'sent_via' => 'email', 'sent_at' => now()]);

        foreach (['/q/' . $token->token, '/q/' . $token->token . '/pdf'] as $path) {
            $res = $this->get($this->onHost($this->tenant, $path))->assertOk();
            $res->assertSee('USD')->assertSee('1,000.00');
            foreach (['3.6725', '3,672.50', '3672.5', 'from provider', 'entered by hand'] as $secret) {
                $res->assertDontSee($secret);
            }
        }
        $this->get($this->onHost($this->tenant, '/q/' . $token->token))->assertDontSee('AED');
    }

    #[Test]
    public function finance_builds_and_sends_quotes_through_quote_manage_and_the_menu_shows_it(): void
    {
        $this->actingAs($this->finance)->get(route('admin.quotes.index'))->assertOk();
        $this->actingAs($this->finance)->get(route('admin.quotes.build', $this->sr))->assertOk();
        $this->actingAs($this->finance)->get($this->onHost($this->tenant, '/dashboard'))->assertOk()->assertSee(route('admin.quotes.index'), false);

        $ops = $this->makeUser($this->tenant, 'operations_manager'); // quote.manage is view_only for this role: may look, may not build
        $this->actingAs($ops)->get(route('admin.quotes.index'))->assertOk();
        $this->actingAs($ops)->get(route('admin.quotes.build', $this->sr))->assertForbidden();
        $this->actingAs($this->makeUser($this->tenant, 'front_desk'))->get(route('admin.quotes.index'))->assertForbidden();
    }

    #[Test]
    public function a_starter_company_cannot_pick_another_currency_but_keeps_the_base_currency(): void
    {
        $t = $this->makeTenant($this->starterPlan(), slug: 'starterco');
        $u = $this->makeUser($t, 'finance');
        $sr = $this->makeServiceRequest($t, $this->makeClient($t), 'pending');
        $q  = $this->makeQuote($t, $this->makeClient($t, 'Other'), $sr);
        $this->enableCurrency($t, 'USD'); // a leftover row must not unlock it

        $this->actingAs($u)->putJson(route('admin.quotes.update', $q), $this->builderPayload(['currency_code' => 'USD']))
            ->assertStatus(403)->assertJson(['code' => 'FEATURE_NOT_IN_PLAN']);

        $this->actingAs($u)->get(route('admin.quotes.edit', $q))->assertOk()->assertDontSee('id="cur-pick"', false);
        $this->actingAs($u)->put(route('admin.quotes.update', $q), $this->builderPayload())->assertRedirect();
        $this->assertSame('AED', $q->fresh()->currency_code);
    }

    #[Test]
    public function totals_are_worked_out_on_the_server_with_the_7_7a_arithmetic(): void
    {
        $tax = \App\Models\TaxRate::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'VAT', 'rate_percent' => 10, 'is_active' => true]);

        $this->actingAs($this->finance)->put(route('admin.quotes.update', $this->quote), $this->builderPayload([
            'discount_type' => 'percent', 'discount_value' => 10,
            'lines' => [
                ['description' => 'Untaxed', 'quantity' => 1, 'unit_rate' => 200, 'tax_rate_id' => null],
                ['description' => 'Taxed', 'quantity' => 1, 'unit_rate' => 100, 'tax_rate_id' => $tax->id],
            ],
        ]))->assertRedirect();

        $q = $this->quote->fresh();
        $this->assertSame(['300.000', '30.000', '9.000', '279.000'], [(string) $q->subtotal, (string) $q->discount_total, (string) $q->tax_total, (string) $q->total]);
    }
}
