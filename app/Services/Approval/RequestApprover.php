<?php

namespace App\Services\Approval;

use App\Models\Contract;
use App\Models\ServiceRequest;
use App\Models\SrStatusHistory;
use App\Services\Audit\AuditLogger;

/**
 * Writes an approval: status, coverage, SLA times and history. Used by the manual approval, the second approval of
 * "Finance and manager" and the automatic modes (Forms-Approvals-Batch spec, D19 to D21). The contract (if any) stays
 * on the request for reporting.
 */
class RequestApprover
{
    /**
     * @param int|null    $userId   the approver; null for an automatic approval
     * @param string|null $autoMode the automatic mode that fired, null for a person's approval
     */
    public function complete(ServiceRequest $sr, string $coverage, ?Contract $contract, ?string $note, bool $override, ?int $userId, ?string $autoMode = null): void
    {
        $fromStatus = $sr->status;
        $started    = now();

        $sr->fill([
            'status'                       => 'approved',
            'approved_by_user_id'          => $userId,
            'coverage_type'                => $coverage,
            'billing_status'               => 'not_applicable',
            'coverage_override_reason'     => $note,
            'coverage_override_by_user_id' => $override ? $userId : null,
            'contract_id'                  => $contract?->id,
            'contract_choice_pending'      => false,
            'contract_choice_reason'       => null,
            'sla_clock_started_at'         => $started,
            'approval_state'               => $sr->approval_state ? 'done' : null,
            'auto_approved_mode'           => $autoMode,
        ]);

        // D10: a contract's response and resolution times replace the policy's.
        $sr->unsetRelation('contract');
        [$resp, $reso] = $sr->slaMinutes();
        $sr->fill([
            'sla_response_due_at'   => $resp !== null ? $started->copy()->addMinutes($resp) : null,
            'sla_resolution_due_at' => $reso !== null ? $started->copy()->addMinutes($reso) : null,
        ])->save();

        $audit = app(AuditLogger::class);
        if ($autoMode) {
            SrStatusHistory::log($sr, $fromStatus, 'approved', 'system', null, "Approved automatically ({$autoMode}).", ['coverage' => $coverage, 'mode' => $autoMode]);
            $audit->log('request.auto_approved', [
                'tenant_id' => $sr->tenant_id, 'item_type' => ServiceRequest::class, 'item_id' => $sr->id,
                'new' => ['sr_number' => $sr->sr_number, 'mode' => $autoMode, 'coverage' => $coverage],
            ]);

            return;
        }

        SrStatusHistory::log($sr, $fromStatus, 'approved', 'user', $userId, $note, ['coverage' => $coverage]);
        if ($override) {
            $audit->log('request.coverage_overridden', [
                'item_type' => ServiceRequest::class, 'item_id' => $sr->id,
                'new' => ['sr_number' => $sr->sr_number, 'coverage' => $coverage, 'reason' => $note],
            ]);
        }
    }
}
