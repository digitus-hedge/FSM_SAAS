<?php

namespace App\Services\Currency;

/**
 * Money rounding (D22, blueprint 7.7a): every step rounds half up to the decimals of its currency.
 * Amounts and rates travel as strings to avoid float drift; storage keeps three decimals.
 */
class Money
{
    /** ISO 4217 minor units for the currencies we know. Anything else defaults to 2. */
    private const DECIMALS = [
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0,
        'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
    ];

    public static function decimals(?string $currency): int
    {
        return self::DECIMALS[strtoupper((string) $currency)] ?? 2;
    }

    /** Round half up (a tie goes away from zero) to the currency's decimals. Returns a plain number string. */
    public static function round(string|int|float $value, ?string $currency): string
    {
        $d = self::decimals($currency);

        // PHP's round() pre-rounds, so 1.005 gives 1.01 as a person expects.
        return number_format(round((float) $value, $d, PHP_ROUND_HALF_UP), $d, '.', '');
    }

    /** Amount in the document currency times its fixed rate, rounded to the base currency's decimals (D7). */
    public static function toBase(string|int|float $amount, string|int|float $rate, ?string $baseCurrency): string
    {
        return self::round((float) $amount * (float) $rate, $baseCurrency);
    }

    /** A rate with 8 decimals (D2, CP-010). */
    public static function rate(string|int|float $rate): string
    {
        return number_format((float) $rate, 8, '.', '');
    }

    /** For screens: thousands separators and the currency's own decimals. */
    public static function format(string|int|float|null $amount, ?string $currency): string
    {
        return number_format((float) $amount, self::decimals($currency));
    }
}
