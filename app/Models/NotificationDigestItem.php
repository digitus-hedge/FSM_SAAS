<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationDigestItem extends Model
{
    use BelongsToTenant;

    public $timestamps = false;
    protected $fillable = ['tenant_id', 'user_id', 'event_key', 'channel', 'frequency', 'title', 'body', 'link', 'created_at', 'sent_at'];
    protected $casts = ['created_at' => 'datetime', 'sent_at' => 'datetime'];
}
