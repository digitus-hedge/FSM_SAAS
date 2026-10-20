<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'activated_at'  => 'datetime',
        'suspended_at'  => 'datetime',
        'trial_ends_on' => 'date',   // SCH-05 / D10
    ];

    /** Users belong to one tenant (AT-22). Was a BelongsToMany via tenant_memberships. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial'], true);
    }

    /* ────────────────────────────────────────
       Current-tenant context (set by TenantResolver middleware).
       Any tenant-scoped model reads from here.
    ─────────────────────────────────────── */
    protected static ?Tenant $current = null;

    public static function current(): ?Tenant { return static::$current; }
    public static function setCurrent(?Tenant $tenant): void { static::$current = $tenant; }
}