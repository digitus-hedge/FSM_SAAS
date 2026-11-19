<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DailySummaryRun extends Model
{
    use BelongsToTenant;

    public $timestamps = false;
    protected $fillable = ['tenant_id', 'user_id', 'run_date', 'sent_at', 'blocks_count'];
}
