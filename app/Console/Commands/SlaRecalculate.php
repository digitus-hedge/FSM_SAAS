<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Sla\SlaClock;
use Illuminate\Console\Command;

class SlaRecalculate extends Command
{
    protected $signature = 'sla:recalculate {--tenant= : Only this company id}';

    protected $description = 'One-time fix: works the due times of open requests on a business-hours policy out again from the company hours and holidays.';

    public function handle(): int
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))->get()->filter(fn ($t) => $t->isActive());

        foreach ($tenants as $tenant) {
            $n = app(SlaClock::class)->recalculateOpen((int) $tenant->id);
            $this->line(sprintf('%s: %d request(s) got new due times', $tenant->name ?? $tenant->id, $n));
        }

        return self::SUCCESS;
    }
}
