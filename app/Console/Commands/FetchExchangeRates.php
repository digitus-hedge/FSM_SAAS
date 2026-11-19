<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Currency\RateFetcher;
use Illuminate\Console\Command;

class FetchExchangeRates extends Command
{
    protected $signature = 'rates:fetch {--tenant= : Only this company id}';

    protected $description = 'Daily exchange rates: store today\'s rate for every turned-on currency that has none yet (CUR-01). Safe to run again; only missing pairs are fetched.';

    public function handle(RateFetcher $fetcher): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->get()
            ->filter(fn ($t) => $t->isActive() && $t->hasFeature('multi_currency'));

        foreach ($tenants as $tenant) {
            $r = $fetcher->runForTenant($tenant);
            $this->line(sprintf('%s: stored %d, already had %d, failed %d', $tenant->name ?? $tenant->id, $r['stored'], $r['skipped'], $r['failed']));
        }

        return self::SUCCESS;
    }
}
