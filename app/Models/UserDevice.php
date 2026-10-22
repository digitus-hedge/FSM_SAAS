<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $fillable = [
        'tenant_id', 'user_type', 'user_id',
        'label', 'ip', 'user_agent', 'session_id',
        'last_used_at', 'trusted_until', 'revoked_at',
    ];

    protected $casts = [
        'last_used_at'  => 'datetime',
        'trusted_until' => 'datetime',
        'revoked_at'    => 'datetime',
    ];

    public function isLive(): bool
    {
        return $this->revoked_at === null && $this->trusted_until->isFuture();
    }
}