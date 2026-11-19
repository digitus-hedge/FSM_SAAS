<?php

namespace App\Services\Currency;

use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use App\Services\Permissions\PermissionLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * The currency and rate of a quote or an invoice (CUR-03, CUR-05; spec 6.3 and 6.4; D2, D4, D5, D6, D21).
 *
 * resolve() works out what a save should store, or refuses with a CurrencyException carrying the spec's error code:
 *   RATE_LOCKED (409), CURRENCY_NOT_ENABLED (422), VALIDATION_FAILED (422), FEATURE_NOT_IN_PLAN (403).
 * It never writes the document. The caller stores the attributes (with base_total from baseTotal()) and calls audit().
 */
class DocumentCurrency
{
    public const MSG_LOCKED = 'The currency and rate cannot be changed after the quote is sent or the invoice is submitted.';

    public function __construct(private CurrencyService $currencies, private AuditLogger $audit)
    {
    }

    public static function base(Tenant $tenant): string
    {
        return strtoupper($tenant->base_currency ?: 'AED');
    }

    /** Can this company pick another currency at all (plan multi_currency)? Starter keeps the base currency. */
    public function pickerAvailable(Tenant $tenant): bool
    {
        return $tenant->hasFeature('multi_currency');
    }

    /** Currencies shown in the picker: the base, the turned-on ones, and the document's own (it may since have been turned off). */
    public function options(Tenant $tenant, ?string $current = null): array
    {
        $codes = [self::base($tenant)];
        if ($this->pickerAvailable($tenant)) {
            $codes = array_merge($codes, $this->currencies->active($tenant)->pluck('currency_code')->all());
        }
        if ($current) {
            $codes[] = strtoupper($current);
        }

        return array_values(array_unique($codes));
    }

    /**
     * What a save of the builder should store.
     *
     * @param  Model  $doc     the Quote or Invoice (already saved, so its current currency and rate are known)
     * @param  array  $input   currency_code, exchange_rate, rate_manual (the "Change rate" box was used)
     * @param  bool   $locked  quote already sent, or invoice already submitted (D5)
     * @param  bool   $currencyFixed  invoice that starts from an accepted quote: its rate may change, its currency may not
     * @return array{currency_code: string, exchange_rate: string, rate_source: ?string, changed: bool}
     */
    public function resolve(Tenant $tenant, Model $doc, array $input, bool $locked = false, bool $currencyFixed = false): array
    {
        $base    = self::base($tenant);
        $current = strtoupper((string) ($doc->currency_code ?: $base));
        $wanted  = strtoupper(trim((string) ($input['currency_code'] ?? $current)));
        $hasRate = filled($input['exchange_rate'] ?? null);
        $manual  = filter_var($input['rate_manual'] ?? false, FILTER_VALIDATE_BOOLEAN) && $hasRate;
        $oldRate = (string) ($doc->exchange_rate ?? '1');

        $currencyChanges = $wanted !== $current;
        $rateChanges     = $manual && Money::rate($input['exchange_rate']) !== Money::rate($oldRate);

        // D5: fixed once a quote is sent or an invoice is submitted.
        if ($locked && ($currencyChanges || $rateChanges)) {
            throw new CurrencyException('RATE_LOCKED', self::MSG_LOCKED);
        }
        // D5: an invoice with an accepted quote keeps the quote's currency.
        if ($currencyFixed && $currencyChanges) {
            throw new CurrencyException('RATE_LOCKED', self::MSG_LOCKED);
        }

        if ($wanted === $base) {
            return $this->result($base, '1', null, $current !== $base || Money::rate($oldRate) !== Money::rate(1));
        }

        // A new, other currency needs the plan and must be turned on. A document's own currency stays allowed after a downgrade (D21).
        if ($currencyChanges) {
            if (! $this->pickerAvailable($tenant)) {
                throw new CurrencyException('FEATURE_NOT_IN_PLAN', 'Other currencies are not in your plan.');
            }
            if (! $this->currencies->active($tenant)->contains('currency_code', $wanted)) {
                throw new CurrencyException('CURRENCY_NOT_ENABLED', "{$wanted} is not turned on for your company.");
            }
        }

        if ($manual) {
            if (! is_numeric($input['exchange_rate']) || (float) $input['exchange_rate'] <= 0) {
                throw new CurrencyException('VALIDATION_FAILED', 'Enter a rate above 0.');
            }

            return $this->result($wanted, Money::rate($input['exchange_rate']), 'manual', $currencyChanges || $rateChanges);
        }

        // The rate only refills when the currency changes, or the document has no usable rate. A plain save keeps it.
        if (! $currencyChanges && (float) $oldRate > 0 && $oldRate !== Money::rate(1)) {
            return $this->result($wanted, Money::rate($oldRate), $doc->rate_source ?: 'provider', false);
        }

        $latest = $this->currencies->latestRate($tenant, $wanted);
        if (! $latest || (float) $latest->rate <= 0) {
            throw new CurrencyException('VALIDATION_FAILED', "Enter the rate for {$wanted}.");
        }

        return $this->result($wanted, Money::rate($latest->rate), $latest->source === 'manual' ? 'manual' : 'provider', true);
    }

    /** The newest stored rate of a currency for a document (D4), or null when none is stored. @return array{exchange_rate: string, rate_source: string}|null */
    public function latest(Tenant $tenant, string $currency): ?array
    {
        $row = $this->currencies->latestRate($tenant, $currency);

        return $row && (float) $row->rate > 0
            ? ['exchange_rate' => Money::rate($row->rate), 'rate_source' => $row->source === 'manual' ? 'manual' : 'provider']
            : null;
    }

    /** What the builder's picker shows: for each option, its rate for a new document and the date of that rate. */
    public function rateMap(Tenant $tenant, array $codes): array
    {
        $map = [];
        foreach ($codes as $code) {
            if ($code === self::base($tenant)) {
                continue;
            }
            $row = $this->currencies->latestRate($tenant, $code);
            $map[$code] = $row ? ['rate' => Money::rate($row->rate), 'date' => $row->rate_date->format('d M Y'), 'source' => $row->source] : null;
        }

        return $map;
    }

    /** base_total of a document: the total times the rate, rounded half up to the base currency's decimals (D2). */
    public static function baseTotal(string|float $total, string|float $rate, Tenant $tenant): string
    {
        return Money::toBase($total, $rate, self::base($tenant));
    }

    /** Audit a change of currency or rate with the old and new values (D4, spec section 7). */
    public function audit(string $action, Model $doc, Tenant $tenant, array $old, array $new): void
    {
        $this->audit->log($action, [
            'tenant_id' => $tenant->id,
            'item_type' => class_basename($doc),
            'item_id'   => $doc->getKey(),
            'old'       => $old,
            'new'       => $new,
        ]);
    }

    /** The values worth recording for an audit row. */
    public static function snapshot(Model $doc): array
    {
        return [
            'currency'    => $doc->currency_code,
            'rate'        => Money::rate($doc->exchange_rate ?? 1),
            'rate_source' => $doc->rate_source,
        ];
    }

    /** Who may change a currency or rate (D20): quote.manage for quotes, invoice.manage for invoices, at change level. */
    public static function mayChange(?\App\Models\User $user, string $kind): bool
    {
        return PermissionLevel::canChange($user, $kind === 'quote' ? 'quote.manage' : 'invoice.manage');
    }

    private function result(string $currency, string $rate, ?string $source, bool $changed): array
    {
        return ['currency_code' => $currency, 'exchange_rate' => $rate, 'rate_source' => $source, 'changed' => $changed];
    }
}
