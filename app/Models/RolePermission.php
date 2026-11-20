<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use \App\Models\Concerns\AppTimezoneDates;

    protected $fillable = ['tenant_id', 'role_id', 'permission_id', 'level'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}