<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /** Members = users with a membership in this tenant. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /* ────────────────────────────────────────
       Current-tenant context (set by TenantResolver middleware)
       Any BelongsToTenant model reads from here.
    ─────────────────────────────────────── */
    protected static ?Tenant $current = null;

    public static function current(): ?Tenant
    {
        return static::$current;
    }

    public static function setCurrent(?Tenant $tenant): void
    {
        static::$current = $tenant;
    }
}