<?php

namespace App\Services\Reports;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Permissions\PermissionLevel;
use Illuminate\Support\Collection;

/**
 * Who sees which report (Reports-Summaries-Preferences spec D5). report.view at record_level means "their own team" for a Team Lead,
 * and view_own for a client means "their own records". Narrowed here, in one place.
 */
class ReportAccess
{
    public const REPORTS = ['sla', 'scorecard', 'certificate', 'contracts'];

    public static function mayView(?User $user): bool
    {
        return PermissionLevel::canView($user, 'report.view') || in_array($user?->role?->code, ['client_user', 'client_admin', 'client_viewer'], true);
    }

    public static function mayExport(?User $user): bool
    {
        return PermissionLevel::isFull($user, 'report.export');
    }

    /** Finance sees the money reports only; clients only the certificate and the contract report; a Team Lead no money. */
    public static function mayOpen(?User $user, string $report): bool
    {
        $code = $user?->role?->code;

        return match ($code) {
            'finance'                                      => in_array($report, ['sla', 'contracts'], true),
            'client_user', 'client_admin', 'client_viewer' => in_array($report, ['certificate', 'contracts'], true),
            'team_lead'                                    => in_array($report, ['sla', 'scorecard'], true),
            default                                        => PermissionLevel::canView($user, 'report.view') && in_array($report, self::REPORTS, true),
        };
    }

    /** The technician ids a user may see in a scorecard, null for everyone. */
    public static function technicianIds(?User $user): ?Collection
    {
        if (! $user || PermissionLevel::isFull($user, 'report.view') || $user->role?->code !== 'team_lead') {
            return null;
        }

        return User::withoutGlobalScopes()->where('tenant_id', $user->tenant_id)->where(fn ($q) => $q->where('team_lead_id', $user->id)->orWhere('id', $user->id))->pluck('id');
    }

    /** A client sees a certificate only of their own client's request. */
    public static function mayCertificate(?User $user, ServiceRequest $sr): bool
    {
        if (! $user || (int) $user->tenant_id !== (int) $sr->tenant_id) {
            return false;
        }
        if (in_array($user->role?->code, ['client_user', 'client_admin', 'client_viewer'], true)) {
            return (int) $user->client_id === (int) $sr->client_id;
        }

        return PermissionLevel::canView($user, 'report.view');
    }
}
