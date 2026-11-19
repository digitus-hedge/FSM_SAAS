<?php

namespace App\Services\Permissions;

use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Who may approve, assign, reschedule, cancel or re-prioritise a service request (blueprint 4 and 7.1, signed matrix).
 *
 * The matrix gives Front Desk `record_level` on sr.assign and sr.cancel, which the blueprint spells out:
 *   - "Front desk assigns critical and emergency requests only"   -> assign and reschedule: critical or emergency requests only
 *   - a Front Desk user works with the requests they raised       -> cancel and change priority: requests they created only
 * Company Admin and Operations Manager hold `full` and may act on every request.
 * Approving needs `full` on sr.approve here: the only record_level holder is the Team Lead, who works from the field app.
 */
class ServiceRequestAccess
{
    private const URGENT = ['critical', 'emergency'];

    public static function mayApprove(?User $user): bool
    {
        return PermissionLevel::isFull($user, 'sr.approve');
    }

    /** Send for quote is the "needs a quote" branch of approving: the same decision, the same people (sr.approve full). */
    public static function maySendForQuote(?User $user): bool
    {
        return PermissionLevel::isFull($user, 'sr.approve');
    }

    /** The office "Submit for review" override (In Progress to Pending Review): the people who review the job next (sr.qc full). */
    public static function mayMarkReview(?User $user): bool
    {
        return PermissionLevel::isFull($user, 'sr.qc');
    }

    public static function mayAssign(?User $user, ServiceRequest $sr): bool
    {
        return PermissionLevel::isFull($user, 'sr.assign')
            || (PermissionLevel::canChange($user, 'sr.assign') && in_array($sr->priority, self::URGENT, true));
    }

    public static function mayCancel(?User $user, ServiceRequest $sr): bool
    {
        return PermissionLevel::isFull($user, 'sr.cancel')
            || (PermissionLevel::canChange($user, 'sr.cancel') && self::raisedBy($user, $sr));
    }

    public static function mayChangePriority(?User $user, ServiceRequest $sr): bool
    {
        return PermissionLevel::isFull($user, 'sr.assign')
            || (PermissionLevel::canChange($user, 'sr.assign') && self::raisedBy($user, $sr));
    }

    private static function raisedBy(?User $user, ServiceRequest $sr): bool
    {
        return $user && (int) $sr->created_by_user_id === (int) $user->id;
    }
}
