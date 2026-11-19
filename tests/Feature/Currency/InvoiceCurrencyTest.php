<?php

namespace Tests\Feature\Currency;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/** Section 10.3, invoices: CP-027 to CP-036 (carried rate, locking, expenses at the invoice rate, rounding). */
class InvoiceCurrencyTest extends BillingTestCase
{
    private $tenant;
    private $finance;
    private $client;
    private $sr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->tenant  = $this->makeTenant();
        $this->finance = $this->makeUser($this->tenant, 'finance');
        $this->client  = $this->makeClient($this->tenant);
        $this->sr      = $this->makeServiceRequest($this->tenant, $this->client);
        $this->enableCurrency($this->tenant, 'USD'); // 3.6725 today
    }

    private function acceptedUsdQuote(string $rate = '3.70000000')
    {
        return $this->makeQuote($this->tenant, $this->client, $this->sr, ['status' => 'accepted', 'currency_code' => 'USD', 'exchange_rate' => $rate, 'rate_source' => 'manual']);
    }

    /** Open the builder (which makes the draft), then return the draft. */
    private function draft(): Invoice
    {
        $this->actingAs($this->finance)->get(route('admin.invoices.build', $this->sr))->assertOk();

        return Invoice::where('service_request_id', $this->sr->id)->sole();
    }

    private function save(Invoice $inv, array $extra = [], bool $json = false, ?array $lines = null)
    {
        $payload = $this->builderPayload($extra, 'due_date');
        if ($lines) {
            $payload['lines'] = $lines;
        }
        $req = $this->actingAs($this->finance);

        return $json ? $req->putJson(route('admin.invoices.update', $inv), $payload) : $req->put(route('admin.invoices.update', $inv), $payload);
    }

    private function expense(string $desc = 'Spare part', float $qty = 1, float $rate = 100): int
    {
        return DB::table('service_request_expenses')->insertGetId([
            'tenant_id' => $this->tenant->id, 'service_request_id' => $this->sr->id, 'logged_by_user_id' => 1,
            'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'expense_category_id' => 1, 'description' => $desc,
            'quantity' => $qty, 'unit_rate' => $rate, 'total_amount' => $qty * $rate, 'is_billable_to_client' => 1,
            'approval_status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[Test]
    public function CP_027_an_invoice_for_a_request_with_an_accepted_quote_starts_with_the_quotes_currency_and_rate(): void
    {
        $this->acceptedUsdQuote('3.70000000');

        $inv = $this->draft();

        $this->assertSame(['USD', '3.70000000', 'manual', '1000.000', '3700.000'], [$inv->currency_code, (string) $inv->exchange_rate, $inv->rate_source, (string) $inv->total, (string) $inv->base_total]);
        $this->assertSame(1, $inv->items()->count());
    }

    #[Test]
    public function CP_028_the_currency_cannot_be_changed_while_the_request_has_an_accepted_quote(): void
    {
        $this->acceptedUsdQuote();
        $inv = $this->draft();

        $this->save($inv, ['currency_code' => 'AED'], true)->assertStatus(409)->assertJson(['code' => 'RATE_LOCKED']);
        $this->assertSame('USD', $inv->fresh()->currency_code);
    }

    #[Test]
    public function CP_029_the_rate_can_change_on_a_draft_and_it_is_audited(): void
    {
        $this->acceptedUsdQuote('3.70000000');
        $inv = $this->draft();

        $this->save($inv, ['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '3.75'])->assertRedirect();

        $inv = $inv->fresh();
        $this->assertSame(['3.75000000', 'manual', '3750.000'], [(string) $inv->exchange_rate, $inv->rate_source, (string) $inv->base_total]);
        $log = ActivityLog::where('action', 'invoice.rate_changed')->where('item_id', $inv->id)->sole();
        $this->assertSame(['3.70000000', '3.75000000'], [$log->old_values['rate'], $log->new_values['rate']]);
    }

    #[Test]
    public function CP_030_submitting_fixes_the_currency_and_rate(): void
    {
        $this->acceptedUsdQuote();
        $inv = $this->draft();
        $this->actingAs($this->finance)->post(route('admin.invoices.submit', $inv))->assertRedirect();
        $this->assertSame('pending_approval', $inv->fresh()->status);

        $this->save($inv, ['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '3.9'], true)
            ->assertStatus(409)->assertJson(['code' => 'RATE_LOCKED', 'message' => 'The currency and rate cannot be changed after the quote is sent or the invoice is submitted.']);
        $this->save($inv, [], true)->assertStatus(422); // nothing else is editable either
        $this->assertSame('3.70000000', (string) $inv->fresh()->exchange_rate);
    }

    #[Test]
    public function CP_031_a_send_back_makes_it_a_draft_again_with_the_draft_rules(): void
    {
        $this->acceptedUsdQuote();
        $inv = $this->draft();
        $this->actingAs($this->finance)->post(route('admin.invoices.submit', $inv));

        // sending back needs invoice.approve (Company Admin, Operations Manager), not Finance (signed matrix)
        $admin = $this->makeUser($this->tenant, 'company_admin');
        $this->actingAs($this->finance)->post(route('admin.invoices.send-back', $inv), ['reason' => 'Check the rate'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.invoices.send-back', $inv), ['reason' => 'Check the rate'])->assertRedirect();
        $this->assertSame('draft', $inv->fresh()->status);

        $this->save($inv, ['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '3.72'])->assertRedirect();
        $this->assertSame('3.72000000', (string) $inv->fresh()->exchange_rate);
        $this->save($inv, ['currency_code' => 'AED'], true)->assertStatus(409); // still no currency change: the accepted quote
    }

    #[Test]
    public function CP_032_an_invoice_with_no_quote_chooses_its_currency_and_defaults_to_the_base_currency(): void
    {
        $inv = $this->draft();
        $this->assertSame(['AED', '1.00000000'], [$inv->currency_code, (string) $inv->exchange_rate]);

        $this->save($inv, ['currency_code' => 'USD'])->assertRedirect();

        $inv = $inv->fresh();
        $this->assertSame(['USD', '3.67250000', 'provider', '3672.500'], [$inv->currency_code, (string) $inv->exchange_rate, $inv->rate_source, (string) $inv->base_total]);
    }

    #[Test]
    public function CP_033_an_expense_of_100_aed_on_a_usd_invoice_at_3_6725_becomes_a_line_of_27_23_usd(): void
    {
        $inv = $this->draft();
        $this->save($inv, ['currency_code' => 'USD'])->assertRedirect();
        $id = $this->expense('Spare part', 1, 100);

        $this->actingAs($this->finance)->get(route('admin.invoices.edit', $inv))->assertSee('Approved expenses')->assertSee('Add as line')->assertSee('Spare part');
        $this->actingAs($this->finance)->post(route('admin.invoices.expenses.add', [$inv, $id]))->assertRedirect(route('admin.invoices.edit', $inv));

        $line = InvoiceItem::where('invoice_id', $inv->id)->where('source_expense_id', $id)->sole();
        $this->assertSame(['27.230', '27.230', '3.67250000'], [(string) $line->unit_rate, (string) $line->line_total, (string) $line->converted_at_rate]);
        $inv = $inv->fresh();
        $this->assertSame('1027.230', (string) $inv->total); // the 1,000 line from the builder plus the expense line
        $this->assertSame('3772.500', (string) $inv->base_total); // 1,027.23 x 3.6725
        // it is not offered twice, and not on another invoice either
        $this->actingAs($this->finance)->post(route('admin.invoices.expenses.add', [$inv, $id]))->assertStatus(422);
    }

    #[Test]
    public function an_expense_on_a_base_currency_invoice_is_not_converted(): void
    {
        $inv = $this->draft();
        $id = $this->expense('Parking', 2, 12.5);

        $this->actingAs($this->finance)->post(route('admin.invoices.expenses.add', [$inv, $id]))->assertRedirect();

        $line = InvoiceItem::where('source_expense_id', $id)->sole();
        $this->assertSame(['12.500', '25.000', null], [(string) $line->unit_rate, (string) $line->line_total, $line->converted_at_rate]);
    }

    #[Test]
    public function CP_034_a_later_rate_change_leaves_the_expense_line_alone_and_the_builder_says_so(): void
    {
        $inv = $this->draft();
        $this->save($inv, ['currency_code' => 'USD'])->assertRedirect();
        $id = $this->expense('Spare part', 1, 100);
        $this->actingAs($this->finance)->post(route('admin.invoices.expenses.add', [$inv, $id]));
        $this->actingAs($this->finance)->get(route('admin.invoices.edit', $inv))->assertDontSee('Lines from expenses were converted at');

        // Finance changes the rate; the page posts the lines back as they are, expense link included.
        $lines = [
            ['description' => 'Work done', 'quantity' => 1, 'unit_rate' => 1000, 'tax_rate_id' => null],
            ['description' => 'Spare part', 'quantity' => 1, 'unit_rate' => '27.230', 'tax_rate_id' => null, 'source_expense_id' => $id],
        ];
        $this->save($inv, ['currency_code' => 'USD', 'rate_manual' => 1, 'exchange_rate' => '3.70'], false, $lines)->assertRedirect();

        $line = InvoiceItem::where('source_expense_id', $id)->sole();
        $this->assertSame(['27.230', '3.67250000'], [(string) $line->unit_rate, (string) $line->converted_at_rate]);
        $this->actingAs($this->finance)->get(route('admin.invoices.edit', $inv))->assertSee('Lines from expenses were converted at 3.6725.');
    }

    #[Test]
    public function a_forged_expense_link_is_ignored(): void
    {
        $inv = $this->draft();
        $id = $this->expense();

        $this->save($inv, [], false, [['description' => 'Work done', 'quantity' => 1, 'unit_rate' => 10, 'tax_rate_id' => null, 'source_expense_id' => $id]])->assertRedirect();

        $this->assertNull(InvoiceItem::where('invoice_id', $inv->id)->sole()->source_expense_id);
    }

    #[Test]
    public function CP_035_a_base_currency_document_has_rate_1_and_no_rate_field(): void
    {
        $inv = $this->draft();
        $this->save($inv)->assertRedirect();

        $inv = $inv->fresh();
        $this->assertSame(['AED', '1.00000000', null], [$inv->currency_code, (string) $inv->exchange_rate, $inv->rate_source]);

        $starter = $this->makeTenant($this->starterPlan(), slug: 'starter2');
        $sr = $this->makeServiceRequest($starter, $this->makeClient($starter));
        $u = $this->makeUser($starter, 'finance');
        $this->actingAs($u)->get(route('admin.invoices.build', $sr))->assertOk();
        $this->actingAs($u)->get(route('admin.invoices.edit', Invoice::where('service_request_id', $sr->id)->sole()))
            ->assertDontSee('id="cur-pick"', false)->assertDontSee('id="rate-input"', false);
    }

    #[Test]
    public function CP_036_kwd_and_jpy_documents_are_stored_with_their_own_decimals(): void
    {
        foreach ([['KWD', 3, '3.704'], ['JPY', 0, '100.000']] as [$code, $dec, $expected]) {
            $t = $this->makeTenant(base: $code, slug: strtolower($code) . 'co');
            $u = $this->makeUser($t, 'finance');
            $sr = $this->makeServiceRequest($t, $this->makeClient($t));
            $this->actingAs($u)->get(route('admin.invoices.build', $sr))->assertOk();
            $inv = Invoice::withoutGlobalScopes()->where('service_request_id', $sr->id)->sole();

            $line = $code === 'KWD' ? ['quantity' => 3, 'unit_rate' => 1.2345] : ['quantity' => 3, 'unit_rate' => 33.333];
            $this->actingAs($u)->put(route('admin.invoices.update', $inv), $this->builderPayload(['lines' => [array_merge(['description' => 'Work', 'tax_rate_id' => null], $line)]], 'due_date'))->assertRedirect();

            $this->assertSame($expected, (string) $inv->fresh()->total, $code);
        }
    }

    #[Test]
    public function sent_approved_paid_and_void_documents_keep_the_totals_they_were_stored_with(): void
    {
        foreach (['sent', 'approved', 'partially_paid', 'overdue', 'paid', 'written_off', 'void'] as $status) {
            $client = $this->makeClient($this->tenant, 'C ' . $status);
            $inv = $this->makeInvoice($this->tenant, $client, ['status' => $status, 'total' => 123.45, 'subtotal' => 100, 'tax_total' => 23.45, 'balance' => 123.45]);

            $this->save($inv, [], true, [['description' => 'Changed', 'quantity' => 9, 'unit_rate' => 999, 'tax_rate_id' => null]])->assertStatus(422);

            $this->assertSame(['123.450', '100.000', '23.450'], [(string) $inv->fresh()->total, (string) $inv->fresh()->subtotal, (string) $inv->fresh()->tax_total], $status);
        }
    }

    #[Test]
    public function an_invoice_in_another_currency_is_refused_for_starter_and_for_a_currency_that_is_off(): void
    {
        $inv = $this->draft();
        \App\Models\TenantCurrency::where('currency_code', 'USD')->update(['is_active' => false]);
        $this->save($inv, ['currency_code' => 'USD'], true)->assertStatus(422)->assertJson(['code' => 'CURRENCY_NOT_ENABLED']);

        $starter = $this->makeTenant($this->starterPlan(), slug: 'starter3');
        $sr = $this->makeServiceRequest($starter, $this->makeClient($starter));
        $u = $this->makeUser($starter, 'finance');
        $this->actingAs($u)->get(route('admin.invoices.build', $sr));
        $si = Invoice::where('service_request_id', $sr->id)->sole();
        $this->actingAs($u)->putJson(route('admin.invoices.update', $si), $this->builderPayload(['currency_code' => 'USD'], 'due_date'))
            ->assertStatus(403)->assertJson(['code' => 'FEATURE_NOT_IN_PLAN']);
    }

    #[Test]
    public function after_a_downgrade_a_foreign_draft_keeps_its_currency_and_rate(): void
    {
        $this->acceptedUsdQuote();
        $inv = $this->draft();
        $this->tenant->plan->update(['features' => ['multi_currency' => false, 'billing_full' => true]]);

        $this->save($inv->fresh())->assertRedirect();

        $inv = $inv->fresh();
        $this->assertSame(['USD', '3.70000000'], [$inv->currency_code, (string) $inv->exchange_rate]);
    }
}
