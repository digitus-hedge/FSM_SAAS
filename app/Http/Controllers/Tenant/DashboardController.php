<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        // ─── Period ─────────────────────────────────────────────────────
        $range = $request->input('range', 'last30');

        if ($range === 'custom') {
            $startInput = $request->input('start');
            $endInput   = $request->input('end');
            try {
                $from = $startInput ? Carbon::parse($startInput)->startOfDay() : now()->subDays(30)->startOfDay();
                $to   = $endInput   ? Carbon::parse($endInput)->endOfDay()   : now()->endOfDay();
            } catch (\Exception $e) {
                $from = now()->subDays(30)->startOfDay();
                $to   = now()->endOfDay();
                $range = 'last30';
            }
            if ($from->gt($to)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            if ($to->gt(now())) $to = now()->endOfDay();
        } else {
            if (! in_array($range, ['today', 'last7', 'last30', 'thismonth', 'lastmonth'])) {
                $range = 'last30';
            }
            [$from, $to] = match ($range) {
                'today'     => [now()->startOfDay(), now()->endOfDay()],
                'last7'     => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
                'thismonth' => [now()->startOfMonth(), now()->endOfDay()],
                'lastmonth' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
                default     => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            };
        }

        $days = max(1, (int) round($from->diffInDays($to)) + 1);

        // ─── 1. KPI Tiles ───────────────────────────────────────────────

        // Open requests = created in the period AND still open (date-filtered)
        $openRequests = ServiceRequest::whereNotIn('status', ['completed', 'cancelled','pending'])
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // Total created in period (any status)
        $createdInPeriod = ServiceRequest::whereBetween('created_at', [$from, $to])->count();

        // Completed in the period
        $completed = ServiceRequest::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
            ->count();

        // SLA compliance
        $slaCompleted = ServiceRequest::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
            ->whereNotNull('sla_resolution_due_at')
            ->count();

        $slaMet = ServiceRequest::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
            ->whereNotNull('sla_resolution_due_at')
            ->whereRaw('COALESCE(completed_at, updated_at) <= sla_resolution_due_at')
            ->count();

        $slaPct = $slaCompleted > 0 ? round(($slaMet / $slaCompleted) * 100, 1) : 0;

        // Response time avg (minutes from created to tech_accepted)
        $responseMin = (int) ServiceRequest::whereBetween('created_at', [$from, $to])
            ->whereNotNull('tech_accepted_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, tech_accepted_at)) as m')
            ->value('m');

        // Resolution time avg (minutes from first punch-in to completion)
        $resolutionMin = (int) ServiceRequest::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
            ->whereNotNull('first_punch_in_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, first_punch_in_at, COALESCE(completed_at, updated_at))) as m')
            ->value('m');

        // Review time avg (minutes from punch-out to QC pass)
        $reviewMin = (int) ServiceRequest::whereBetween('last_punch_out_at', [$from, $to])
            ->whereNotNull('qc_reviewed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, last_punch_out_at, qc_reviewed_at)) as m')
            ->value('m');

        // First-time fix %
        $firstTimeFix = ServiceRequest::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
            ->count();

        $reworkCount = ServiceRequest::where(function ($q) {
                $q->where('status', 'rework')
                ->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('sr_status_history')
                        ->whereColumn('sr_status_history.service_request_id', 'service_requests.id')
                        ->where('sr_status_history.to_status', 'rework');
                });
            })
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $firstFixPct = $firstTimeFix > 0 ? round((($firstTimeFix - $reworkCount) / $firstTimeFix) * 100, 1) : 0;

        // Customer rating placeholder
        $customerRating = null;
        $nps            = null;

        // ─── 1b. Change versus the previous period (for the up/down badges) ───
        // Previous period = the same number of days, immediately before $from.
        $prevFrom = $from->copy()->subDays($days);
        $prevTo   = $from->copy()->subSecond();
        $prev     = $this->periodStats($prevFrom, $prevTo);

        $deltas = [
            'created'   => $this->delta($createdInPeriod, $prev['created']),
            'completed' => $this->delta($completed, $prev['completed']),
            'response'  => $responseMin > 0
                ? $this->delta($responseMin, $prev['responseMin'], false, true)
                : null,
            'firstfix'  => ($firstTimeFix > 0 && $prev['completed'] > 0)
                ? $this->delta($firstFixPct, $prev['firstFixPct'], true)
                : null,
            'sla'       => ($slaCompleted > 0 && $prev['slaCompleted'] > 0)
                ? $this->delta($slaPct, $prev['slaPct'], true)
                : null,
        ];

        // ─── 2. Open requests by status (DATE-FILTERED to match Open requests tile) ───
        $openStatuses = ['pending', 'quoted', 'approved', 'assigned', 'in_progress', 'pending_review', 'awaiting_invoice', 'rework'];

        $statusCounts = ServiceRequest::whereIn('status', $openStatuses)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')->toArray();

        $byStatus = collect($openStatuses)->map(fn ($s) => [
            'label' => ucwords(str_replace('_', ' ', $s)),
            'count' => $statusCounts[$s] ?? 0,
        ])->filter(fn ($r) => $r['count'] > 0)->values();

        $byStatusMax = $byStatus->max('count') ?: 1;

        // ─── 3. Created and completed each day (used by the summary banner) ───
        $createdPerDay = ServiceRequest::whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd')->toArray();

        $completedPerDay = ServiceRequest::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
            ->selectRaw('DATE(COALESCE(completed_at, updated_at)) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd')->toArray();

        $chartLabels   = [];
        $chartCreated  = [];
        $chartComplete = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $chartLabels[]   = $d->format('d M');
            $chartCreated[]  = (int) ($createdPerDay[$key] ?? 0);
            $chartComplete[] = (int) ($completedPerDay[$key] ?? 0);
        }

        // ─── 3b. Requests per month (last 12 months) and per week (last 12 weeks) ───
        // These are NOT affected by the period filter; they always show the recent trend.
        $trend = $this->trend();

        // ─── 4. SLA by priority ─────────────────────────────────────────
        $priorities = ['emergency', 'critical', 'high', 'medium', 'low'];
        $slaByPriority = collect($priorities)->map(function ($p) use ($from, $to) {
            $compl = ServiceRequest::where('status', 'completed')
                ->where('priority', $p)
                ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
                ->whereNotNull('sla_resolution_due_at')
                ->count();
            $met = ServiceRequest::where('status', 'completed')
                ->where('priority', $p)
                ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
                ->whereNotNull('sla_resolution_due_at')
                ->whereRaw('COALESCE(completed_at, updated_at) <= sla_resolution_due_at')
                ->count();
            return [
                'priority'  => $p,
                'label'     => ucfirst($p),
                'completed' => $compl,
                'met'       => $met,
                'pct'       => $compl > 0 ? round(($met / $compl) * 100, 1) : 0,
            ];
        });

        return view('tenant.dashboard', compact(
            'tenant', 'range', 'days', 'from', 'to',
            'openRequests', 'createdInPeriod', 'completed',
            'slaPct', 'slaMet', 'slaCompleted',
            'responseMin', 'resolutionMin', 'reviewMin', 'firstFixPct',
            'customerRating', 'nps',
            'byStatus', 'byStatusMax',
            'chartLabels', 'chartCreated', 'chartComplete',
            'slaByPriority', 'deltas', 'trend'
        ));
    }

    /**
     * Headline numbers for one period (used for the previous-period comparison).
     * Same rules as the current-period tiles above.
     */
    private function periodStats(Carbon $from, Carbon $to): array
    {
        $created = ServiceRequest::whereBetween('created_at', [$from, $to])->count();

        $completed = ServiceRequest::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
            ->count();

        $slaCompleted = ServiceRequest::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
            ->whereNotNull('sla_resolution_due_at')
            ->count();

        $slaMet = ServiceRequest::where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(completed_at, updated_at)'), [$from, $to])
            ->whereNotNull('sla_resolution_due_at')
            ->whereRaw('COALESCE(completed_at, updated_at) <= sla_resolution_due_at')
            ->count();

        $responseMin = (int) ServiceRequest::whereBetween('created_at', [$from, $to])
            ->whereNotNull('tech_accepted_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, tech_accepted_at)) as m')
            ->value('m');

        $reworkCount = ServiceRequest::where(function ($q) {
                $q->where('status', 'rework')
                ->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))->from('sr_status_history')
                        ->whereColumn('sr_status_history.service_request_id', 'service_requests.id')
                        ->where('sr_status_history.to_status', 'rework');
                });
            })
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            'created'      => $created,
            'completed'    => $completed,
            'slaCompleted' => $slaCompleted,
            'slaPct'       => $slaCompleted > 0 ? round(($slaMet / $slaCompleted) * 100, 1) : 0,
            'responseMin'  => $responseMin,
            'firstFixPct'  => $completed > 0 ? round((($completed - $reworkCount) / $completed) * 100, 1) : 0,
        ];
    }

    /**
     * Change badge data.
     *  $points = true  -> difference in percentage points (for % metrics)
     *  $points = false -> relative change in percent (for counts and times)
     *  $lowerIsBetter  -> a drop is shown as an improvement (e.g. response time)
     * Returns null when there is nothing to compare or nothing changed.
     */
    private function delta($current, $previous, bool $points = false, bool $lowerIsBetter = false): ?array
    {
        if ($points) {
            $diff = round($current - $previous, 1);
        } else {
            if ($previous <= 0) return null;
            $diff = round((($current - $previous) / $previous) * 100, 1);
        }
        if ($diff == 0.0) return null;

        $up = $diff > 0;
        return [
            'text' => abs($diff) . '%',
            'up'   => $up,
            'good' => $lowerIsBetter ? ! $up : $up,
        ];
    }

    /**
     * Created / completed per month (last 12 months) and per ISO week (last 12 weeks).
     */
    private function trend(): array
    {
        $doneExpr = 'COALESCE(completed_at, updated_at)';

        // ---- months ----
        $monthStart = now()->startOfMonth()->subMonths(11);

        $mCreated = ServiceRequest::where('created_at', '>=', $monthStart)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as k, COUNT(*) as c")
            ->groupBy('k')->pluck('c', 'k')->toArray();

        $mDone = ServiceRequest::where('status', 'completed')
            ->where(DB::raw($doneExpr), '>=', $monthStart)
            ->selectRaw("DATE_FORMAT($doneExpr, '%Y-%m') as k, COUNT(*) as c")
            ->groupBy('k')->pluck('c', 'k')->toArray();

        $monthly = ['labels' => [], 'full' => [], 'created' => [], 'completed' => []];
        for ($i = 0; $i < 12; $i++) {
            $m   = $monthStart->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $monthly['labels'][]    = mb_substr($m->format('M'), 0, 1);
            $monthly['full'][]      = $m->format('M Y');
            $monthly['created'][]   = (int) ($mCreated[$key] ?? 0);
            $monthly['completed'][] = (int) ($mDone[$key] ?? 0);
        }

        // ---- weeks (ISO, Monday first) ----
        $weekStart = now()->startOfWeek()->subWeeks(11);

        $wCreated = ServiceRequest::where('created_at', '>=', $weekStart)
            ->selectRaw('YEARWEEK(created_at, 3) as k, COUNT(*) as c')
            ->groupBy('k')->pluck('c', 'k')->toArray();

        $wDone = ServiceRequest::where('status', 'completed')
            ->where(DB::raw($doneExpr), '>=', $weekStart)
            ->selectRaw("YEARWEEK($doneExpr, 3) as k, COUNT(*) as c")
            ->groupBy('k')->pluck('c', 'k')->toArray();

        $weekly = ['labels' => [], 'full' => [], 'created' => [], 'completed' => []];
        for ($i = 0; $i < 12; $i++) {
            $w   = $weekStart->copy()->addWeeks($i);
            $key = $w->format('oW');   // ISO year + 2-digit ISO week, same as YEARWEEK(..., 3)
            $weekly['labels'][]    = 'W' . $w->isoWeek();
            $weekly['full'][]      = 'Week of ' . $w->format('d M');
            $weekly['created'][]   = (int) ($wCreated[$key] ?? 0);
            $weekly['completed'][] = (int) ($wDone[$key] ?? 0);
        }

        return ['monthly' => $monthly, 'weekly' => $weekly];
    }

    public static function fmtMinutes(?int $m): string
    {
        if (! $m) return '—';
        $h  = intdiv($m, 60);
        $mm = $m % 60;
        return $h > 0 ? "{$h} h {$mm} min" : "{$mm} min";
    }
}
