<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    const UPDATED_AT = null;   // append-only — never updated

    protected $fillable = [
        'tenant_id', 'actor_type', 'actor_id', 'actor_name',
        'action', 'item_type', 'item_id',
        'old_values', 'new_values',
        'ip_address', 'user_agent',
        'prev_hash', 'hash',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];
}