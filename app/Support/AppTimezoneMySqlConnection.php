<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Carbon;

/**
 * The query side of the time zone rule (see Models\Concerns\AppTimezoneDates): a date or Carbon given as a query value or an insert or
 * update value, in any time zone, is converted to the application zone before it is formatted, because that is the zone the columns are read in.
 * Without this `->where('created_at', '>=', $carbonInUtc)` compared the wrong wall-clock time.
 */
class AppTimezoneMySqlConnection extends MySqlConnection
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = Carbon::instance($value)->setTimezone(config('app.timezone'));
            }
        }

        return parent::prepareBindings($bindings);
    }
}
