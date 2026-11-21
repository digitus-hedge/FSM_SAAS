<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SrStatusHistory extends Model
{
    use \App\Models\Concerns\AppTimezoneDates;

    protected $table = 'sr_status_history';

    protected $fillable = [
        'tenant_id', 'service_request_id', 'from_status', 'to_status',
        'custom_stage_code', 'actor_type', 'actor_id', 'reason', 'meta_json',
    ];

    protected $casts = ['meta_json' => 'array'];

    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }

    public static function log(
        ServiceRequest $sr, ?string $from, string $to,
        string $actorType = 'user', ?int $actorId = null,
        ?string $reason = null, array $meta = []
    ): self {
        return static::create([
            'tenant_id' => $sr->tenant_id, 'service_request_id' => $sr->id,
            'from_status' => $from, 'to_status' => $to,
            'actor_type' => $actorType, 'actor_id' => $actorId,
            'reason' => $reason, 'meta_json' => $meta ?: null,
        ]);
    }

    /** Titles of the history events that do not change the status (meta_json.event). */
    public const EVENTS = [
        'quote_requested'  => 'Sent for quoting',
        'approval_partial' => 'Approved by one approver',
        'quote_created'    => 'Quote created',
        'quote_sent'       => 'Quote sent',
        'priority_changed' => 'Priority changed',
    ];

    /** A history row for an event that leaves the status as it is (from and to are both the current status). */
    public static function event(ServiceRequest $sr, string $event, ?string $reason = null, array $meta = [], string $actorType = 'user', ?int $actorId = null): self
    {
        return static::log($sr, $sr->status, $sr->status, $actorType, $actorId ?? auth()->id(), $reason, ['event' => $event] + $meta);
    }

    /** The line the history card shows: the event title, or "From → To". */
    public function title(): string
    {
        $event = $this->meta_json['event'] ?? null;
        if ($event && isset(self::EVENTS[$event]) && $this->from_status === $this->to_status) {
            return self::EVENTS[$event];
        }
        $label = fn (?string $s) => $s ? (ServiceRequest::STATUSES[$s] ?? ucwords(str_replace('_', ' ', $s))) : 'Created';

        return $this->from_status ? $label($this->from_status) . ' → ' . $label($this->to_status) : 'Created → ' . $label($this->to_status);
    }
}