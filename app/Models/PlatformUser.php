<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class PlatformUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'platform_role',
        'failed_login_count', 'locked_until',
        'two_factor_secret', 'two_factor_confirmed_at',
        'last_sign_in_at', 'deactivated_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected $casts = [
        'password'                => 'hashed',
        'two_factor_secret'       => 'encrypted',
        'two_factor_confirmed_at' => 'datetime',
        'locked_until'            => 'datetime',
        'last_sign_in_at'         => 'datetime',
        'deactivated_at'          => 'datetime',
    ];

    /**
     * D19: platform roles.
     *  - owner   → everything
     *  - support → view, resend invitations, reset two-factor
     *  - finance → read only (plans + companies)
     */
    public function canDo(string $ability): bool
{
    // Platform admins only hold platform-scoped abilities.
    // Tenant keys (client.manage, site.manage, etc.) are never true here —
    // platform admins don't belong to a tenant, so tenant-scoped work doesn't apply.
    $platformAbilities = match ($this->platform_role) {
        'owner'   => ['tenants.view', 'tenants.create', 'tenants.suspend',
                      'tenants.reactivate', 'tenants.resend_invitation',
                      'tenants.reset_two_factor', 'plans.view', 'plans.manage'],
        'support' => ['tenants.view', 'tenants.resend_invitation',
                      'tenants.reset_two_factor', 'plans.view'],
        'finance' => ['tenants.view', 'plans.view'],
        default   => [],
    };

    return in_array($ability, $platformAbilities, true);
}
}