<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Services\Contracts\ContractDailyJob;
use Carbon\Carbon;

/**
 * CNT-08, CNT-09, CNT-14: the daily contract job (expiring soon, the 90, 60 and 30 day alerts, expiry, auto-renew) and the contract.renewed message.
 * Today is 19 November 2026.
 */
class ContractRenewalTest extends ContractsTestCase
{
    protected function runJob(): array
    {
        return app(ContractDailyJob::class)->runForTenant($this->t->fresh(), Carbon::parse('2026-11-19'));
    }

    /* ---------- expiring soon and the alerts ---------- */

    public function test_a_contract_with_90_days_left_becomes_expiring_soon_and_the_alert_goes_out_once(): void
    {
        $c = $this->contract(['end_date' => '2027-02-17']);                  // 90 days after 19 Nov 2026

        $this->runJob();
        $this->runJob();

        $this->assertSame('expiring_soon', $c->fresh()->status);
        $this->assertSame(1, $this->notified('contract.expiring', $this->u['company_admin']));
        $this->assertSame(1, $this->notified('contract.expiring', $this->u['operations_manager']));
        $this->assertSame(0, $this->notified('contract.expiring', $this->u['field_technician']));
    }

    /** @dataProvider thresholds */
    public function test_the_alert_goes_out_at_exactly_90_60_and_30_days(int $days, int $expected): void
    {
        $this->contract(['end_date' => Carbon::parse('2026-11-19')->addDays($days)->toDateString()]);

        $this->runJob();

        $this->assertSame($expected, $this->notified('contract.expiring', $this->u['operations_manager']));
    }

    public static function thresholds(): array
    {
        return [[90, 1], [60, 1], [30, 1], [89, 0], [45, 0], [31, 0]];
    }

    public function test_a_contract_with_more_than_90_days_left_stays_active(): void
    {
        $c = $this->contract(['end_date' => '2027-06-30']);

        $this->runJob();

        $this->assertSame('active', $c->fresh()->status);
    }

    public function test_a_contract_past_its_end_date_expires(): void
    {
        $c = $this->contract(['end_date' => '2026-11-18']);

        $this->runJob();

        $this->assertSame('expired', $c->fresh()->status);
    }

    public function test_a_contract_with_a_successor_is_marked_renewed_not_expired(): void
    {
        $old = $this->contract(['end_date' => '2026-11-18']);
        $this->contract(['start_date' => '2026-11-19', 'end_date' => '2027-11-18', 'renewed_from_contract_id' => $old->id]);

        $this->runJob();

        $this->assertSame('renewed', $old->fresh()->status);
    }

    /* ---------- auto-renew and contract.renewed (D12) ---------- */

    protected function autoRenewing(array $over = [], array $sites = []): Contract
    {
        return $this->contract(array_merge(['auto_renew' => true, 'start_date' => '2025-11-19', 'end_date' => '2026-11-18', 'max_calls' => 12, 'call_period' => 'annual', 'response_sla_minutes' => 60], $over), $sites);
    }

    public function test_an_auto_renew_contract_gets_a_successor_for_the_same_length_and_the_old_one_is_renewed(): void
    {
        $old = $this->autoRenewing([], [$this->site->id]);

        $r = $this->runJob();

        $new = Contract::withoutGlobalScopes()->where('renewed_from_contract_id', $old->id)->first();
        $this->assertSame(1, $r['auto_renewed']);
        $this->assertNotNull($new);
        $this->assertSame('2026-11-19', $new->start_date->toDateString());
        $this->assertSame('2027-11-18', $new->end_date->toDateString());
        $this->assertSame('active', $new->status);
        $this->assertNotSame($old->contract_number, $new->contract_number);
        $this->assertStringStartsWith('CT-2026-', $new->contract_number);
        $this->assertNotNull($new->auto_renewed_at);
        $this->assertSame(12, (int) $new->max_calls);
        $this->assertSame(0, (int) $new->calls_used_current_period);
        $this->assertSame([$this->site->id], $new->sites()->pluck('project_sites.id')->all());
        $this->assertSame('renewed', $old->fresh()->status);
        $this->assertSame(1, $this->audits('contract.auto_renewed'));
    }

    public function test_contract_renewed_goes_in_app_to_the_company_admin_and_the_operations_manager_only(): void
    {
        $old = $this->autoRenewing();

        $this->runJob();

        $new = Contract::withoutGlobalScopes()->where('renewed_from_contract_id', $old->id)->first();
        foreach (['company_admin', 'operations_manager'] as $role) {
            $n = \App\Models\UserNotification::where('user_id', $this->u[$role]->id)->where('event_key', 'contract.renewed')->first();
            $this->assertNotNull($n, $role);
            $this->assertSame('Contract ' . $old->contract_number . ' was renewed', $n->title);
            $this->assertStringContainsString($new->contract_number, $n->body);
            $this->assertStringContainsString('19 Nov 2026', $n->body);
            $this->assertStringContainsString('18 Nov 2027', $n->body);
            $this->assertNull($n->emailed_at);                                    // in-app only
        }
        foreach (['finance', 'front_desk', 'team_lead', 'field_technician'] as $role) {
            $this->assertSame(0, $this->notified('contract.renewed', $this->u[$role]), $role);
        }
    }

    public function test_the_job_does_not_renew_twice(): void
    {
        $old = $this->autoRenewing();

        $this->runJob();
        $this->runJob();

        $this->assertSame(1, Contract::withoutGlobalScopes()->where('renewed_from_contract_id', $old->id)->count());
        $this->assertSame(1, $this->notified('contract.renewed', $this->u['company_admin']));
    }

    public function test_a_contract_without_auto_renew_is_not_renewed_and_sends_no_renewed_message(): void
    {
        $old = $this->autoRenewing(['auto_renew' => false]);

        $this->runJob();

        $this->assertSame(0, Contract::withoutGlobalScopes()->where('renewed_from_contract_id', $old->id)->count());
        $this->assertSame(0, $this->notified('contract.renewed', $this->u['company_admin']));
    }

    public function test_renewing_by_hand_makes_a_draft_and_sends_no_renewed_message(): void
    {
        $old = $this->contract();

        $this->as('company_admin')->post($this->onHost($this->t, route('admin.contracts.renew', $old, false)))->assertSessionHasNoErrors();

        $draft = Contract::withoutGlobalScopes()->where('renewed_from_contract_id', $old->id)->first();
        $this->assertSame('draft', $draft->status);
        $this->assertSame(0, $this->notified('contract.renewed', $this->u['company_admin']));      // the message is for renewal by itself (D12)
    }

    public function test_another_companys_contracts_are_not_touched(): void
    {
        $other = $this->makeTenant(slug: 'other');
        $theirs = Contract::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'client_id' => $this->makeClient($other, 'Theirs')->id, 'contract_type' => 'amc', 'status' => 'active', 'auto_renew' => true, 'start_date' => '2025-11-19', 'end_date' => '2026-11-18']);

        $this->runJob();                                                    // runs for the first company only

        $this->assertSame('active', $theirs->fresh()->status);
        $this->assertSame(0, Contract::withoutGlobalScopes()->where('renewed_from_contract_id', $theirs->id)->count());
    }
}
