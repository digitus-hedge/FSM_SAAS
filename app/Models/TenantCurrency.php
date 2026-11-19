<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A currency a company has turned on besides its base currency (D1). Turned off keeps the row and the history. */
class TenantCurrency extends Model
{
    protected $fillable = ['tenant_id', 'currency_code', 'is_active', 'created_by_user_id'];

    protected $casts = ['is_active' => 'boolean'];
}
