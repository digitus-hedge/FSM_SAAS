<?php

namespace App\Services\Trades;

use App\Models\User;
use App\Models\Vendor;
use App\Services\Audit\AuditLogger;
use App\Services\Forms\FormException;
use App\Services\Permissions\PermissionLevel;
use Illuminate\Http\Request;

/**
 * A vendor technician is a technician user who belongs to a vendor company (Multi-Trade-Vendors-Depot spec VND-02, D8, D10).
 * Only a holder of vendor.manage (full) sets the vendor or the "may log expenses" switch.
 */
class VendorTechnicians
{
    /** Called by the user form after the user was saved. */
    public function apply(User $user, Request $request, User $actor): void
    {
        $user->loadMissing('role');
        $mayManage = PermissionLevel::isFull($actor, 'vendor.manage');

        // a vendor technician can only be a technician (VENDOR_ROLE_LOCKED), also when the role is changed later
        if ($user->vendor_id && $user->role?->code !== 'field_technician') {
            throw new FormException('VENDOR_ROLE_LOCKED', 'A vendor technician can only have the technician role.');
        }

        if (! $mayManage || ! $request->has('vendor_id')) {
            return;
        }

        $vendorId = $request->filled('vendor_id') ? (int) $request->input('vendor_id') : null;
        if ($vendorId === null) {
            if ($user->vendor_id) {
                $user->forceFill(['vendor_id' => null, 'may_log_expenses' => true])->save();
                $this->audit($user, null);
            }

            return;
        }

        $vendor = Vendor::withoutGlobalScopes()->where('tenant_id', $user->tenant_id)->find($vendorId);
        if (! $vendor || $vendor->status !== 'active') {
            throw new FormException('VENDOR_SUSPENDED', 'This vendor is suspended.');
        }
        if ($user->role?->code !== 'field_technician') {
            throw new FormException('VENDOR_ROLE_LOCKED', 'A vendor technician can only have the technician role.');
        }

        $first = (int) $user->vendor_id !== $vendorId;
        $user->forceFill([
            'vendor_id' => $vendorId,
            // D10: a new vendor technician starts with the vendor's default; the switch is changed on the form after that
            'may_log_expenses' => $request->has('may_log_expenses')
                ? $request->boolean('may_log_expenses')
                : ($first ? (bool) $vendor->default_may_log_expenses : (bool) $user->may_log_expenses),
        ])->save();
        $this->audit($user, $vendorId);
    }

    private function audit(User $user, ?int $vendorId): void
    {
        app(AuditLogger::class)->log('user.vendor_changed', [
            'tenant_id' => $user->tenant_id, 'item_type' => User::class, 'item_id' => $user->id,
            'new' => ['vendor_id' => $vendorId, 'may_log_expenses' => $user->may_log_expenses],
        ]);
    }
}
