<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    public const KINDS = [
        'sr_attachment' => 'SR attachment',
        'before_photo'  => 'Before photo',
        'after_photo'   => 'After photo',
        'signature'     => 'Signature',
        'receipt'       => 'Receipt',
        'document'      => 'Document',
        'logo'          => 'Logo',
        'asset_photo'   => 'Asset photo',
        'report'        => 'Report',
        'quote_pdf'     => 'Quote PDF',
        'invoice_pdf'   => 'Invoice PDF',
    ];

    protected $fillable = [
        'tenant_id', 'attachable_type', 'attachable_id', 'kind',
        'storage_path', 'original_name', 'mime_type', 'size_bytes', 'sha256',
        'captured_at', 'gps_lat', 'gps_lng',
        'uploaded_by_user_id', 'client_event_uuid',
    ];

    protected $casts = [
        'size_bytes'  => 'integer',
        'captured_at' => 'datetime',
        'gps_lat'     => 'decimal:7',
        'gps_lng'     => 'decimal:7',
    ];

    /* ---------- Relationships ---------- */

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /* ---------- Accessors ---------- */

   public function getUrlAttribute(): string
{
    // Removed attachment: no file URL available.
    if (empty($this->storage_path)) {
        return '';
    }

    if ($this->attachable_type === \App\Models\ServiceRequest::class) {
        return route('service-requests.file', [
            'service_request' => $this->attachable_id,
            'filename' => basename($this->storage_path),
        ]);
    }

    return \Storage::disk('public')->url($this->storage_path);
}

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function getExtAttribute(): string
    {
        return strtolower(pathinfo($this->original_name ?: $this->storage_path, PATHINFO_EXTENSION));
    }

    public function getHumanSizeAttribute(): string
    {
        $kb = $this->size_bytes / 1024;
        return $kb < 1024
            ? number_format($kb, 1) . ' KB'
            : number_format($kb / 1024, 1) . ' MB';
    }

    /**
     * Delete the DB row AND the file on disk.
     */
    public function deleteWithFile(): bool
    {
        if (Storage::disk('public')->exists($this->storage_path)) {
            Storage::disk('public')->delete($this->storage_path);
        }
        return $this->delete();
    }
}