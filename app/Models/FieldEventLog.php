<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldEventLog extends Model
{
    protected $table = 'field_event_log';

    protected $fillable = [
        'tenant_id', 'user_id', 'device_id', 'client_event_uuid', 'event_type',
        'service_request_id', 'recorded_at_device', 'synced_to_server_at',
        'clock_drift_seconds', 'result', 'conflict_code', 'conflict_note',
        'payload_hash', 'payload_json', 'acknowledged_at', 'reviewed_by_user_id',
        'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'recorded_at_device'  => 'datetime',
        'synced_to_server_at' => 'datetime',
        'payload_json'        => 'array',
        'acknowledged_at'     => 'datetime',
        'reviewed_at'         => 'datetime',
    ];

    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
    public function user()           { return $this->belongsTo(User::class); }
}