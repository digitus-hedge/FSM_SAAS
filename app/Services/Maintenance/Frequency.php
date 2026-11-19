<?php

namespace App\Services\Maintenance;

use Carbon\Carbon;

/**
 * Calendar frequencies of a maintenance schedule (Preventive-Maintenance spec D1, 6.1). Dates are company-local dates.
 * Month steps keep the original day of the month where the month is long enough (31 Jan, 28 Feb, 31 Mar), because every
 * occurrence is counted from the first due date and never from the one before it.
 */
class Frequency
{
    private const STEP = [
        'weekly' => ['d', 7], 'monthly' => ['m', 1], 'quarterly' => ['m', 3], 'half_yearly' => ['m', 6], 'yearly' => ['m', 12],
    ];

    public static function valid(string $frequency, ?int $interval): bool
    {
        if (isset(self::STEP[$frequency])) {
            return true;
        }

        return match ($frequency) {
            'every_n_days'   => $interval !== null && $interval >= 7 && $interval <= 730,
            'every_n_months' => $interval !== null && $interval >= 1 && $interval <= 36,
            default          => false,
        };
    }

    /** The n-th occurrence, counting the first due date as 0. */
    public static function nth(Carbon $first, string $frequency, ?int $interval, int $n): Carbon
    {
        [$unit, $size] = self::STEP[$frequency] ?? match ($frequency) {
            'every_n_days'   => ['d', (int) $interval],
            'every_n_months' => ['m', (int) $interval],
        };
        $first = $first->copy()->startOfDay();

        return $unit === 'd' ? $first->addDays($size * $n) : $first->addMonthsNoOverflow($size * $n);
    }

    /** The first occurrence strictly after $date. */
    public static function after(Carbon $first, string $frequency, ?int $interval, Carbon $date): Carbon
    {
        $n = 0;
        while (($d = self::nth($first, $frequency, $interval, $n))->lte($date->copy()->startOfDay())) {
            $n++;
            if ($n > 20000) {
                break;
            }
        }

        return $d;
    }

    /** The first occurrence on or after $date. */
    public static function onOrAfter(Carbon $first, string $frequency, ?int $interval, Carbon $date): Carbon
    {
        return self::after($first, $frequency, $interval, $date->copy()->subDay());
    }

    /** All occurrences from $from to $to, both included. */
    public static function between(Carbon $first, string $frequency, ?int $interval, Carbon $from, Carbon $to): array
    {
        $out = [];
        $d   = self::onOrAfter($first, $frequency, $interval, $from);
        $n   = 0;
        while ($d->lte($to) && $n++ < 400) {
            $out[] = $d->copy();
            $d = self::after($first, $frequency, $interval, $d);
        }

        return $out;
    }
}
