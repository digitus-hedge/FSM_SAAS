<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** One row for each due date a schedule was worked on: the schedule's history. */
class PmScheduleRun extends Model
{
    use BelongsToTenant;

    public $timestamps = false;
    protected $fillable = ['tenant_id', 'pm_schedule_id', 'due_date', 'result', 'service_request_id', 'note', 'created_at'];
    protected $casts = ['due_date' => 'date', 'created_at' => 'datetime'];

    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
}
