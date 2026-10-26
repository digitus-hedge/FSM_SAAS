<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SrEvent extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sr_events';

    protected $fillable = [
        'tenant_id',
        'service_request_id',
        'user_id',
        'type',          // assigned | accepted | punch_in | hold | resume | punch_out
        'label',         // e.g. "Bad weather" for a hold event, or the technician name for assigned
        'note',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /* ===================== RELATIONS ===================== */

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /* ===================== HELPERS ===================== */

    /**
     * Render this event as a one-line history row (matches the mockup).
     *   "Resumed, 09:36"
     *   "On hold (Bad weather), 09:35"
     *   "Punched in by Anil Kumar, 09:23"
     *   "Accepted by Anil Kumar, 08:48"
     *   "Assigned to Anil Kumar by Rahul Sharma, 08:40"
     */
    public function toHistoryLine(): string
    {
        $time  = optional($this->occurred_at)->format('H:i') ?? '';
        $who   = $this->user?->name ?? '—';
        $label = $this->label;

        return match ($this->type) {
            'resume'    => "Resumed, {$time}",
            'hold'      => "On hold ({$label}), {$time}",
            'punch_in'  => "Punched in by {$who}, {$time}",
            'punch_out' => "Punched out by {$who}, {$time}",
            'accepted'  => "Accepted by {$who}, {$time}",
            'assigned'  => "Assigned to " . ($label ?: $who) . " by {$who}, {$time}",
            default     => ucfirst($this->type) . ", {$time}",
        };
    }

    /* ===================== STATIC WRITERS ===================== */

    /**
     * Convenience factory used by JobsController@hold / @resume / etc.
     */
    public static function log(
        int    $serviceRequestId,
        string $type,
        ?string $label = null,
        ?string $note  = null,
        ?int   $userId = null,
        ?int   $tenantId = null
    ): self {
        return self::create([
            'tenant_id'          => $tenantId ?? auth()->user()?->tenant_id,
            'service_request_id' => $serviceRequestId,
            'user_id'            => $userId ?? auth()->id(),
            'type'               => $type,
            'label'              => $label,
            'note'               => $note,
            'occurred_at'        => now(),
        ]);
    }
}