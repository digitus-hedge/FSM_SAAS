<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeofenceOverrideRequest extends Model
{
    protected $fillable = [
        'tenant_id', 'service_request_id', 'technician_user_id', 'client_event_uuid',
        'reason', 'photo_attachment_id', 'requested_at_device', 'gps_lat', 'gps_lng',
        'gps_accuracy_meters', 'site_distance_meters', 'status', 'decided_by_user_id',
        'decided_at', 'decision_note', 'valid_until', 'used_punch_id',
    ];

    protected $casts = [
        'requested_at_device' => 'datetime',
        'decided_at'          => 'datetime',
        'valid_until'         => 'datetime',
        'gps_lat'             => 'decimal:7',
        'gps_lng'             => 'decimal:7',
    ];

    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
    public function technician()     { return $this->belongsTo(User::class, 'technician_user_id'); }
    public function decidedBy()      { return $this->belongsTo(User::class, 'decided_by_user_id'); }
    public function photo()          { return $this->belongsTo(Attachment::class, 'photo_attachment_id'); }

    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function isPending(): bool { return $this->status === 'pending'; }

    public function isUsable(): bool
    {
        return $this->status === 'approved'
            && is_null($this->used_punch_id)
            && (! $this->valid_until || $this->valid_until->isFuture());
    }
}