<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Sla\SlaMonitor;
use Illuminate\Console\Command;

class SlaCheck extends Command
{
    protected $signature = 'sla:check {--tenant= : Only this company id}';

    protected $description = 'SLA check: sends sla.warning at 80 percent of a target and sla.breached when it is missed, once for each request and target (every minute).';

    public function handle(SlaMonitor $monitor): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn ($t) => $t->isActive());

        foreach ($tenants as $tenant) {
            $r = $monitor->runForTenant($tenant);
            if ($r['warnings'] || $r['breaches'] || $r['silent']) {
                $this->line(sprintf('%s: checked %d, warnings %d, breaches %d, flagged without a message %d', $tenant->name ?? $tenant->id, $r['checked'], $r['warnings'], $r['breaches'], $r['silent']));
            }
        }

        return self::SUCCESS;
    }
}
