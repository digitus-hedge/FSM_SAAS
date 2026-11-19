<?php

namespace App\Services\Maintenance;

use App\Models\Asset;
use App\Models\PmSchedule;
use App\Models\PmScheduleRun;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\SrStatusHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTimeOff;
use App\Services\Approval\ApprovalModes;
use App\Services\Audit\AuditLogger;
use App\Services\Contracts\ContractCoverage;
use App\Services\Forms\BatchService;
use App\Services\Forms\Checklists;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The daily maintenance job (Preventive-Maintenance spec PMC-02 to PMC-05, PMC-07, PMC-08, D5 to D13, D18, D20).
 * Safe to run twice on the same day: the unique key on (schedule, due date) stops a second request.
 * No company is signed in when it runs, so it sets the company for the steps that need one and always puts it back.
 */
class PmGenerator
{
    public static int $maxPerRun = 500;            // D20 (a property so a test can lower it)
    private const NOT_OPEN = ['completed', 'cancelled'];
    private const WORK_DONE = ['pending_review', 'awaiting_invoice', 'completed', 'cancelled'];

    /**
     * @return array{created:int, skipped_open:int, needs_contract:int, errors:int, ended:int, overdue:int, left_over:int}   created includes needs_contract
     */
    public function runForTenant(Tenant $tenant, Carbon $today): array
    {
        $out = ['created' => 0, 'skipped_open' => 0, 'needs_contract' => 0, 'errors' => 0, 'ended' => 0, 'overdue' => 0, 'left_over' => 0];
        $today = Carbon::parse($today->toDateString());   // the company's date, compared with the date columns
        $previous = Tenant::current();
        Tenant::setCurrent($tenant);

        try {
            $due = PmSchedule::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'active')
                ->whereRaw('DATE_SUB(next_due_date, INTERVAL lead_days DAY) <= ?', [$today->toDateString()])
                ->orderBy('next_due_date')->orderBy('id')->get();

            foreach ($due as $schedule) {
                if ($out['created'] >= self::$maxPerRun) {
                    $out['left_over']++;
                    continue;
                }
                try {
                    $r = $this->generate($tenant, $schedule->id, $today);
                } catch (\Throwable $e) {
                    Log::warning('Maintenance schedule failed', ['schedule' => $schedule->id, 'error' => $e->getMessage()]);
                    $r = 'error';
                }
                match ($r) {
                    'created'        => $out['created']++,
                    'needs_contract' => [$out['created']++, $out['needs_contract']++],
                    'skipped_open'   => $out['skipped_open']++,
                    'ended'          => $out['ended']++,
                    'error'          => $out['errors']++,
                    default          => null,   // nothing to do: another worker or an earlier run did it
                };
            }

            $out['overdue'] = $this->sendOverdue($tenant, $today);
        } finally {
            Tenant::setCurrent($previous);
        }

        return $out;
    }

    /** One schedule, one due date, in one transaction. Returns the key of the count to raise. */
    private function generate(Tenant $tenant, int $scheduleId, Carbon $today): string
    {
        $created = null;
        $outcome = DB::transaction(function () use ($tenant, $scheduleId, $today, &$created) {
            /** @var PmSchedule|null $s */
            $s = PmSchedule::withoutGlobalScopes()->lockForUpdate()->find($scheduleId);
            // another worker may have done it between the list and the lock
            if (! $s || $s->status !== 'active' || $s->next_due_date->copy()->subDays($s->lead_days)->gt($today)) {
                return 'skip';
            }

            $due = $s->next_due_date->copy();

            // an end date: nothing past it is created, and the schedule ends (PMC-019)
            if ($s->end_date && $due->gt($s->end_date)) {
                $s->forceFill(['status' => 'ended'])->save();
                $this->audit('pm_schedule.ended', $s, ['reason' => 'end date reached']);

                return 'ended';
            }

            $already = PmScheduleRun::withoutGlobalScopes()->where('pm_schedule_id', $s->id)->where('due_date', $due->toDateString())->exists();
            $result  = 'created';
            $note    = null;
            $sr      = null;

            if (! $already) {
                if ($s->skip_if_open && ServiceRequest::withoutGlobalScopes()->where('pm_schedule_id', $s->id)->whereNotIn('status', self::NOT_OPEN)->exists()) {
                    $result = 'skipped_open';
                } else {
                    $assets = $this->availableAssets($s);
                    if ($assets->isEmpty()) {
                        $result = 'error';
                        $note   = 'No asset of this schedule is available.';
                    } else {
                        // PMC-02: the catch-up note when dates were missed (D8)
                        $skipped = $due->lt($today) ? count(Frequency::between($s->first_due_date, $s->frequency, $s->interval_value, $due->copy()->addDay(), $today)) : 0;
                        $sr = $this->createRequest($tenant, $s, $due, $assets, $skipped);
                        $created = $sr;
                        $note = $skipped > 0 ? "Created late. {$skipped} earlier dates were missed." : null;
                        if ($sr->contract_choice_pending) {
                            $result = 'needs_contract';
                        }
                    }
                }
                PmScheduleRun::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id, 'pm_schedule_id' => $s->id, 'due_date' => $due->toDateString(),
                    'result' => $result, 'service_request_id' => $sr?->id, 'note' => $note, 'created_at' => now(),
                ]);
            }

            // D8: the next date comes from the due date; after a catch-up it is the first date after today
            $next = Frequency::after($s->first_due_date, $s->frequency, $s->interval_value, $due);
            if ($next->lte($today)) {
                $next = Frequency::after($s->first_due_date, $s->frequency, $s->interval_value, $today);
            }
            $s->forceFill(['next_due_date' => $next->toDateString()])->save();

            return $already ? 'skip' : $result;
        });

        if ($created) {
            $this->afterCreated($tenant, $created);
        }

        return $outcome;
    }

    /** The assets of the schedule that can still be serviced (D12). */
    private function availableAssets(PmSchedule $s)
    {
        return Asset::withoutGlobalScopes()->where('tenant_id', $s->tenant_id)->whereNull('deleted_at')
            ->whereIn('id', DB::table('pm_schedule_assets')->where('pm_schedule_id', $s->id)->select('asset_id'))
            ->where('status', '!=', 'decommissioned')->orderBy('asset_code')->get();
    }

    private function createRequest(Tenant $tenant, PmSchedule $s, Carbon $due, $assets, int $skipped): ServiceRequest
    {
        $cat  = ServiceCategory::withoutGlobalScopes()->find($s->service_category_id);
        $site = $s->site()->withoutGlobalScopes()->first();
        $tz   = $tenant->timezone ?: config('app.timezone');

        $teamLeadId = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deactivated_at')
            ->whereHas('coveredCategories', fn ($q) => $q->where('service_categories.id', $cat->id))->orderBy('id')->value('id');

        $hasCoordinates = $site && $site->lat !== null && $site->lng !== null;
        $batch = $assets->count() > 1;

        $sr = ServiceRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'sr_number' => ServiceRequest::nextSrNumber($tenant->id),
            'client_id' => $site?->client_id, 'project_site_id' => $s->project_site_id, 'service_category_id' => $cat->id,
            'primary_asset_id' => $batch ? null : $assets->first()->id,
            'priority' => $s->priority, 'title' => 'Maintenance: ' . $s->name,
            'description' => $s->notes . ($skipped > 0 ? ($s->notes ? "\n" : '') . "Created late. {$skipped} earlier dates were missed." : ''),
            'service_mode' => 'on_site', 'source' => 'pm_auto', 'coverage_type' => 'out_of_coverage', 'status' => 'pending',
            'billing_status' => 'not_applicable', 'invoice_mandatory' => (bool) ($cat->invoice_mandatory ?? false),
            // D10: not enforced unless the site always enforces it, and the signature waiver is allowed (nobody is at the door)
            'enforce_geofence' => $hasCoordinates && (bool) ($site->always_enforce_geofence ?? false),
            'allow_signature_waiver' => true,
            'created_by_user_id' => null, 'team_lead_user_id' => $teamLeadId,
            'pm_schedule_id' => $s->id, 'pm_due_date' => $due->toDateString(),
            'scheduled_eta_at' => Carbon::parse($due->toDateString() . ' 09:00', $tz),
            'contract_id' => $s->contract_id,                                   // D9: the pinned contract
            'proof_rules_snapshot' => [
                'require_before_photo' => (bool) $cat->require_before_photo, 'require_after_photo' => (bool) $cat->require_after_photo,
                'require_signature' => (bool) $cat->require_signature, 'require_checklist' => (bool) $cat->require_checklist,
                'require_per_asset_checklist' => (bool) $cat->require_per_asset_checklist,
                'snapshot_at' => now()->toIso8601String(), 'category_code' => $cat->code,
            ],
        ]);

        $policy  = $sr->resolveSlaPolicy();
        $started = now();
        $sr->update([
            'sla_clock_started_at'  => $started,
            'sla_response_due_at'   => $policy ? $started->copy()->addMinutes((int) $policy->response_minutes) : null,
            'sla_resolution_due_at' => $policy ? $started->copy()->addMinutes((int) $policy->resolution_minutes) : null,
        ]);
        SrStatusHistory::log($sr, null, 'pending', 'system', null, 'Created by maintenance schedule ' . $s->name);

        // D19 / B3: the schedule's template wins; a batch gets a row for each asset
        $checklists = app(Checklists::class);
        if ($batch) {
            $checklists->bind($sr, $cat, null, $s->form_template_id);
            app(BatchService::class)->attach($sr, $assets, $cat, $s->form_template_id);
        } else {
            $checklists->bind($sr, $cat, $assets->first(), $s->form_template_id);
        }

        app(ContractCoverage::class)->onCreated($sr);                       // D9: several contracts and no pin: a manager chooses
        app(ApprovalModes::class)->onCreated($sr->fresh(), $cat);          // B3: the category's approval mode

        $this->audit('pm_schedule.request_created', $s, ['sr_number' => $sr->sr_number, 'due_date' => $due->toDateString()]);

        return $sr->fresh();
    }

    /** Default technician (D10, D18) and the pm.generated message (PMC-07). Outside the transaction: it sends mail. */
    private function afterCreated(Tenant $tenant, ServiceRequest $sr): void
    {
        $s    = PmSchedule::withoutGlobalScopes()->find($sr->pm_schedule_id);
        $tech = $s?->default_technician_user_id ? User::withoutGlobalScopes()->find($s->default_technician_user_id) : null;
        $note = '';
        $assigned = null;

        if ($tech && ($tech->deactivated_at === null)) {
            $date = Carbon::parse($sr->pm_due_date);
            $off  = UserTimeOff::where('tenant_id', $tenant->id)->where('user_id', $tech->id)
                ->where('starts_at', '<=', $date->copy()->endOfDay())->where('ends_at', '>=', $date->copy()->startOfDay())->exists();
            if ($off) {
                $note = "Default technician {$tech->name} is off on {$date->format('j M Y')}. Assign someone.";
            } elseif ($sr->status === 'approved') {
                $this->assign($sr, $tech);
                $assigned = $tech;
            }
        }

        $svc  = app(NotificationService::class);
        $to   = $svc->usersWithRole('operations_manager', $tenant->id);
        if ($assigned) {
            $to = $to->push($assigned);
        } elseif ($note !== '') {
            $to = $to->merge($svc->usersWithRole('front_desk', $tenant->id));
        }
        $count = \App\Models\ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->count();
        $svc->notify('pm.generated', $to, [
            'sr_number' => $sr->sr_number, 'what' => $count > 1 ? "{$count} assets" : ($sr->primaryAsset?->name ?? 'the asset'),
            'site' => $sr->site?->name, 'date' => Carbon::parse($sr->pm_due_date)->format('j M Y'), 'note' => $note,
        ], route('service-requests.show', $sr, false));
    }

    /** The same writes as the office "Assign" action, for an approved request. */
    private function assign(ServiceRequest $sr, User $tech): void
    {
        $teamLeadUserId = $tech->team_lead_id ?? ($tech->role?->code === 'team_lead' ? $tech->id : null);
        $from = $sr->status;
        $sr->update(['status' => 'assigned', 'assigned_tech_user_id' => $tech->id, 'team_lead_user_id' => $teamLeadUserId]);
        SrStatusHistory::log($sr, $from, 'assigned', 'system', null, 'Default technician of the maintenance schedule', ['technician_id' => $tech->id, 'technician_name' => $tech->name]);
    }

    /** pm.overdue (PMC-08, D13): once when 2 or more days past due, once more after 7 days, never for finished work. */
    private function sendOverdue(Tenant $tenant, Carbon $today): int
    {
        $sent = 0;
        $svc  = app(NotificationService::class);
        $open = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('pm_schedule_id')->whereNotNull('pm_due_date')
            ->whereNotIn('status', self::WORK_DONE)->get();

        foreach ($open as $sr) {
            $days = Carbon::parse($sr->pm_due_date)->startOfDay()->diffInDays($today, false);
            $col  = null;
            if ($days >= 7 && ! $sr->pm_overdue_second_at) {
                $col = 'pm_overdue_second_at';
            } elseif ($days > 1 && ! $sr->pm_overdue_first_at) {
                $col = 'pm_overdue_first_at';
            }
            if (! $col) {
                continue;
            }
            $sr->forceFill([$col => now(), ...($col === 'pm_overdue_second_at' && ! $sr->pm_overdue_first_at ? ['pm_overdue_first_at' => now()] : [])])->save();
            $svc->notify('pm.overdue', $svc->usersWithRole('operations_manager', $tenant->id), [
                'sr_number' => $sr->sr_number, 'site' => $sr->site?->name, 'date' => Carbon::parse($sr->pm_due_date)->format('j M Y'),
            ], route('service-requests.show', $sr, false));
            $sent++;
        }

        return $sent;
    }

    private function audit(string $action, PmSchedule $s, array $new): void
    {
        app(AuditLogger::class)->log($action, ['tenant_id' => $s->tenant_id, 'item_type' => PmSchedule::class, 'item_id' => $s->id, 'new' => $new]);
    }
}
