<?php
namespace Database\Seeders;

use App\Models\HoldReason;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class HoldReasonSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::current()?->id;

        if (!$tenantId || !Tenant::whereKey($tenantId)->exists()) {
            $this->command?->warn('HoldReasonSeeder skipped: no tenant context.');
            return;
        }

        $reasons = [
            ['Waiting for client access or contact',      true,  false],
            ['Waiting for client approval',               true,  false],
            ["Waiting for the client's other contractor", true,  false],
            ['Waiting for parts',                         false, true],
            ['Bad weather',                               true,  false],
            ['Unsafe to continue',                        true,  true],
            ['Other',                                     false, true],
        ];

        foreach ($reasons as [$label, $pauses, $note]) {
            HoldReason::firstOrCreate(
                ['tenant_id' => $tenantId, 'label' => $label],
                ['pauses_sla' => $pauses, 'requires_note' => $note, 'is_active' => true]
            );
        }
    }
}