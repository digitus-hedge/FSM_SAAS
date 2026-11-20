<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandFont extends Model
{
    use \App\Models\Concerns\AppTimezoneDates;

    protected $fillable = ['key', 'name', 'google_param', 'body_family', 'display_family', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}