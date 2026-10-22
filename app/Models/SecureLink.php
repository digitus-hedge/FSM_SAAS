<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecureLink extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'purpose',
        'token_hash', 'expires_at', 'used_at', 'request_ip',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLive(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}