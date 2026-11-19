<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Notifications\DailySummary;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SummaryDaily extends Command
{
    protected $signature = 'summary:daily {--tenant= : Only this company id}';

    protected $description = 'Send the daily summaries at the configured company time.';

    public function handle(DailySummary $summary): int
    {
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))->get()
            ->filter(fn ($t) => $t->isActive() && $t->hasFeature('reports_full'));

        foreach ($tenants as $tenant) {
            $now = Carbon::now($tenant->timezone ?: config('app.timezone'));
            $r = $summary->runForTenant($tenant, $now);
            $this->line(sprintf('%s: sent %d, nothing to say %d', $tenant->name ?? $tenant->id, $r['sent'], $r['empty']));
        }

        return self::SUCCESS;
    }
}
