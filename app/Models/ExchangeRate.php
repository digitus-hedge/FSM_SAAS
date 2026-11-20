<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One stored rate for a company, currency pair and date: base units for one unit of from_currency (D2). */
class ExchangeRate extends Model
{
    use \App\Models\Concerns\AppTimezoneDates;

    protected $fillable = ['tenant_id', 'from_currency', 'to_currency', 'rate', 'rate_date', 'source'];

    protected $casts = [
        'rate_date' => 'date',
    ];
}
