<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Billing\OverdueReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class OverdueDaily extends Command
{
    protected $signature = 'invoices:overdue-daily {--tenant= : Only this company id} {--force : Run now, whatever the company hour} {--hour=9 : Company hour to run at}';

    protected $description = 'Daily overdue job: mark invoices overdue, then send the reminders that are due (runs at 09:00 company time).';

    public function handle(OverdueReminderService $job): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn ($t) => $t->isActive());

        foreach ($tenants as $tenant) {
            $now = Carbon::now($tenant->timezone ?: config('app.timezone'));

            if (! $this->option('force') && (int) $now->format('G') !== (int) $this->option('hour')) {
                continue; // CP-085
            }

            $r = $job->runForTenant($tenant, $now->copy()->startOfDay());
            $this->line(sprintf('%s: marked overdue %d, reminders sent %d', $tenant->name ?? $tenant->id, $r['marked'], $r['reminders']));
        }

        return self::SUCCESS;
    }
}
