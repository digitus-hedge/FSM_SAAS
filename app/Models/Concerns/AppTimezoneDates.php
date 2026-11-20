<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Eloquent formats a Carbon in the Carbon's OWN time zone, but reads a date column back in the application's zone (config app.timezone).
 * A Carbon made in the company's zone, in UTC or from a string with an offset was therefore saved as the wrong wall-clock time.
 * This converts any date to the application zone before it is formatted, so a Carbon in any zone is saved as the same instant.
 *
 * Used by every model (BelongsToTenant brings it in for the company tables). Query values, which do not pass through a model, are
 * converted the same way by App\Support\AppTimezoneMySqlConnection.
 */
trait AppTimezoneDates
{
    public function fromDateTime($value)
    {
        if ($value instanceof DateTimeInterface) {
            $value = Carbon::instance($value)->setTimezone(config('app.timezone'));
        }

        return parent::fromDateTime($value);
    }
}
