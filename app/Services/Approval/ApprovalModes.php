<?php

namespace App\Services\Approval;

use App\Models\ApprovalRule;
use App\Models\Contract;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Contracts\ContractCoverage;
use App\Services\Forms\FormException;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Approval modes (FRM-07, FRM-08, D9, D19 to D21). The mode is service_categories.approval_mode. A plan that does not
 * include it runs the category as manual, without changing the stored value.
 */
class ApprovalModes
{
    public const AUTO = ['auto_if_in_contract', 'always_auto'];

    /** Is this mode part of the company's plan? (D9) */
    public function allowed(?Tenant $tenant, string $mode): bool
    {
        return match ($mode) {
            'manual'              => true,
            'auto_if_in_contract', 'always_auto' => (bool) $tenant?->hasFeature('approval_auto'),
            'finance_and_manager' => (bool) $tenant?->hasFeature('approval_finance'),
            default               => false,
        };
    }

    public function effective(?ServiceCategory $category, ?Tenant $tenant): string
    {
        $mode = $category?->approval_mode ?: 'manual';

        return $this->allowed($tenant, $mode) ? $mode : 'manual';
    }

    /** The mode saved on a category, refused when the plan lacks it (APPROVAL_MODE_NOT_IN_PLAN). */
    public function assertAllowed(?Tenant $tenant, string $mode): void
    {
        if (! $this->allowed($tenant, $mode)) {
            throw new FormException('APPROVAL_MODE_NOT_IN_PLAN', 'Not in your plan.', 403);
        }
    }

    /** Saves the mode, the limit and the second approver of a category (the rules page, FRM-08). */
    public function saveRule(ServiceCategory $category, Tenant $tenant, string $mode, ?string $limit, ?int $secondApproverId): void
    {
        if ($mode !== $category->approval_mode) {
            $this->assertAllowed($tenant, $mode);
        }
        $old = $category->approval_mode;
        $category->forceFill(['approval_mode' => $mode])->save();
        ApprovalRule::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'service_category_id' => $category->id],
            ['mode' => $mode, 'amount_limit_base' => ($limit !== null && $limit !== '') ? $limit : null, 'second_approver_user_id' => $secondApproverId]
        );
        if ($old !== $mode) {
            app(AuditLogger::class)->log('approval_mode.changed', [
                'tenant_id' => $tenant->id, 'item_type' => ServiceCategory::class, 'item_id' => $category->id,
                'old' => ['approval_mode' => $old], 'new' => ['approval_mode' => $mode],
            ]);
        }
    }

    /** Called right after a request is created. Automatic modes approve at once, "Finance and manager" opens its two approvals. */
    public function onCreated(ServiceRequest $sr, ServiceCategory $category): void
    {
        $tenant = Tenant::find($sr->tenant_id);
        $mode   = $this->effective($category, $tenant);
        $notify = app(NotificationService::class);

        if ($mode === 'finance_and_manager') {
            $sr->forceFill(['approval_state' => 'awaiting_both'])->save();
            $notify->notify('sr.approval_needed', $this->approvers($sr), [
                'sr_number' => $sr->sr_number, 'client' => $sr->client?->name, 'waiting_for' => 'The Operations Manager and Finance',
            ], route('service-requests.show', $sr, false));

            return;
        }
        if (! in_array($mode, self::AUTO, true)) {
            return;
        }

        $sr->refresh();
        if ($sr->contract_choice_pending) {
            return;   // several contracts match: a person chooses first
        }

        $cov    = app(ContractCoverage::class);
        $result = $cov->resolve($sr);
        if ($result['needs_choice']) {
            return;   // a cap was reached: a manager decides
        }
        if ($mode === 'auto_if_in_contract' && ! ($result['type'] === 'in_contract' && $result['contract'])) {
            return;   // not covered: stays Pending for manual approval (D19)
        }

        $done = DB::transaction(function () use ($sr, $result, $cov, $mode) {
            $contract = null;
            if ($result['type'] === 'in_contract') {
                $contract = Contract::lockForUpdate()->find($result['contract']->id);
                if (! $contract || $contract->capReached()) {
                    return false;
                }
                $cov->countCall($sr, $contract, false);
            }
            app(RequestApprover::class)->complete($sr, $result['type'], $contract, null, false, null, $mode);

            return true;
        });

        if ($done) {
            $notify->notify('sr.auto_approved', $this->approvers($sr), [
                'sr_number' => $sr->sr_number, 'client' => $sr->client?->name, 'mode' => $mode,
            ], route('service-requests.show', $sr, false));
        }
    }

    /**
     * One side of "Finance and manager" (D21). $side is 'ops' or 'finance'. The same person cannot give both
     * (APPROVAL_SAME_PERSON). Returns true when the second approval completed the request.
     */
    public function approveSide(ServiceRequest $sr, string $side, User $user, string $coverage, ?string $note): bool
    {
        $other = $side === 'ops' ? 'approved_by_finance_user_id' : 'approved_by_ops_user_id';
        if ($sr->{$other} && (int) $sr->{$other} === (int) $user->id) {
            throw new FormException('APPROVAL_SAME_PERSON', 'A second person must give the other approval.');
        }

        if ($side === 'finance') {
            $assigned = ApprovalRule::withoutGlobalScopes()->where('tenant_id', $sr->tenant_id)->where('service_category_id', $sr->service_category_id)->value('second_approver_user_id');
            if ($assigned && (int) $assigned !== (int) $user->id) {
                throw new FormException('APPROVAL_NOT_ASSIGNED', 'Another person is named for the Finance approval of this category.', 403);
            }
        }

        $col = $side === 'ops' ? 'approved_by_ops_user_id' : 'approved_by_finance_user_id';
        $sr->forceFill([$col => $user->id])->save();

        // The coverage chosen by the Operations Manager waits on the request until the second approval.
        if ($side === 'ops') {
            $sr->forceFill(['coverage_type' => $coverage, 'coverage_override_reason' => $note])->save();
        }

        app(AuditLogger::class)->log('request.approval_given', [
            'tenant_id' => $sr->tenant_id, 'item_type' => ServiceRequest::class, 'item_id' => $sr->id,
            'new' => ['sr_number' => $sr->sr_number, 'side' => $side, 'mode' => 'finance_and_manager'],
        ]);

        $both = $sr->approved_by_ops_user_id && $sr->approved_by_finance_user_id;
        $sr->forceFill(['approval_state' => $both ? 'done' : ($side === 'ops' ? 'awaiting_finance' : 'awaiting_ops')])->save();

        if (! $both) {
            app(NotificationService::class)->notify('sr.approval_needed', $this->approvers($sr), [
                'sr_number' => $sr->sr_number, 'client' => $sr->client?->name,
                'waiting_for' => $side === 'ops' ? 'Finance' : 'The Operations Manager',
            ], route('service-requests.show', $sr, false));
        }

        return (bool) $both;
    }

    /** A rejection by either approver cancels the approval and goes back to the requester (D21). */
    public function reject(ServiceRequest $sr, User $user, string $reason): void
    {
        $sr->forceFill(['approval_state' => null, 'approved_by_ops_user_id' => null, 'approved_by_finance_user_id' => null])->save();
        $requester = $sr->created_by_user_id ? User::withoutGlobalScopes()->find($sr->created_by_user_id) : null;
        app(NotificationService::class)->notify('sr.approval_rejected', [$requester], ['sr_number' => $sr->sr_number, 'reason' => $reason], route('service-requests.show', $sr, false));
    }

    /** People who may give an approval: sr.approve or expense.approve at full. */
    private function approvers(ServiceRequest $sr): \Illuminate\Support\Collection
    {
        $svc = app(NotificationService::class);

        return $svc->usersWhoCan('sr.approve', $sr->tenant_id)->merge($svc->usersWhoCan('expense.approve', $sr->tenant_id))->unique('id')->values();
    }
}
