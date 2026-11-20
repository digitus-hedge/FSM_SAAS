<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\DB;

/** A10: contract numbers use the CT prefix, CT-YYYY-000001 (blueprint decision D23, Contracts spec D20 and CC-004), from the company's own sequence. */
class ContractNumberTest extends ContractsTestCase
{
    public function test_the_first_contract_of_a_company_is_ct_year_000001_and_the_next_follows(): void
    {
        $this->assertSame('CT-2026-000001', $this->contract()->contract_number);
        $this->assertSame('CT-2026-000002', $this->contract()->contract_number);
    }

    public function test_the_number_comes_from_the_ct_row_of_tenant_sequences(): void
    {
        $this->contract();
        $this->contract();

        $row = DB::table('tenant_sequences')->where('tenant_id', $this->t->id)->where('prefix', 'CT')->first();

        $this->assertNotNull($row);
        $this->assertSame('CT-YYYY-000001', $row->pattern);
        $this->assertSame(3, (int) $row->next_number);
        $this->assertSame(0, DB::table('tenant_sequences')->where('tenant_id', $this->t->id)->where('prefix', 'CON')->count());
    }

    public function test_each_company_has_its_own_counter(): void
    {
        $this->contract();
        $this->contract();
        $other = $this->makeTenant(slug: 'other');
        $otherClient = $this->makeClient($other, 'Other client');

        $c = Contract::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'client_id' => $otherClient->id, 'contract_type' => 'amc', 'status' => 'draft', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $this->assertSame('CT-2026-000001', $c->contract_number);
    }

    public function test_the_counter_starts_again_in_a_new_year(): void
    {
        $this->contract();
        $this->contract();

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2027-01-05 10:00:00', 'UTC'));

        $this->assertSame('CT-2027-000001', $this->contract()->contract_number);
        $this->assertSame('CT-2027-000002', $this->contract()->contract_number);
    }

    public function test_a_company_that_changed_its_pattern_keeps_the_ct_prefix_and_width(): void
    {
        DB::table('tenant_sequences')->insert(['tenant_id' => $this->t->id, 'prefix' => 'CT', 'pattern' => 'CT-YYYY-0000001', 'reset_yearly' => 1, 'current_year' => 2026, 'next_number' => 41, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('CT-2026-0000041', $this->contract()->contract_number);
    }

    public function test_a_renewed_copy_gets_a_new_ct_number(): void
    {
        $old = $this->contract();

        $this->as('company_admin')->post($this->onHost($this->t, route('admin.contracts.renew', $old, false)))->assertSessionHasNoErrors();

        $copy = Contract::withoutGlobalScopes()->where('tenant_id', $this->t->id)->where('renewed_from_contract_id', $old->id)->first();
        $this->assertNotNull($copy);
        $this->assertSame('CT-2026-000002', $copy->contract_number);
    }
}
