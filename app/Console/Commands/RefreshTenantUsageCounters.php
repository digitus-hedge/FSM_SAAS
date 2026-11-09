<?php

namespace App\Console\Commands;

use App\Models\MessageLog;
use App\Models\Tenant;
use App\Models\TenantUsageCounter;
use App\Models\User;
use Illuminate\Console\Command;

class RefreshTenantUsageCounters extends Command
{
    protected $signature = 'usage:refresh';

    protected $description = 'Recompute each company\'s active-user and message counts for the current month (blueprint 7.9a: "Counters are checked every night").';

    public function handle(): int
    {
        Tenant::query()->chunk(50, function ($tenants) {
            foreach ($tenants as $tenant) {
                $this->refreshForTenant($tenant);
            }
        });

        return self::SUCCESS;
    }

    private function refreshForTenant(Tenant $tenant): void
    {
        $now = now($tenant->timezone ?? 'UTC');
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        // Blueprint 7.9a: "Staff, technicians and vendor technicians count as users.
        // Client users do not." Vendor technicians are user_type='technician' with
        // vendor_id set, so this is covered by user_type alone.
        $userCount = User::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereIn('user_type', ['staff', 'technician'])
            ->count();

        $whatsappCount = MessageLog::where('tenant_id', $tenant->id)
            ->where('channel', 'whatsapp')
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $emailCount = MessageLog::where('tenant_id', $tenant->id)
            ->where('channel', 'email')
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        TenantUsageCounter::updateOrCreate(
            ['tenant_id' => $tenant->id, 'year' => $year, 'month' => $month],
            [
                'user_count' => $userCount,
                'whatsapp_message_count' => $whatsappCount,
                'email_message_count' => $emailCount,
            ]
        );
    }
}