<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Maintenance\PmGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PmDaily extends Command
{
    protected $signature = 'pm:daily {--tenant= : Only this company id} {--force : Run now, whatever the company hour} {--hour=6 : Company hour to run at}';

    protected $description = 'Maintenance job: create the requests of schedules that are due and tell about overdue ones (runs at 06:00 company time).';

    public function handle(PmGenerator $generator): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn ($t) => $t->isActive() && $t->hasFeature('pm_time'));

        foreach ($tenants as $tenant) {
            $tz  = $tenant->timezone ?: config('app.timezone');
            $now = Carbon::now($tz);

            if (! $this->option('force') && (int) $now->format('G') !== (int) $this->option('hour')) {
                continue;
            }

            $r = $generator->runForTenant($tenant, $now->copy()->startOfDay());
            $this->line(sprintf(
                '%s: created %d, skipped (open) %d, waiting for a contract %d, errors %d, ended %d, overdue messages %d, left for the next run %d',
                $tenant->name ?? $tenant->id, $r['created'], $r['skipped_open'], $r['needs_contract'], $r['errors'], $r['ended'], $r['overdue'], $r['left_over']
            ));
        }

        return self::SUCCESS;
    }
}
