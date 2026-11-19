<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PmScheduleAsset extends Model
{
    use BelongsToTenant;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $fillable = ['pm_schedule_id', 'asset_id', 'tenant_id'];

    public function asset() { return $this->belongsTo(Asset::class)->withTrashed(); }
}
