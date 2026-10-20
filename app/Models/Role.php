<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $fillable = ['tenant_id', 'code', 'name', 'is_standard', 'based_on_code'];

    protected $casts = [
        'is_standard' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions()
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}