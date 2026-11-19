<?php

namespace Tests\Feature\Currency;

use App\Models\Invoice;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/** CP-082: the Reminders line on the invoice page (spec 5.8) with Pause and Resume. */
class InvoiceRemindersLineTest extends BillingTestCase
{
    private $tenant;
    private $finance;
    private Invoice $inv;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->tenant  = $this->makeTenant();
        $this->finance = $this->makeUser($this->tenant, 'finance');
        $this->inv     = $this->makeInvoice($this->tenant, $this->makeClient($this->tenant), [
            'status' => 'overdue', 'due_date' => '2026-11-01', 'reminder_count' => 2, 'last_reminder_at' => '2026-11-09 05:00:00',
        ]);
    }

    private function page($user = null, ?Invoice $inv = null)
    {
        return $this->actingAs($user ?? $this->finance)->get(route('admin.invoices.show', $inv ?? $this->inv));
    }

    #[Test]
    public function CP_082_the_invoice_page_shows_the_count_the_last_date_and_pause(): void
    {
        $this->page()->assertOk()->assertSee('2 of 4 reminders sent.')->assertSee('Last on 09 Nov 2026.')->assertSee('Pause reminders');
    }

    #[Test]
    public function the_max_comes_from_the_companys_settings(): void
    {
        \App\Models\TenantSetting::forTenant($this->tenant->id)->update(['overdue_max_reminders' => 6]);

        $this->page()->assertSee('2 of 6 reminders sent.');
    }

    #[Test]
    public function pausing_with_a_reason_shows_the_reason_and_resume_and_resuming_brings_pause_back(): void
    {
        $this->actingAs($this->finance)->post(route('admin.invoices.reminders.pause', $this->inv), ['reason' => 'Client is disputing the invoice'])
            ->assertRedirect(route('admin.invoices.show', $this->inv))->assertSessionHas('success', 'Reminders paused.');

        $this->page()->assertSee('Reminders paused: Client is disputing the invoice')->assertSee('Resume reminders')->assertDontSee('Pause reminders');

        $this->actingAs($this->finance)->post(route('admin.invoices.reminders.resume', $this->inv))->assertSessionHas('success', 'Reminders resumed.');
        $this->page()->assertSee('Pause reminders')->assertDontSee('Resume reminders');
    }

    #[Test]
    public function the_history_lists_each_reminder_and_the_pause(): void
    {
        \App\Models\InvoiceEvent::create(['tenant_id' => $this->tenant->id, 'invoice_id' => $this->inv->id, 'type' => 'reminder_sent', 'label' => 'Reminder 2 of 4 sent', 'occurred_at' => now()]);
        $this->actingAs($this->finance)->post(route('admin.invoices.reminders.pause', $this->inv), ['reason' => 'Dispute']);

        $this->page()->assertSee('Reminder sent')->assertSee('Reminder 2 of 4 sent')->assertSee('Reminders paused');
    }

    #[Test]
    public function an_operations_manager_sees_the_line_but_cannot_pause(): void
    {
        $ops = $this->makeUser($this->tenant, 'operations_manager');

        $this->page($ops)->assertOk()->assertSee('2 of 4 reminders sent.')->assertDontSee('Pause reminders');
    }

    #[Test]
    public function a_paid_or_draft_invoice_has_no_reminders_line_and_starter_never_has_one(): void
    {
        $paid = $this->makeInvoice($this->tenant, $this->makeClient($this->tenant, 'P'), ['status' => 'paid', 'balance' => 0]);
        $this->page(null, $paid)->assertOk()->assertDontSee('reminders sent.');

        $starter = $this->makeTenant($this->starterPlan(), slug: 'starterrem');
        $inv = $this->makeInvoice($starter, $this->makeClient($starter), ['status' => 'overdue', 'reminder_count' => 1]);
        $this->actingAs($this->makeUser($starter, 'finance'))->get(route('admin.invoices.show', $inv))->assertOk()->assertDontSee('reminders sent.');
    }
}
