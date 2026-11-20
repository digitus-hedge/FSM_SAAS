<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\ProjectSite;
use Tests\Feature\Forms\FormsTestCase;

/**
 * Shared set-up for the contract tests (docs/specs/Contracts-and-Coverage.md). "Today" is 19 November 2026 (FormsTestCase).
 * The company is on a plan with the `contracts` feature.
 */
abstract class ContractsTestCase extends FormsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $plan = $this->t->plan;
        $plan->features = array_merge((array) $plan->features, ['contracts' => true]);
        $plan->save();
        $this->t = $this->t->fresh();
        foreach ($this->u as $k => $user) {
            $this->u[$k] = $user->fresh();
        }
    }

    protected function contract(array $over = [], array $siteIds = []): Contract
    {
        $c = Contract::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'contract_type' => 'amc', 'status' => 'active',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ], $over));
        if ($siteIds) {
            $c->sites()->syncWithPivotValues($siteIds, ['tenant_id' => $this->t->id]);
        }

        return $c;
    }

    protected function categoriesOf(Contract $c, array $categoryIds): void
    {
        $c->categories()->syncWithPivotValues($categoryIds, ['tenant_id' => $this->t->id]);
    }

    protected function audits(string $action): int
    {
        return \Illuminate\Support\Facades\DB::table('activity_logs')->where('tenant_id', $this->t->id)->where('action', $action)->count();
    }

    protected function notified(string $event, $user): int
    {
        return \App\Models\UserNotification::where('user_id', $user->id)->where('event_key', $event)->count();
    }

    protected function otherSite(): ProjectSite
    {
        return ProjectSite::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'name' => 'Second site']);
    }
}
