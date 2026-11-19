<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** One asset of a batch request, with its inspection status and checklist (BAT-01). */
class ServiceRequestAsset extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    public const STATUSES = ['pending', 'serviced_ok', 'repaired', 'needs_parts', 'failed_unsafe', 'skipped'];

    protected $fillable = [
        'tenant_id', 'service_request_id', 'asset_id', 'inspection_status', 'qr_scanned_verify', 'scanned_at',
        'form_template_version_id', 'asset_checklist_payload', 'checklist_result', 'recorded_meter_reading',
        'before_photo_paths', 'after_photo_paths', 'technician_remarks', 'completed_at',
    ];

    protected $casts = [
        'qr_scanned_verify'       => 'boolean',
        'scanned_at'              => 'datetime',
        'completed_at'            => 'datetime',
        'asset_checklist_payload' => 'array',
        'before_photo_paths'      => 'array',
        'after_photo_paths'       => 'array',
    ];

    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
    public function asset()          { return $this->belongsTo(Asset::class)->withTrashed(); }
    public function version()        { return $this->belongsTo(FormTemplateVersion::class, 'form_template_version_id'); }
}
