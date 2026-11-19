<?php

namespace App\Services\Currency;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/** The Currencies and rates page rules (CUR-02, spec 6.2, D1, D19). */
class CurrencyService
{
    public function __construct(private RateProvider $provider, private AuditLogger $audit)
    {
    }

    /** Currencies the provider supports, for the Add currency list. Falls back to a built-in list when it is down. */
    public function supported(): array
    {
        return Cache::remember('rates.supported.' . config('rates.provider'), config('rates.supported_cache_seconds'), function () {
            try {
                return collect($this->provider->supportedCurrencies())->map(fn ($c) => strtoupper($c))->sort()->values()->all();
            } catch (RateProviderException) {
                return config('rates.fallback_currencies');
            }
        });
    }

    /** Turned-on currencies (active rows) of a company. */
    public function active(Tenant $tenant)
    {
        return TenantCurrency::query()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('currency_code')->get();
    }

    /** The newest stored rate of a currency, or null (spec 5.1: "Rate from {date}"). */
    public function latestRate(Tenant $tenant, string $code): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('tenant_id', $tenant->id)
            ->where('from_currency', strtoupper($code))->where('to_currency', strtoupper($tenant->base_currency))
            ->orderByDesc('rate_date')->first();
    }

    public function history(Tenant $tenant, string $code, int $limit = 60)
    {
        return ExchangeRate::query()
            ->where('tenant_id', $tenant->id)
            ->where('from_currency', strtoupper($code))->where('to_currency', strtoupper($tenant->base_currency))
            ->orderByDesc('rate_date')->limit($limit)->get();
    }

    public function enable(Tenant $tenant, string $code, ?User $user = null): TenantCurrency
    {
        $code = strtoupper(trim($code));
        $base = strtoupper((string) $tenant->base_currency);

        if (! preg_match('/^[A-Z]{3}$/', $code)) {
            throw new CurrencyException('VALIDATION_FAILED', 'Choose a currency.');
        }
        if ($code === $base) { // CP-012
            throw new CurrencyException('VALIDATION_FAILED', "{$base} is the base currency and is always turned on.");
        }
        if (! in_array($code, $this->supported(), true)) {
            throw new CurrencyException('VALIDATION_FAILED', "{$code} is not supported by the rates provider.");
        }

        $row = TenantCurrency::firstOrNew(['tenant_id' => $tenant->id, 'currency_code' => $code]);
        $row->is_active = true;
        $row->created_by_user_id ??= $user?->id;
        $row->save();

        $this->audit->log('currency.enabled', [
            'tenant_id' => $tenant->id, 'item_type' => 'tenant_currency', 'item_id' => $row->id,
            'new' => ['currency' => $code],
        ]);

        // Best effort: so the page shows a rate straight away. A failure here is not an error (6.1).
        app(RateFetcher::class)->runForTenant($tenant);

        return $row;
    }

    public function disable(Tenant $tenant, string $code, ?User $user = null): void
    {
        $code = strtoupper(trim($code));
        $row  = TenantCurrency::query()->where('tenant_id', $tenant->id)->where('currency_code', $code)->where('is_active', true)->first();
        if (! $row) {
            throw new CurrencyException('NOT_FOUND', 'Currency not found.');
        }

        if ($this->inUse($tenant, $code)) { // CP-013
            throw new CurrencyException('CURRENCY_IN_USE', "{$code} is used on open documents and cannot be turned off.");
        }

        $row->update(['is_active' => false]); // history is kept (CP-014)

        $this->audit->log('currency.disabled', [
            'tenant_id' => $tenant->id, 'item_type' => 'tenant_currency', 'item_id' => $row->id,
            'old' => ['currency' => $code],
        ]);
    }

    /** True when a draft or sent quote, or an invoice that is not paid, void or written off, uses the currency (6.2). */
    public function inUse(Tenant $tenant, string $code): bool
    {
        $code = strtoupper($code);

        return Quote::query()->where('tenant_id', $tenant->id)->where('currency_code', $code)
                ->whereIn('status', ['draft', 'sent'])->exists()
            || Invoice::query()->where('tenant_id', $tenant->id)->where('currency_code', $code)
                ->whereNotIn('status', ['paid', 'void', 'written_off'])->exists();
    }

    /** A manual rate for a date. Replaces the provider rate of that date and is audited (D19, 6.2). */
    public function addManualRate(Tenant $tenant, string $code, string $date, string|float $rate, ?User $user = null): ExchangeRate
    {
        $code = strtoupper(trim($code));
        $base = strtoupper((string) $tenant->base_currency);

        $active = TenantCurrency::query()->where('tenant_id', $tenant->id)->where('currency_code', $code)->exists();
        if (! $active || $code === $base) {
            throw new CurrencyException('NOT_FOUND', 'Currency not found.');
        }

        if (! is_numeric($rate) || (float) $rate <= 0) { // CP-016
            throw new CurrencyException('VALIDATION_FAILED', 'Enter a rate above 0.');
        }

        try {
            $day = Carbon::parse($date, $tenant->timezone ?: config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            throw new CurrencyException('VALIDATION_FAILED', 'Choose today or an earlier date.');
        }
        $today = Carbon::now($tenant->timezone ?: config('app.timezone'))->startOfDay();
        if ($day->gt($today)) { // CP-016
            throw new CurrencyException('VALIDATION_FAILED', 'Choose today or an earlier date.');
        }

        $existing = ExchangeRate::query()->where(['tenant_id' => $tenant->id, 'from_currency' => $code, 'to_currency' => $base])
            ->whereDate('rate_date', $day->toDateString())->first();
        $old = $existing ? ['rate' => (string) $existing->rate, 'source' => $existing->source] : null;

        $row = $existing ?? new ExchangeRate(['tenant_id' => $tenant->id, 'from_currency' => $code, 'to_currency' => $base, 'rate_date' => $day->toDateString()]);
        $row->rate   = Money::rate($rate);
        $row->source = 'manual';
        $row->save();

        $this->audit->log('rate.manual_added', [
            'tenant_id' => $tenant->id, 'item_type' => 'exchange_rate', 'item_id' => $row->id,
            'old' => $old, 'new' => ['currency' => $code, 'date' => $day->toDateString(), 'rate' => $row->rate, 'source' => 'manual'],
        ]);

        return $row;
    }
}
