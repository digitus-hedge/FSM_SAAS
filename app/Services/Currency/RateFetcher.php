<?php

namespace App\Services\Currency;

use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Models\TenantCurrency;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The daily rates job for one company (CUR-01, spec 6.1).
 *  - Reads each turned-on currency against the base currency and stores one row for the company's date, source provider.
 *  - Never overwrites a manual rate and never adds a second row for the same pair and date (safe to run again and again).
 *  - A provider failure is logged and leaves the latest stored rate in use. The schedule runs it again later in the day.
 *  - A rate of 0 or below is never stored.
 */
class RateFetcher
{
    /** Provider answers cached for this run, so a base currency is asked for once however many companies share it. */
    private array $cache = [];

    public function __construct(private RateProvider $provider)
    {
    }

    /** @return array{stored:int, skipped:int, failed:int} */
    public function runForTenant(Tenant $tenant, ?Carbon $now = null): array
    {
        $out  = ['stored' => 0, 'skipped' => 0, 'failed' => 0];
        $base = strtoupper((string) $tenant->base_currency);
        $date = ($now ?? Carbon::now())->copy()->setTimezone($tenant->timezone ?: config('app.timezone'))->toDateString();

        $codes = TenantCurrency::query()
            ->where('tenant_id', $tenant->id)->where('is_active', true)
            ->pluck('currency_code')->map(fn ($c) => strtoupper($c))
            ->reject(fn ($c) => $c === $base)->values();

        if ($codes->isEmpty()) {
            return $out; // CP-006: a company with no turned-on currencies is skipped
        }

        // Only the pairs that have no rate for the company's date yet (provider or manual).
        $have = ExchangeRate::query()
            ->where('tenant_id', $tenant->id)->where('to_currency', $base)->whereDate('rate_date', $date)
            ->pluck('from_currency')->all();
        $missing = $codes->reject(fn ($c) => in_array($c, $have, true))->values()->all();
        $out['skipped'] = $codes->count() - count($missing);

        if (! $missing) {
            return $out;
        }

        try {
            $rates = $this->ratesFor($base, $missing);
        } catch (RateProviderException $e) {
            Log::warning('Exchange rates not fetched', ['tenant' => $tenant->id, 'base' => $base, 'error' => $e->getMessage()]);
            $out['failed'] = count($missing);
            return $out;
        }

        foreach ($missing as $code) {
            $rate = $rates[$code] ?? null;
            if ($rate === null || (float) $rate <= 0) { // CP-009
                $out['failed']++;
                continue;
            }

            $row = ExchangeRate::firstOrCreate(
                ['tenant_id' => $tenant->id, 'from_currency' => $code, 'to_currency' => $base, 'rate_date' => $date],
                ['rate' => Money::rate($rate), 'source' => 'provider'],
            );
            $row->wasRecentlyCreated ? $out['stored']++ : $out['skipped']++;
        }

        return $out;
    }

    private function ratesFor(string $base, array $codes): array
    {
        $key = $base . ':' . implode(',', $codes);

        return $this->cache[$key] ??= $this->provider->ratesToBase($base, $codes);
    }
}
