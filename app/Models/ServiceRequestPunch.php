<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequestPunch extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'service_request_id', 'technician_user_id', 'client_event_uuid',
        'punch_type', 'hold_reason_id', 'recorded_at_device', 'synced_to_server_at',
        'is_offline_sync', 'clock_drift_seconds', 'gps_lat', 'gps_lng',
        'gps_accuracy_meters', 'gps_status', 'site_distance_meters', 'is_within_geofence',
        'geofence_override_reason', 'geofence_override_by_user_id', 'sync_status',
        'conflict_note', 'device_info',
    ];

    protected $casts = [
        'recorded_at_device'  => 'datetime',
        'synced_to_server_at' => 'datetime',
        'is_offline_sync'     => 'boolean',
        'is_within_geofence'  => 'boolean',
        'gps_lat'             => 'decimal:7',
        'gps_lng'             => 'decimal:7',
    ];

    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
    public function technician()     { return $this->belongsTo(User::class, 'technician_user_id'); }
    public function holdReason()     { return $this->belongsTo(HoldReason::class); }
}