<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantUsageCounter;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        // Blueprint 7.9a: staff, technicians and vendor technicians count as users; client users do not.
        // Client users always carry a client_id, so active users without one are the ones that count.
        $userCount = User::where('tenant_id', $tenant->id)
            ->whereNull('deactivated_at')
            ->whereNull('client_id')
            ->count();

        // Message logs are not built yet. Until the table exists, both counts are 0.
        $messages = fn (string $channel) => Schema::hasTable('message_logs')
            ? DB::table('message_logs')
                ->where('tenant_id', $tenant->id)
                ->where('channel', $channel)
                ->where('direction', 'outbound')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count()
            : 0;

        TenantUsageCounter::updateOrCreate(
            ['tenant_id' => $tenant->id, 'year' => $year, 'month' => $month],
            [
                'user_count' => $userCount,
                'whatsapp_message_count' => $messages('whatsapp'),
                'email_message_count' => $messages('email'),
            ]
        );
    }
}