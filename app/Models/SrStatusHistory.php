<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SrStatusHistory extends Model
{
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
    
}