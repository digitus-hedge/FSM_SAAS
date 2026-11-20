<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A support session of a platform user inside a company (blueprint 7.9a). Not scoped by company: it is read by the platform console and by the session itself. */
class ImpersonationSession extends Model
{
    use \App\Models\Concerns\AppTimezoneDates;

    protected $fillable = [
        'platform_user_id', 'tenant_id', 'target_user_id', 'reason', 'started_at', 'expires_at', 'ended_at', 'ip_address', 'end_reason', 'actions_count',
    ];

    protected $casts = ['started_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];

    public function platformUser() { return $this->belongsTo(PlatformUser::class); }

    public function tenant() { return $this->belongsTo(Tenant::class); }

    public function targetUser() { return $this->belongsTo(User::class, 'target_user_id'); }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }

    public function minutesLeft(): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($this->expires_at, false) / 60));
    }
}
