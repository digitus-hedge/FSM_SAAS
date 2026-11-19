<?php

namespace App\Services\Billing;

use App\Services\Currency\Money;

/**
 * The arithmetic of blueprint 7.7a for a quote or an invoice (spec 6.3), in the document's currency:
 *   1. each line = quantity x rate, rounded half up to the currency's decimals;
 *   2. one discount for the whole document (a percentage of the subtotal, or an amount), shared over the lines by line total;
 *   3. tax for each line, on the line after its share of the discount;
 *   4. total = subtotal - discount + tax.
 * Every step rounds half up to the decimals of the currency (D22). Sums are kept in whole minor units, so no float drift.
 *
 * Only DRAFT documents are ever worked out again. A sent, approved, paid or void document keeps the totals it was stored with:
 * callers must check the status before calling this.
 */
class DocumentTotals
{
    /**
     * @param  array<int, array{quantity: mixed, unit_rate: mixed, tax_percent?: mixed}>  $lines
     * @return array{lines: array<int, string>, subtotal: string, discount_total: string, tax_total: string, total: string}
     *         amounts are plain numbers with the currency's decimals
     */
    public static function compute(array $lines, string $discountType, mixed $discountValue, string $currency): array
    {
        $dec    = Money::decimals($currency);
        $factor = 10 ** $dec;
        $minor  = fn (float|string $v): int => (int) round(round((float) $v, $dec, PHP_ROUND_HALF_UP) * $factor);

        // 1. Lines
        $lineMinor = [];
        foreach ($lines as $i => $ln) {
            $lineMinor[$i] = $minor((float) $ln['quantity'] * (float) $ln['unit_rate']);
        }
        $subtotal = array_sum($lineMinor);

        // 2. One discount, never more than the subtotal
        $discount = match ($discountType) {
            'percent' => $minor($subtotal / $factor * (float) $discountValue / 100),
            'amount'  => $minor((float) $discountValue),
            default   => 0,
        };
        $discount = max(0, min($discount, $subtotal));

        // Shared over the lines by line total. The last line takes the rounding remainder, so the shares add up exactly.
        $share = [];
        $given = 0;
        $keys  = array_keys($lineMinor);
        $last  = end($keys);
        foreach ($lineMinor as $i => $m) {
            if ($i === $last) {
                $share[$i] = $discount - $given;
            } else {
                $share[$i] = $subtotal > 0 ? (int) round($discount * $m / $subtotal) : 0;
                $given += $share[$i];
            }
        }

        // 3. Tax for each line on what is left of it
        $tax = 0;
        foreach ($lineMinor as $i => $m) {
            $pct  = (float) ($lines[$i]['tax_percent'] ?? 0);
            $tax += $minor((($m - $share[$i]) / $factor) * $pct / 100);
        }

        // 4. Total
        $total = max(0, $subtotal - $discount + $tax);

        $fmt = fn (int $m): string => number_format($m / $factor, $dec, '.', '');

        return [
            'lines'          => array_map($fmt, $lineMinor),
            'subtotal'       => $fmt($subtotal),
            'discount_total' => $fmt($discount),
            'tax_total'      => $fmt($tax),
            'total'          => $fmt($total),
        ];
    }
}
