<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use  HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'web';

    protected $fillable = [
        'name', 'email', 'password',
        'is_platform_admin',
        'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'password'             => 'hashed',
        'is_platform_admin'    => 'boolean',
        'must_change_password' => 'boolean',
    ];

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function hasAccessToTenant(Tenant $tenant): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        return $this->tenants()
            ->where('tenants.id', $tenant->id)
            ->wherePivot('status', 'active')
            ->exists();
    }
}