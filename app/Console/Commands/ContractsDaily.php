<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Contracts\ContractDailyJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ContractsDaily extends Command
{
    protected $signature = 'contracts:daily {--tenant= : Only this company id} {--force : Run now, whatever the company hour} {--hour=2 : Company hour to run at}';

    protected $description = 'Daily contract job: expire, expiring soon, alerts, auto-renew, roll call periods (runs at 02:00 company time).';

    public function handle(ContractDailyJob $job): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn ($t) => $t->isActive() && $t->hasFeature('contracts'));

        foreach ($tenants as $tenant) {
            $tz  = $tenant->timezone ?: config('app.timezone');
            $now = Carbon::now($tz);

            if (! $this->option('force') && (int) $now->format('G') !== (int) $this->option('hour')) {
                continue;
            }

            $r = $job->runForTenant($tenant, $now->copy()->startOfDay());
            $this->line(sprintf(
                '%s: expired %d, marked renewed %d, expiring soon %d, alerts %d, auto-renewed %d, periods rolled %d',
                $tenant->name ?? $tenant->id, $r['expired'], $r['renewed_marked'], $r['expiring_soon'], $r['alerts'], $r['auto_renewed'], $r['periods_rolled']
            ));
        }

        return self::SUCCESS;
    }
}
