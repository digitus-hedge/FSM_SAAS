<?php

namespace Tests\Feature\Contracts;

use App\Models\Asset;
use App\Models\ServiceRequest;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Services\Contracts\ContractCoverage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CNT-12: matching, overlaps, pins, the call cap and the plan gate (Contracts spec 6.2 to 6.8, CC-0xx).
 * The coverage service reads the current company, so each test makes the test company current.
 */
class ContractCoverageTest extends ContractsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tenant::setCurrent($this->t);
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        parent::tearDown();
    }

    protected function request(array $over = []): ServiceRequest
    {
        $sr = $this->raise($this->makeCategory());
        Tenant::setCurrent($this->t);                                  // an HTTP request resets the current company
        if ($over) {
            $sr->forceFill($over)->save();
        }

        return $sr->fresh();
    }

    protected function cov(): ContractCoverage
    {
        return app(ContractCoverage::class);
    }

    /* ---------- matching ---------- */

    public function test_one_matching_contract_covers_the_request(): void
    {
        $c = $this->contract();

        $r = $this->cov()->resolve($this->request());

        $this->assertSame('in_contract', $r['type']);
        $this->assertSame($c->id, $r['contract']->id);
        $this->assertFalse($r['needs_choice']);
        $this->assertStringContainsString($c->contract_number, $r['reason']);
    }

    public function test_no_matching_contract_means_out_of_coverage(): void
    {
        $r = $this->cov()->resolve($this->request());

        $this->assertSame('out_of_coverage', $r['type']);
        $this->assertNull($r['contract']);
    }

    /** @dataProvider notCandidates */
    public function test_a_contract_that_does_not_qualify_is_not_a_candidate(string $kind): void
    {
        $this->contract(match ($kind) {
            'draft'       => ['status' => 'draft'],
            'cancelled'   => ['status' => 'cancelled'],
            'expired'     => ['status' => 'expired', 'end_date' => '2026-10-31'],
            'ended'       => ['end_date' => '2026-11-18'],
            'not_started' => ['start_date' => '2026-11-20', 'end_date' => '2027-11-19'],
            'renewed'     => ['status' => 'renewed'],
            'other_client' => ['client_id' => $this->makeClient($this->t, 'Another client')->id],
        });

        $this->assertSame('out_of_coverage', $this->cov()->resolve($this->request())['type']);
    }

    public static function notCandidates(): array
    {
        return [['draft'], ['cancelled'], ['expired'], ['ended'], ['not_started'], ['renewed'], ['other_client']];
    }

    public function test_an_expiring_soon_contract_still_covers(): void
    {
        $this->contract(['status' => 'expiring_soon', 'end_date' => '2026-12-15']);

        $this->assertSame('in_contract', $this->cov()->resolve($this->request())['type']);
    }

    public function test_a_contract_with_sites_covers_only_those_sites(): void
    {
        $second = $this->otherSite();
        $this->contract([], [$second->id]);

        $this->assertSame('out_of_coverage', $this->cov()->resolve($this->request())['type']);       // the request is at the first site

        $this->contract([], [$this->site->id]);
        $this->assertSame('in_contract', $this->cov()->resolve($this->request())['type']);
    }

    public function test_a_contract_with_categories_covers_only_those_categories(): void
    {
        $other = $this->makeCategory();
        $c = $this->contract();
        $this->categoriesOf($c, [$other->id]);

        $sr = $this->request();
        $this->assertSame('out_of_coverage', $this->cov()->resolve($sr)['type']);

        $this->categoriesOf($c, [$sr->service_category_id]);
        $this->assertSame('in_contract', $this->cov()->resolve($sr)['type']);
    }

    /* ---------- overlaps ---------- */

    public function test_two_matching_contracts_need_a_choice_and_the_request_is_flagged(): void
    {
        $this->contract();
        $this->contract();
        $sr = $this->request();

        $r = $this->cov()->resolve($sr);
        $this->assertTrue($r['needs_choice']);
        $this->assertSame('several', $r['choice_reason']);

        $this->cov()->onCreated($sr);
        $this->assertTrue($sr->fresh()->contract_choice_pending);
        $this->assertSame('several', $sr->fresh()->contract_choice_reason);
        $this->assertGreaterThan(0, \App\Models\UserNotification::where('event_key', 'sr.contract_choice_needed')->count());
    }

    public function test_choosing_one_contract_settles_the_request_and_writes_the_audit_row(): void
    {
        $a = $this->contract();
        $this->contract();
        $sr = $this->request();
        $this->cov()->onCreated($sr);

        $this->cov()->choose($sr->fresh(), $a->id);

        $fresh = $sr->fresh();
        $this->assertSame($a->id, $fresh->contract_id);
        $this->assertSame('in_contract', $fresh->coverage_type);
        $this->assertFalse($fresh->contract_choice_pending);
        $this->assertSame(1, $this->audits('request.contract_chosen'));
        $this->assertSame($a->id, $this->cov()->resolve($fresh)['contract']->id);               // the choice sticks
    }

    public function test_a_contract_that_does_not_match_cannot_be_chosen(): void
    {
        $this->contract();
        $this->contract();
        $other = $this->contract(['status' => 'draft']);
        $sr = $this->request();

        $this->expectException(ValidationException::class);
        $this->cov()->choose($sr, $other->id);
    }

    /* ---------- pins ---------- */

    public function test_a_valid_pin_settles_a_request_without_asking_even_when_others_match(): void
    {
        $pinned = $this->contract();
        $this->contract();
        $asset = $this->makeAsset(null, ['default_contract_id' => $pinned->id]);
        $sr = $this->request(['primary_asset_id' => $asset->id]);

        $r = $this->cov()->resolve($sr);
        $this->assertFalse($r['needs_choice']);
        $this->assertSame($pinned->id, $r['contract']->id);

        $this->cov()->onCreated($sr);
        $this->assertFalse($sr->fresh()->contract_choice_pending);
        $this->assertSame($pinned->id, $sr->fresh()->contract_id);
    }

    /** @dataProvider ignoredPins */
    public function test_a_pin_that_no_longer_qualifies_is_ignored_and_normal_matching_runs(string $kind): void
    {
        $second = $this->otherSite();
        $pinned = match ($kind) {
            'expired'    => $this->contract(['end_date' => '2026-10-31']),
            'other_site' => $this->contract([], [$second->id]),
            'cancelled'  => $this->contract(['status' => 'cancelled']),
        };
        $this->contract();
        $this->contract();
        $asset = $this->makeAsset(null, ['default_contract_id' => $pinned->id]);
        $sr = $this->request(['primary_asset_id' => $asset->id]);

        $this->assertTrue($this->cov()->resolve($sr)['needs_choice']);          // the two ordinary contracts still need a choice
    }

    public static function ignoredPins(): array
    {
        return [['expired'], ['other_site'], ['cancelled']];
    }

    /* ---------- call cap ---------- */

    public function test_a_contract_at_its_cap_asks_the_manager(): void
    {
        $c = $this->contract(['max_calls' => 2, 'call_period' => 'contract_term', 'calls_used_current_period' => 2]);

        $r = $this->cov()->resolve($this->request());

        $this->assertTrue($r['needs_choice']);
        $this->assertSame('cap_reached', $r['choice_reason']);
        $this->assertSame($c->id, $r['contract']->id);
    }

    public function test_a_contract_below_its_cap_counts_the_next_call(): void
    {
        $this->contract(['max_calls' => 5, 'call_period' => 'contract_term', 'calls_used_current_period' => 2]);

        $r = $this->cov()->resolve($this->request());

        $this->assertFalse($r['needs_choice']);
        $this->assertStringContainsString('Call 3 of 5', $r['reason']);
    }

    public function test_a_flagged_cap_sends_contract_cap_reached_once(): void
    {
        $this->contract(['max_calls' => 1, 'call_period' => 'contract_term', 'calls_used_current_period' => 1]);
        $sr = $this->request();

        $result = $this->cov()->resolve($sr);
        $this->cov()->flagChoice($sr->fresh(), $result);
        $this->cov()->flagChoice($sr->fresh(), $result);

        $this->assertSame(1, \App\Models\UserNotification::where('event_key', 'contract.cap_reached')->where('user_id', $this->u['operations_manager']->id)->count());
    }

    public function test_counting_a_call_writes_the_ledger_and_giving_it_back_restores_it(): void
    {
        $c = $this->contract(['max_calls' => 5, 'call_period' => 'contract_term']);
        $sr = $this->request(['contract_id' => $c->id]);

        DB::transaction(fn () => $this->cov()->countCall($sr, \App\Models\Contract::lockForUpdate()->find($c->id)));
        $this->assertSame(1, (int) $c->fresh()->calls_used_current_period);
        $this->assertSame(1, DB::table('contract_calls')->where('service_request_id', $sr->id)->where('action', 'counted')->count());

        $this->cov()->giveBack($sr->fresh());
        $this->assertSame(0, (int) $c->fresh()->calls_used_current_period);
        $this->assertSame(1, DB::table('contract_calls')->where('service_request_id', $sr->id)->where('action', 'given_back')->count());

        $this->cov()->giveBack($sr->fresh());                                         // only once
        $this->assertSame(0, (int) $c->fresh()->calls_used_current_period);
    }

    public function test_a_call_is_not_given_back_after_the_first_punch_in(): void
    {
        $c = $this->contract(['max_calls' => 5, 'call_period' => 'contract_term']);
        $sr = $this->request(['contract_id' => $c->id]);
        DB::transaction(fn () => $this->cov()->countCall($sr, \App\Models\Contract::lockForUpdate()->find($c->id)));
        $sr->forceFill(['first_punch_in_at' => now()])->save();

        $this->cov()->giveBack($sr->fresh());

        $this->assertSame(1, (int) $c->fresh()->calls_used_current_period);
    }

    public function test_a_call_over_the_cap_is_marked_and_audited(): void
    {
        $c = $this->contract(['max_calls' => 1, 'call_period' => 'contract_term', 'calls_used_current_period' => 1]);
        $sr = $this->request(['contract_id' => $c->id]);

        DB::transaction(fn () => $this->cov()->countCall($sr, \App\Models\Contract::lockForUpdate()->find($c->id), true));

        $this->assertSame(1, DB::table('contract_calls')->where('service_request_id', $sr->id)->where('over_cap', 1)->count());
        $this->assertSame(1, $this->audits('contract.call_over_cap'));
    }

    /* ---------- SLA targets (CNT-07, D10) ---------- */

    public function test_a_contracts_sla_targets_replace_the_policys(): void
    {
        SlaPolicy::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'service_category_id' => null, 'priority' => 'medium', 'response_minutes' => 60, 'resolution_minutes' => 240]);
        $c = $this->contract(['response_sla_minutes' => 30]);                        // only the response target is set
        $sr = $this->request(['contract_id' => $c->id, 'coverage_type' => 'in_contract']);

        $this->assertSame([30, 240], $sr->fresh()->slaMinutes());
    }

    /* ---------- plan gate ---------- */

    public function test_without_the_contracts_feature_nothing_is_matched(): void
    {
        $this->contract();
        $plan = $this->t->plan;
        $plan->features = array_merge((array) $plan->features, ['contracts' => false]);
        $plan->save();
        Tenant::setCurrent($this->t->fresh());

        $r = $this->cov()->resolve($this->request());

        $this->assertFalse($this->cov()->enabled());
        $this->assertSame('out_of_coverage', $r['type']);
        $this->assertSame('Choose the coverage by hand.', $r['reason']);
    }

    public function test_the_contract_pages_are_behind_the_plan_gate(): void
    {
        $plan = $this->t->plan;
        $plan->features = array_merge((array) $plan->features, ['contracts' => false]);
        $plan->save();

        $this->as('company_admin')->get($this->onHost($this->t, '/admin/contracts'))->assertForbidden();
    }
}
