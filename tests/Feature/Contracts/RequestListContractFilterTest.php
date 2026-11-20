<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;

/** Contracts spec D17: the request list can be filtered by contract. */
class RequestListContractFilterTest extends ContractsTestCase
{
    protected function list(array $query = []): string
    {
        return $this->as('operations_manager')->get($this->onHost($this->t, '/service-requests?' . http_build_query($query)))->assertOk()->getContent();
    }

    /** The request numbers in the table of the list (the bell in the header also names requests). */
    protected function rows(array $query = []): array
    {
        preg_match_all('/class="sr-num">([^<]+)</', $this->list($query), $m);

        return $m[1];
    }

    public function test_the_list_filters_by_contract(): void
    {
        $a = $this->contract();
        $b = $this->contract();
        $srA = $this->raise($this->makeCategory(['name' => 'A']), ['title' => 'Alpha job']);
        $srB = $this->raise($this->makeCategory(['name' => 'B']), ['title' => 'Bravo job']);
        $this->raise($this->makeCategory(['name' => 'C']), ['title' => 'Charlie job']);
        $srA->forceFill(['contract_id' => $a->id])->save();
        $srB->forceFill(['contract_id' => $b->id])->save();

        $this->assertSame([$srA->sr_number], $this->rows(['contract_id' => $a->id]));
        $this->assertSame([$srB->sr_number], $this->rows(['contract_id' => $b->id]));
    }

    public function test_without_the_filter_every_request_shows(): void
    {
        $a = $this->contract();
        $srA = $this->raise($this->makeCategory());
        $srNone = $this->raise($this->makeCategory());
        $srA->forceFill(['contract_id' => $a->id])->save();

        $this->assertEqualsCanonicalizing([$srA->sr_number, $srNone->sr_number], $this->rows());
    }

    public function test_the_filter_lists_the_contracts_of_the_company_only_when_contracts_are_on(): void
    {
        $a = $this->contract();
        $other = $this->makeTenant(slug: 'other');
        $row = fn () => Contract::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'client_id' => $this->makeClient($other, 'Theirs')->id, 'contract_type' => 'amc', 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $row();
        $theirs = $row();                                              // numbers are per company: this one is CT-2026-000002, mine is 000001

        $html = $this->list();

        $this->assertStringContainsString('name="contract_id"', $html);
        $this->assertStringContainsString('>' . $a->contract_number . '<', $html);
        $this->assertStringNotContainsString($theirs->contract_number, $html);

        $plan = $this->t->plan;
        $plan->features = array_merge((array) $plan->features, ['contracts' => false]);
        $plan->save();
        $this->assertStringNotContainsString('name="contract_id"', $this->list());
    }

    public function test_a_junk_contract_id_is_ignored(): void
    {
        $sr = $this->raise($this->makeCategory());

        $this->assertSame([$sr->sr_number], $this->rows(['contract_id' => 'abc']));
    }
}
