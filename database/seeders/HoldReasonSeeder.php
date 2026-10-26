<?php
namespace Database\Seeders;

use App\Models\HoldReason;
use Illuminate\Database\Seeder;

class HoldReasonSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = \App\Models\Tenant::current()?->id ?? 1;

        $reasons = [
            ['Waiting for client access or contact',   true,  false],
            ['Waiting for client approval',            true,  false],
            ["Waiting for the client's other contractor", true, false],
            ['Waiting for parts',                      false, true],
            ['Bad weather',                            true,  false],
            ['Unsafe to continue',                     true,  true],
            ['Other',                                  false, true],
        ];

        foreach ($reasons as [$label, $pauses, $note]) {
            HoldReason::firstOrCreate(
                ['tenant_id' => $tenantId, 'label' => $label],
                ['pauses_sla' => $pauses, 'requires_note' => $note, 'is_active' => true]
            );
        }
    }
}