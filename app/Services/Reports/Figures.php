<?php

namespace App\Services\Reports;

use App\Models\Contract;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestExpense;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Models\UserTimeOff;
use Carbon\Carbon;

/**
 * The dashboard figures of Reports-Summaries-Preferences spec RPT-03 to RPT-07 (D6 to D8). Every query names its company, because
 * some of these tables have no company scope. A figure with nothing behind it returns `null` ("No data yet").
 * $from and $to are company-local dates, both included.
 */
class Figures
{
    private function requests(int $tid)
    {
        return ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tid);
    }

    /** UTC bounds of the company-local dates. */
    private function bounds(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        $tz = $tenant->timezone ?: config('app.timezone');

        return [Carbon::parse($from->toDateString(), $tz)->utc(), Carbon::parse($to->toDateString(), $tz)->addDay()->utc()];
    }

    /** All figures for a range. */
    public function all(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        return [
            'utilisation'        => $this->utilisation($tenant, $from, $to),
            'maintenance'        => $this->maintenanceOnTime($tenant, $from, $to),
            'pm_vs_reactive'     => $this->pmVsReactive($tenant, $from, $to),
            'contract_use'       => $this->contractUse($tenant),
            'callbacks'          => $this->callbacks($tenant, $from, $to),
            'first_time_fix'     => $this->firstTimeFix($tenant, $from, $to),
            'vendor_spend'       => $this->vendorSpend($tenant, $from, $to),
        ];
    }

    /** Work minutes against the technicians' available minutes (weekdays, less time off). */
    public function utilisation(Tenant $tenant, Carbon $from, Carbon $to): ?array
    {
        [$a, $b] = $this->bounds($tenant, $from, $to);
        $techs = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deactivated_at')
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['field_technician', 'team_lead']))->get(['id', 'daily_capacity_minutes']);
        if ($techs->isEmpty()) {
            return null;
        }

        $days = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days += $d->isWeekday() ? 1 : 0;
        }
        $capacity = 0;
        foreach ($techs as $t) {
            $off = 0;
            foreach (UserTimeOff::where('tenant_id', $tenant->id)->where('user_id', $t->id)->where('starts_at', '<', $b)->where('ends_at', '>=', $a)->get() as $e) {
                $s = Carbon::parse(max($e->starts_at->toDateString(), $from->toDateString()));
                $z = Carbon::parse(min($e->ends_at->toDateString(), $to->toDateString()));
                for ($d = $s->copy(); $d->lte($z); $d->addDay()) {
                    $off += $d->isWeekday() ? 1 : 0;
                }
            }
            $capacity += max(0, $days - $off) * (int) ($t->daily_capacity_minutes ?: 480);
        }
        $booked = (int) $this->requests($tenant->id)->whereIn('assigned_tech_user_id', $techs->pluck('id'))->whereBetween('last_punch_out_at', [$a, $b])->sum('total_work_minutes');

        return $capacity > 0 ? ['booked' => $booked, 'capacity' => $capacity, 'percent' => round($booked / $capacity * 100, 1)] : null;
    }

    /** Maintenance requests completed by their due date or not, and the ones still open past it. */
    public function maintenanceOnTime(Tenant $tenant, Carbon $from, Carbon $to): ?array
    {
        [$a, $b] = $this->bounds($tenant, $from, $to);
        $done = $this->requests($tenant->id)->whereNotNull('pm_schedule_id')->whereNotNull('completed_at')->whereBetween('completed_at', [$a, $b])->get(['completed_at', 'pm_due_date']);
        $open = $this->requests($tenant->id)->whereNotNull('pm_schedule_id')->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('pm_due_date', '<', Carbon::now($tenant->timezone ?: config('app.timezone'))->toDateString())->count();
        if ($done->isEmpty() && $open === 0) {
            return null;
        }
        $tz = $tenant->timezone ?: config('app.timezone');
        $onTime = $done->filter(fn ($r) => Carbon::parse($r->completed_at)->setTimezone($tz)->toDateString() <= Carbon::parse($r->pm_due_date)->toDateString())->count();

        return ['on_time' => $onTime, 'late' => $done->count() - $onTime, 'open_overdue' => $open,
            'percent' => $done->count() ? round($onTime / $done->count() * 100, 1) : null];
    }

    public function pmVsReactive(Tenant $tenant, Carbon $from, Carbon $to): ?array
    {
        [$a, $b] = $this->bounds($tenant, $from, $to);
        $q = $this->requests($tenant->id)->whereBetween('created_at', [$a, $b])->where('is_master_sr', false);
        $pm = (clone $q)->where('source', 'pm_auto')->count();
        $reactive = (clone $q)->where('source', '!=', 'pm_auto')->count();

        return ($pm + $reactive) === 0 ? null : ['maintenance' => $pm, 'reactive' => $reactive, 'percent_maintenance' => round($pm / ($pm + $reactive) * 100, 1)];
    }

    /** Calls used against the cap of each active contract. */
    public function contractUse(Tenant $tenant): ?array
    {
        $rows = Contract::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('status', ['active', 'expiring_soon'])->whereNotNull('max_calls')
            ->get(['contract_number', 'calls_used_current_period', 'max_calls']);

        return $rows->isEmpty() ? null : $rows->map(fn ($c) => [
            'contract' => $c->contract_number, 'used' => (int) $c->calls_used_current_period, 'max' => (int) $c->max_calls,
            'percent' => round($c->calls_used_current_period / max(1, $c->max_calls) * 100, 1),
        ])->all();
    }

    /** Blueprint 7.9: jobs reopened inside the company's reopen window divided by completed jobs. */
    public function callbacks(Tenant $tenant, Carbon $from, Carbon $to): ?array
    {
        [$a, $b] = $this->bounds($tenant, $from, $to);
        $window = (int) (TenantSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('reopen_window_days') ?? 14);
        $completed = $this->requests($tenant->id)->whereBetween('completed_at', [$a, $b])->count();
        $reopened = $this->requests($tenant->id)->where('reopened_count', '>', 0)->whereBetween('reopened_at', [$a, $b])->count();
        if ($completed === 0 && $reopened === 0) {
            return null;
        }
        // a window of 0 means reopening is never allowed, so there are no callbacks
        $reopened = $window > 0 ? $reopened : 0;

        return ['reopened' => $reopened, 'completed' => $completed, 'window_days' => $window,
            'percent' => $completed > 0 ? round($reopened / $completed * 100, 1) : null];
    }

    /** Blueprint 7.9: completed requests with zero rework divided by completed requests. */
    public function firstTimeFix(Tenant $tenant, Carbon $from, Carbon $to): ?array
    {
        [$a, $b] = $this->bounds($tenant, $from, $to);
        $done = $this->requests($tenant->id)->whereBetween('completed_at', [$a, $b])->get(['rework_count', 'qc_rework_count', 'reopened_count']);
        if ($done->isEmpty()) {
            return null;
        }
        $fixed = $done->filter(fn ($r) => (int) $r->rework_count === 0 && (int) $r->qc_rework_count === 0 && (int) $r->reopened_count === 0)->count();

        return ['first_time' => $fixed, 'completed' => $done->count(), 'percent' => round($fixed / $done->count() * 100, 1)];
    }

    public function vendorSpend(Tenant $tenant, Carbon $from, Carbon $to): ?array
    {
        [$a, $b] = $this->bounds($tenant, $from, $to);
        $q = ServiceRequestExpense::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('vendor_id')->where('approval_status', 'approved')->whereBetween('created_at', [$a, $b]);
        $total = (float) (clone $q)->sum('total_amount');

        return $total > 0 ? ['total' => $total, 'currency' => $tenant->base_currency, 'lines' => (clone $q)->count()] : null;
    }
}
