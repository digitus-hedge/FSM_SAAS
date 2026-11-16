<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTimeOff extends Model
{
    protected $table = 'user_time_off';

    protected $fillable = ['tenant_id', 'user_id', 'starts_at', 'ends_at', 'type', 'note', 'created_by_user_id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public const TYPES = ['leave' => 'Leave', 'training' => 'Training', 'other' => 'Other'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
