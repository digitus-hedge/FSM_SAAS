<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id', 'role_id',
        'name', 'email', 'phone', 'employee_code',
        'password',
        'team_lead_id', 'client_id',
        'client_access', 'site_scope_json',
        'must_change_password',
        'failed_login_count', 'locked_until',
        'two_factor_secret', 'two_factor_confirmed_at',
        'aiywah_account_id',
        'last_sign_in_at', 'deactivated_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected $casts = [
        'email_verified_at'       => 'datetime',
        'password'                => 'hashed',
        'must_change_password'    => 'boolean',
        'two_factor_secret'       => 'encrypted',
        'two_factor_confirmed_at' => 'datetime',
        'locked_until'            => 'datetime',
        'last_sign_in_at'         => 'datetime',
        'deactivated_at'          => 'datetime',
        'site_scope_json'         => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(self::class, 'team_lead_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(UserSkill::class);
    }

    /** True if this user is currently signed-in-eligible. */
    public function isActive(): bool
    {
        return $this->deactivated_at === null
            && ($this->locked_until === null || $this->locked_until->isPast())
            && $this->tenant?->isActive();
    }
    public function hasAccessToTenant(\App\Models\Tenant $tenant): bool
    {
        return $this->tenant_id === $tenant->id
            && $this->deactivated_at === null;
    }
    /**
     * Does this tenant user hold the given permission key at any level above 'none'?
     * Reads directly from role_permissions. Overrides come later (ACC-11 full engine).
     */
    public function canDo(string $key): bool
    {
        if (! $this->role_id || ! $this->tenant_id) {
            return false;
        }

        return \App\Models\RolePermission::where('tenant_id', $this->tenant_id)
            ->where('role_id', $this->role_id)
            ->whereHas('permission', fn ($q) => $q->where('key', $key))
            ->where('level', '!=', 'none')
            ->exists();
    }
}