<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['key', 'group', 'description', 'feature_required'];

    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class);
    }
}