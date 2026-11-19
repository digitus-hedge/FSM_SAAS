<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DailySummaryConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'base_role', 'send_time', 'days_of_week_json', 'content_blocks_json', 'is_active', 'user_id', 'event_key', 'channel', 'frequency'];

    protected $casts = ['days_of_week_json' => 'array', 'content_blocks_json' => 'array', 'is_active' => 'boolean'];
}
