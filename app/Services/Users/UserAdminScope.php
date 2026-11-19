<?php

namespace App\Services\Users;

use App\Models\Client;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Permissions\PermissionLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who may add, edit, turn off or reset which users (blueprint section 4):
 *
 *   "Only the Company Admin adds staff and technicians. A Client Admin adds users for their own company, and front desk adds
 *    client users when a client has no Client Admin. Operations managers do not add users."
 *
 * The route gate (`level:user.manage`) only says a person has some right on user.manage. This class narrows it to their own
 * records, and is the one place that decides it:
 *
 *   admin         the Company Admin (role code company_admin): every user and every role, except their own role
 *   desk          any other staff user with user.manage (the Front Desk): client users only, and only for a client that has
 *                 no active Client Admin
 *   client_admin  a Client Admin (role code client_admin): client users of their own client company only
 *   none          everybody else, including client users and viewers: nothing
 *
 * Nobody but a Company Admin can create or edit a staff user, or give anyone the Company Admin role. Nobody can change
 * their own role.
 */
class UserAdminScope
{
    public const CLIENT_ROLE_CODES = ['client_user', 'client_admin', 'client_viewer'];

    private function __construct(private User $actor)
    {
    }

    public static function for(User $actor): self
    {
        return new self($actor);
    }

    public function mode(): string
    {
        $code = $this->actor->role?->code;

        if ($code === 'company_admin') {
            return 'admin';
        }
        if ($code === 'client_admin' && $this->actor->client_id) {
            return 'client_admin';
        }
        if (in_array($code, self::CLIENT_ROLE_CODES, true) || in_array($code, ['team_lead', 'field_technician'], true)) {
            return 'none';
        }

        return PermissionLevel::canChange($this->actor, 'user.manage') ? 'desk' : 'none';
    }

    public function isAdmin(): bool
    {
        return $this->mode() === 'admin';
    }

    public function allowed(): bool
    {
        return $this->mode() !== 'none';
    }

    /** Roles this person may give. Staff roles, and the Company Admin role above all, are the Company Admin's alone. */
    public function roles(Tenant $tenant): Collection
    {
        $roles = Role::where('tenant_id', $tenant->id)->orderBy('name')->get();

        return $this->isAdmin() ? $roles : $roles->filter(fn ($r) => $this->isClientRole($r))->values();
    }

    public function canAssignRole(?Role $role): bool
    {
        if (! $role || (int) $role->tenant_id !== (int) $this->actor->tenant_id) {
            return false;
        }

        return match ($this->mode()) {
            'admin'                    => true,
            'desk', 'client_admin'     => $this->isClientRole($role),
            default                    => false,
        };
    }

    /** May this person open, change, turn off or reset this user? */
    public function canManage(User $target): bool
    {
        if ((int) $target->tenant_id !== (int) $this->actor->tenant_id) {
            return false;
        }

        $clientTarget = in_array($target->role?->code, self::CLIENT_ROLE_CODES, true) && $target->client_id;

        return match ($this->mode()) {
            'admin'        => true,
            'desk'         => $clientTarget && ! $this->clientHasAdmin((int) $target->client_id),
            'client_admin' => $clientTarget && (int) $target->client_id === (int) $this->actor->client_id,
            default        => false,
        };
    }

    /** May this person add a user to this client company? */
    public function canAddForClient(?int $clientId): bool
    {
        if (! $clientId) {
            return false;
        }

        return match ($this->mode()) {
            'admin'        => true,
            'desk'         => ! $this->clientHasAdmin($clientId),
            'client_admin' => $clientId === (int) $this->actor->client_id,
            default        => false,
        };
    }

    /** The users the list shows. */
    public function scope(Builder $users): Builder
    {
        return match ($this->mode()) {
            'admin' => $users,
            'desk'  => $users->whereHas('role', fn ($r) => $r->whereIn('code', self::CLIENT_ROLE_CODES))
                             ->whereNotNull('client_id')
                             ->whereNotIn('client_id', $this->clientsWithAdmin()),
            'client_admin' => $users->whereHas('role', fn ($r) => $r->whereIn('code', self::CLIENT_ROLE_CODES))
                                    ->where('client_id', $this->actor->client_id),
            default => $users->whereRaw('1 = 0'),
        };
    }

    /** The client companies this person may add users to. */
    public function clients(Tenant $tenant): Collection
    {
        $q = Client::where('tenant_id', $tenant->id)->orderBy('name');

        return match ($this->mode()) {
            'admin'        => $q->get(),
            'desk'         => $q->whereNotIn('id', $this->clientsWithAdmin())->get(),
            'client_admin' => $q->where('id', $this->actor->client_id)->get(),
            default        => collect(),
        };
    }

    private function isClientRole(Role $role): bool
    {
        return in_array($role->code, self::CLIENT_ROLE_CODES, true) && $role->code !== 'company_admin';
    }

    private function clientHasAdmin(int $clientId): bool
    {
        return in_array($clientId, $this->clientsWithAdmin(), true);
    }

    /** Client companies that already have an active Client Admin. */
    private function clientsWithAdmin(): array
    {
        return DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.tenant_id', $this->actor->tenant_id)->where('roles.code', 'client_admin')
            ->whereNull('users.deactivated_at')->whereNotNull('users.client_id')
            ->pluck('users.client_id')->map(fn ($v) => (int) $v)->all();
    }
}
