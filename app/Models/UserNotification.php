<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** One in-app notification for one user (C1 foundation). */
class UserNotification extends Model
{
    use BelongsToTenant;

    protected $table = 'user_notifications';

    protected $fillable = ['tenant_id', 'user_id', 'event_key', 'title', 'body', 'url', 'data', 'emailed_at', 'read_at'];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime', 'emailed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    public function scopeUnread($q) { return $q->whereNull('read_at'); }

    public function getIsReadAttribute(): bool { return $this->read_at !== null; }
}
