<?php

namespace App\Services\Permissions;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermissionOverride;

/**
 * User::canDo() is true for ANY level above "none", so a view_only or view_own holder passes it, and so does a client user
 * with view_own on a permission meant for staff. This class says exactly what a level allows (blueprint section 4).
 * It follows the same rules as canDo(): a personal override wins.
 *
 *   canChange(): full, grantable or record_level. For every create, edit, delete, send, pause, rate or settings change.
 *   canView():   the same, and view_only. To open a page or a record read-only.
 *   record_level means "records matching the user's relationship" (blueprint section 4): the Front Desk adds client users and
 *   assigns urgent jobs, a Team Lead assigns within the team. The controller narrows what they may touch; this class only says
 *   the user has the right at all. view_own and edit_own are left out: they belong to client and field users, not to an office screen.
 */
class PermissionLevel
{
    private const CHANGE_LEVELS = ['full', 'grantable', 'record_level'];
    private const VIEW_LEVELS   = ['full', 'grantable', 'record_level', 'view_only'];

    public static function canChange(?User $user, string ...$keys): bool
    {
        return self::any($user, self::CHANGE_LEVELS, $keys);
    }

    public static function canView(?User $user, string ...$keys): bool
    {
        return self::any($user, self::VIEW_LEVELS, $keys);
    }

    /** True only for full or grantable: every record, not just the user's own (record_level). */
    public static function isFull(?User $user, string ...$keys): bool
    {
        return self::any($user, ['full', 'grantable'], $keys);
    }

    private static function any(?User $user, array $levels, array $keys): bool
    {
        if (! $user || ! $user->role_id || ! $user->tenant_id) {
            return false;
        }

        foreach ($keys as $key) {
            $permission = Permission::where('key', $key)->first();
            if (! $permission) {
                continue;
            }

            $override = UserPermissionOverride::where('user_id', $user->id)->where('permission_id', $permission->id)
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->first();

            $level = $override
                ? $override->level
                : RolePermission::where('tenant_id', $user->tenant_id)->where('role_id', $user->role_id)->where('permission_id', $permission->id)->value('level');

            if (in_array($level, $levels, true)) {
                return true;
            }
        }

        return false;
    }
}
