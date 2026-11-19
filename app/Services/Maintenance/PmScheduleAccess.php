<?php

namespace App\Services\Maintenance;

use App\Models\PmSchedule;
use App\Models\User;
use App\Services\Permissions\PermissionLevel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who sees which maintenance schedules (Preventive-Maintenance spec D3). pm.view at record_level means "their own team's":
 * a Team Lead sees the schedules whose default technician is on their team (or is themselves). Narrowed here, in one place.
 */
class PmScheduleAccess
{
    public static function mayManage(?User $user): bool
    {
        return PermissionLevel::isFull($user, 'pm.manage');
    }

    public static function seesAll(?User $user): bool
    {
        return PermissionLevel::isFull($user, 'pm.view');
    }

    /** @param Builder<PmSchedule> $query */
    public static function narrow(Builder $query, ?User $user): Builder
    {
        if (self::seesAll($user)) {
            return $query;
        }
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('default_technician_user_id', User::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where(fn ($q) => $q->where('team_lead_id', $user->id)->orWhere('id', $user->id))
            ->select('id'));
    }

    public static function mayView(?User $user, PmSchedule $schedule): bool
    {
        return self::narrow(PmSchedule::withoutGlobalScopes()->whereKey($schedule->id), $user)->exists();
    }
}
