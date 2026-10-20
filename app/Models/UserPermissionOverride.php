<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPermissionOverride extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'permission_id', 'level', 'reason', 'ends_at'];

    protected $casts = [
        'ends_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}