<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DispatchSetting;
use App\Models\ServiceRequest;
use App\Models\UserTimeOff;
use App\Services\Audit\AuditLogger;
use App\Services\Dispatch\TechnicianSuggester;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DispatchController extends Controller
{
    public function __construct(private TechnicianSuggester $suggester, private AuditLogger $audit) {}

    /** Technician workload for the next 7 days (green / amber / red). */
    public function workload()
    {
        $user = auth()->user();
        abort_unless(\App\Services\Permissions\PermissionLevel::canView($user, 'sr.assign'), 403);

        $tenantId = (int) $user->tenant_id;
        $s     = DispatchSetting::forTenant($tenantId);
        $techs = $this->suggester->candidates($tenantId);
        $ids   = $techs->pluck('id');

        $load   = $techs->isEmpty() ? [] : $this->suggester->weekLoad($tenantId, $ids, $s);
        $punch  = $techs->isEmpty() ? [] : $this->suggester->lastPunches($ids);

        $rows = $techs->map(function ($t) use ($load, $punch, $s) {
            $cap = max(1, (int) ($t->daily_capacity_minutes ?: 480)) * 7;
            $min = $load[$t->id]['minutes'] ?? 0;
            $pct = round($min / $cap * 100, 1);
            return [
                'id'       => $t->id,
                'name'     => $t->name,
                'is_lead'  => $t->role?->code === 'team_lead',
                'jobs'     => $load[$t->id]['jobs'] ?? 0,
                'minutes'  => $min,
                'capacity' => $cap,
                'pct'      => $pct,
                'band'     => TechnicianSuggester::band($pct, $s),
                'next'     => $load[$t->id]['next'] ?? null,
                'location' => isset($punch[$t->id])
                    ? 'Last punch ' . $punch[$t->id]['at']->diffForHumans()
                    : (($t->home_base_lat !== null && $t->home_base_lng !== null) ? 'Home base' : 'None on file'),
            ];
        })->sortByDesc('pct')->values();

        return view('admin.dispatch.workload', ['rows' => $rows, 'settings' => $s]);
    }

    /** Ranked technicians for one request at a chosen arrival time (the assign form calls this). */
    public function suggestions(Request $request, ServiceRequest $service_request)
    {
        abort_unless(\App\Services\Permissions\PermissionLevel::canView(auth()->user(), 'sr.assign'), 403);
        abort_unless(\App\Services\Permissions\ServiceRequestAccess::mayAssign(auth()->user(), $service_request), 403); // Front Desk: critical and emergency requests only

        $arrival = null;
        if ($request->filled('arrival')) {
            try {
                $arrival = Carbon::parse($request->query('arrival'))->setTimezone(config('app.timezone'));
            } catch (\Throwable $e) {
                $arrival = null;
            }
        }

        $service_request->loadMissing(['site', 'category']);
        return response()->json($this->suggester->suggest($service_request, $arrival));
    }

    /** Week calendar: one row per technician, one column per day. Drag a job to another day to move it. */
    public function calendar(Request $request)
    {
        $user = auth()->user();
        abort_unless(\App\Services\Permissions\PermissionLevel::canView($user, 'sr.assign'), 403);
        $tenantId = (int) $user->tenant_id;

        try {
            $anchor = Carbon::parse($request->query('week', 'now'));
        } catch (\Throwable $e) {
            $anchor = now();
        }
        $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end   = $start->copy()->addDays(7);

        $techs = $this->suggester->candidates($tenantId);
        $ids   = $techs->pluck('id');

        $jobs = $techs->isEmpty() ? collect() : ServiceRequest::query()
            ->with('site:id,name')
            ->where('tenant_id', $tenantId)
            ->whereIn('assigned_tech_user_id', $ids)
            ->whereIn('status', TechnicianSuggester::OPEN_STATUSES)
            ->whereNotNull('scheduled_eta_at')
            ->where('scheduled_eta_at', '>=', $start)
            ->where('scheduled_eta_at', '<', $end)
            ->orderByRaw("CASE WHEN priority = 'emergency' THEN 0 ELSE 1 END")   // DSP-08: Emergency jobs sit at the top of their day
            ->orderBy('scheduled_eta_at')
            ->get(['id', 'sr_number', 'title', 'priority', 'status', 'assigned_tech_user_id', 'scheduled_eta_at', 'project_site_id']);

        $grid = [];
        foreach ($jobs as $j) {
            $grid[$j->assigned_tech_user_id][$j->scheduled_eta_at->format('Y-m-d')][] = $j;
        }

        // Time off shown as a grey chip on each day it touches.
        $off = [];
        if ($techs->isNotEmpty() && Schema::hasTable('user_time_off')) {
            $entries = UserTimeOff::where('tenant_id', $tenantId)
                ->whereIn('user_id', $ids)
                ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get();
            foreach ($entries as $e) {
                for ($i = 0; $i < 7; $i++) {
                    $d0 = $start->copy()->addDays($i);
                    $d1 = $d0->copy()->addDay();
                    if ($e->starts_at->lt($d1) && $e->ends_at->gt($d0)) {
                        $off[$e->user_id][$d0->format('Y-m-d')] = UserTimeOff::TYPES[$e->type] ?? 'Time off';
                    }
                }
            }
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $start->copy()->addDays($i);
        }

        return view('admin.dispatch.calendar', [
            'techs'    => $techs,
            'days'     => $days,
            'grid'     => $grid,
            'off'      => $off,
            'prevWeek' => $start->copy()->subWeek()->format('Y-m-d'),
            'nextWeek' => $start->copy()->addWeek()->format('Y-m-d'),
            'thisWeek' => now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            'isThis'   => $start->isSameDay(now()->startOfWeek(Carbon::MONDAY)),
            'jobMin'   => (int) DispatchSetting::forTenant($tenantId)->job_minutes,
        ]);
    }

    /** Drag and drop: move one assigned job to a new time and write sr_reschedules. */
    public function reschedule(Request $request, ServiceRequest $service_request)
    {
        $user = auth()->user();
        abort_unless(\App\Services\Permissions\PermissionLevel::canChange($user, 'sr.assign'), 403);
        abort_unless((int) $service_request->tenant_id === (int) $user->tenant_id, 404);
        abort_unless(\App\Services\Permissions\ServiceRequestAccess::mayAssign($user, $service_request), 403); // Front Desk: critical and emergency requests only

        if ($service_request->status !== 'assigned') {
            return response()->json(['message' => 'Only an assigned job that has not started can be moved.'], 422);
        }

        $data = $request->validate([
            'new_eta'       => ['required', 'date', 'after:now'],
            'reason'        => ['nullable', 'string', 'max:255'],
            'confirm_clash' => ['nullable', 'boolean'],
        ], ['new_eta.after' => 'Choose a time in the future.']);

        $tenantId = (int) $user->tenant_id;
        $techId   = (int) $service_request->assigned_tech_user_id;
        $new      = Carbon::parse($data['new_eta'])->setTimezone(config('app.timezone'));
        $jobMin   = max(1, (int) DispatchSetting::forTenant($tenantId)->job_minutes);

        // Time off blocks the move.
        if (Schema::hasTable('user_time_off')) {
            $off = UserTimeOff::where('tenant_id', $tenantId)->where('user_id', $techId)
                ->where('starts_at', '<', $new->copy()->addMinutes($jobMin))
                ->where('ends_at', '>', $new)->first();
            if ($off) {
                return response()->json(['message' => 'This technician is on ' . $off->type . ' until ' . $off->ends_at->format('d M H:i') . '.'], 422);
            }
        }

        // Another job close to that time: ask once, then allow.
        if (! $request->boolean('confirm_clash')) {
            $clash = DB::table('service_requests')
                ->where('tenant_id', $tenantId)
                ->where('assigned_tech_user_id', $techId)
                ->whereIn('status', TechnicianSuggester::OPEN_STATUSES)
                ->where('id', '!=', $service_request->id)
                ->whereBetween('scheduled_eta_at', [$new->copy()->subMinutes($jobMin - 1), $new->copy()->addMinutes($jobMin - 1)])
                ->orderBy('scheduled_eta_at')->first(['scheduled_eta_at']);
            if ($clash) {
                return response()->json([
                    'needs_confirm' => true,
                    'message' => 'This technician already has a job at ' . Carbon::parse($clash->scheduled_eta_at)->format('d M H:i') . '. Move it anyway?',
                ], 409);
            }
        }

        $previous = $service_request->scheduled_eta_at;

        // DSP-08: dropping a job on the time it already has changes nothing: no row, no audit, no email
        if ($previous && $previous->equalTo($new)) {
            return response()->json(['ok' => true, 'unchanged' => true, 'message' => $service_request->sr_number . ' stays at ' . $new->format('D d M, H:i') . '.']);
        }

        DB::transaction(function () use ($service_request, $new, $previous, $data, $user, $tenantId) {
            $row = [
                'tenant_id'              => $tenantId,
                'service_request_id'     => $service_request->id,
                'previous_eta_at'        => $previous,
                'new_eta_at'             => $new,
                'reason'                 => $data['reason'] ?? null,
                'source'                 => 'drag_drop',
                'rescheduled_by_user_id' => $user->id,
            ];
            foreach (['created_at', 'updated_at'] as $c) {
                if (Schema::hasColumn('sr_reschedules', $c)) {
                    $row[$c] = now();
                }
            }
            DB::table('sr_reschedules')->insert($row);

            $service_request->update(['scheduled_eta_at' => $new]);

            $this->audit->log('request.rescheduled', [
                'item_type' => ServiceRequest::class,
                'item_id'   => $service_request->id,
                'old'       => ['scheduled_eta_at' => optional($previous)->toDateTimeString()],
                'new'       => ['sr_number' => $service_request->sr_number, 'scheduled_eta_at' => $new->toDateTimeString(), 'reason' => $data['reason'] ?? null],
            ]);
        });

        $emailed = $this->tellClient($service_request->fresh(), $previous, $new, $data['reason'] ?? null);

        return response()->json(['ok' => true, 'client_emailed' => $emailed, 'message' => $service_request->sr_number . ' moved to ' . $new->format('D d M, H:i') . '.' . ($emailed ? ' The client was emailed.' : '')]);
    }

    /**
     * Email the client contact that the visit moved (DSP-08). The contact is the client's primary active contact with an email,
     * else the first active contact with one. A failed email never undoes the move. Returns true when an email was sent.
     */
    private function tellClient(ServiceRequest $sr, ?Carbon $previous, Carbon $new, ?string $reason): bool
    {
        $contact = \App\Models\ClientContact::query()->where('client_id', $sr->client_id)->where('is_active', true)
            ->whereNotNull('email')->where('email', '!=', '')
            ->orderByDesc('is_primary')->orderBy('id')->first();
        if (! $contact) {
            return false;
        }

        try {
            $tz = \App\Models\Tenant::query()->whereKey($sr->tenant_id)->value('timezone') ?: config('app.timezone');
            \Illuminate\Support\Facades\Mail::to($contact->email)->send(new \App\Mail\JobRescheduledMail(
                $sr, $contact->name, $previous?->copy()->setTimezone($tz), $new->copy()->setTimezone($tz), $reason,
            ));

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Reschedule email failed', ['sr' => $sr->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function settings()
    {
        $this->ensureAdmin();
        return view('admin.dispatch.settings', ['s' => DispatchSetting::forTenant((int) auth()->user()->tenant_id)]);
    }

    public function updateSettings(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'w_skill'        => ['required', 'integer', 'between:0,100'],
            'w_availability' => ['required', 'integer', 'between:0,100'],
            'w_workload'     => ['required', 'integer', 'between:0,100'],
            'w_proximity'    => ['required', 'integer', 'between:0,100'],
            'w_first_fix'    => ['required', 'integer', 'between:0,100'],
            'job_minutes'    => ['required', 'integer', 'between:15,1440'],
            // DSP-06: the workload colours (percent of the week's capacity), green below the first, amber up to the second, red above
            'workload_green_below' => ['required', 'integer', 'between:1,100', 'lt:workload_amber_up_to'],
            'workload_amber_up_to' => ['required', 'integer', 'between:2,200'],
        ], [
            'workload_green_below.lt' => 'Green must end below the amber limit.',
            'job_minutes.between' => 'Enter between 15 and 1440 minutes.',
            'w_skill.between'     => 'Each weight must be between 0 and 100.',
        ]);

        if (array_sum(array_intersect_key($data, array_flip(['w_skill', 'w_availability', 'w_workload', 'w_proximity', 'w_first_fix']))) < 1) {
            return back()->withErrors(['w_skill' => 'At least one weight must be above zero.'])->withInput();
        }

        $s   = DispatchSetting::forTenant((int) auth()->user()->tenant_id);
        $old = $s->only(array_keys($data));
        $s->update($data);

        $this->audit->log('dispatch.settings_updated', [
            'item_type' => DispatchSetting::class,
            'item_id'   => $s->id,
            'old'       => $old,
            'new'       => $data,
        ]);

        return redirect()->route('dispatch.settings')->with('dispatch_saved', 'Dispatch settings saved.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()->role?->code === 'company_admin', 403);
    }
}
