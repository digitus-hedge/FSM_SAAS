<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'committed_at' => 'datetime',
    ];
}