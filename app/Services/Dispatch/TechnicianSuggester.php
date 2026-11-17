<?php

namespace App\Services\Dispatch;

use App\Models\DispatchSetting;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprint 7.5: ranks technicians for a request. Suggestion only; the assigner
 * can always pick anyone, and nothing here assigns.
 */
class TechnicianSuggester
{
    /** Statuses in which a technician is still holding the job. */
    public const OPEN_STATUSES = ['assigned', 'in_progress', 'rework'];

    /** Workload colours: green below 60 percent, amber 60 to 90, red above 90. */
    public const GREEN_BELOW = 60;
    public const AMBER_UP_TO = 90;

    /** Distance at (or beyond) which the proximity score is zero. */
    private const FAR_KM = 50;

    /** Fewer completed jobs than this means "not enough history" for first-time fix. */
    private const MIN_HISTORY = 3;

    public static function band(float $pct): string
    {
        return $pct < self::GREEN_BELOW ? 'green' : ($pct <= self::AMBER_UP_TO ? 'amber' : 'red');
    }

    /** Active field technicians and team leads of the company (same pool the assign form has always used). */
    public function candidates(int $tenantId): Collection
    {
        $roleIds = DB::table('roles')->where('tenant_id', $tenantId)
            ->whereIn('code', ['field_technician', 'team_lead'])->pluck('id');
        if ($roleIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->with('role:id,code')
            ->where('tenant_id', $tenantId)
            ->whereIn('role_id', $roleIds)
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get(['id', 'name', 'team_lead_id', 'role_id', 'home_base_lat', 'home_base_lng', 'daily_capacity_minutes']);
    }

    /**
     * Scheduled minutes over the next 7 days per technician.
     * Returns [userId => ['jobs' => int, 'minutes' => int, 'next' => Carbon|null]].
     */
    public function weekLoad(int $tenantId, Collection $userIds, DispatchSetting $s, ?int $excludeSrId = null): array
    {
        $rows = DB::table('service_requests')
            ->where('tenant_id', $tenantId)
            ->whereIn('assigned_tech_user_id', $userIds)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('scheduled_eta_at')
            ->whereBetween('scheduled_eta_at', [now()->startOfDay(), now()->addDays(6)->endOfDay()])
            ->when($excludeSrId, fn ($q) => $q->where('id', '!=', $excludeSrId))
            ->get(['assigned_tech_user_id', 'scheduled_eta_at']);

        $out = [];
        foreach ($userIds as $id) {
            $out[$id] = ['jobs' => 0, 'minutes' => 0, 'next' => null];
        }
        foreach ($rows as $r) {
            $id  = (int) $r->assigned_tech_user_id;
            $eta = Carbon::parse($r->scheduled_eta_at);
            $out[$id]['jobs']++;
            $out[$id]['minutes'] += (int) $s->job_minutes;
            if ($out[$id]['next'] === null || $eta->lt($out[$id]['next'])) {
                $out[$id]['next'] = $eta;
            }
        }
        return $out;
    }

    /** Last punch per technician (last 30 days): [userId => ['lat','lng','at' => Carbon]]. */
    public function lastPunches(Collection $userIds): array
    {
        if (! Schema::hasTable('service_request_punches')) {
            return [];
        }
        $rows = DB::table('service_request_punches')
            ->whereIn('technician_user_id', $userIds)
            ->whereNotNull('gps_lat')->whereNotNull('gps_lng')
            ->where('recorded_at_device', '>=', now()->subDays(30))
            ->orderByDesc('recorded_at_device')
            ->get(['technician_user_id', 'gps_lat', 'gps_lng', 'recorded_at_device']);

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->technician_user_id;
            if (! isset($out[$id])) {
                $out[$id] = ['lat' => (float) $r->gps_lat, 'lng' => (float) $r->gps_lng,
                             'at' => Carbon::parse($r->recorded_at_device)];
            }
        }
        return $out;
    }

    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($a)));
    }

    /**
     * Ranked list, best first. Each item:
     * id, name, is_lead, total (0-100), parts (each 0-100), reasons[], workload_pct, workload_band.
     */
    public function suggest(ServiceRequest $sr, $arrival = null): array
    {
        $tenantId = (int) $sr->tenant_id;
        $s        = DispatchSetting::forTenant($tenantId);
        $arrival  = $arrival ? Carbon::parse($arrival) : now()->addDay()->setTime(9, 0);
        $jobMin   = max(1, (int) $s->job_minutes);

        $techs = $this->candidates($tenantId);
        if ($techs->isEmpty()) {
            return [];
        }
        $ids = $techs->pluck('id');

        // Skill: which of them have the request's category.
        $skilled = [];
        if (Schema::hasTable('user_skills')) {
            $skilled = DB::table('user_skills')
                ->whereIn('user_id', $ids)
                ->where('service_category_id', $sr->service_category_id)
                ->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        }
        $categoryName = $sr->category->name ?? 'this category';

        // Availability: another open job within one job-length of the chosen arrival.
        $clashes = DB::table('service_requests')
            ->where('tenant_id', $tenantId)
            ->whereIn('assigned_tech_user_id', $ids)
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('id', '!=', $sr->id)
            ->whereBetween('scheduled_eta_at', [$arrival->copy()->subMinutes($jobMin - 1), $arrival->copy()->addMinutes($jobMin - 1)])
            ->get(['assigned_tech_user_id', 'scheduled_eta_at'])
            ->groupBy('assigned_tech_user_id');

        // Time off that covers the chosen arrival time.
        $leave = [];
        if (Schema::hasTable('user_time_off')) {
            $leave = DB::table('user_time_off')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $ids)
                ->where('starts_at', '<', $arrival->copy()->addMinutes($jobMin))
                ->where('ends_at', '>', $arrival)
                ->orderBy('starts_at')
                ->get(['user_id', 'type', 'ends_at'])
                ->keyBy('user_id')->all();
        }

        $load   = $this->weekLoad($tenantId, $ids, $s, $sr->id);
        $punch  = $this->lastPunches($ids);

        // First-time fix: completed jobs finished without rework.
        $fix = DB::table('service_requests')
            ->where('tenant_id', $tenantId)
            ->whereIn('assigned_tech_user_id', $ids)
            ->where('status', 'completed')
            ->selectRaw('assigned_tech_user_id as uid, COUNT(*) as done, SUM(CASE WHEN rework_count = 0 THEN 1 ELSE 0 END) as clean')
            ->groupBy('assigned_tech_user_id')
            ->get()->keyBy('uid');

        $site    = $sr->site;
        $siteLat = $site && $site->lat !== null ? (float) $site->lat : null;
        $siteLng = $site && $site->lng !== null ? (float) $site->lng : null;

        $w    = $s->weights();
        $wSum = max(1, array_sum($w));
        $out  = [];

        foreach ($techs as $t) {
            $id = (int) $t->id;
            $reasons = [];

            // Skill
            $hasSkill = in_array($id, $skilled, true);
            $skill = $hasSkill ? 1.0 : 0.0;
            $reasons[] = $hasSkill ? "Has the {$categoryName} skill" : "No {$categoryName} skill listed";

            // Availability
            $clash = $clashes->get($id);
            if (isset($leave[$id])) {
                $avail = 0.0;
                $reasons[] = 'On ' . $leave[$id]->type . ' until ' . Carbon::parse($leave[$id]->ends_at)->format('d M H:i');
            } else {
                $avail = $clash && $clash->count() ? 0.0 : 1.0;
                $reasons[] = $avail
                    ? 'Free at ' . $arrival->format('d M H:i')
                    : 'Already has a job at ' . Carbon::parse($clash->first()->scheduled_eta_at)->format('d M H:i');
            }

            // Workload
            $cap   = max(1, (int) ($t->daily_capacity_minutes ?: 480)) * 7;
            $mins  = $load[$id]['minutes'] ?? 0;
            $pct   = round($mins / $cap * 100, 1);
            $work  = max(0.0, 1 - $pct / 100);
            $reasons[] = ($load[$id]['jobs'] ?? 0) . ' job(s) in the next 7 days, ' . $pct . '% of capacity';

            // Proximity: last punch, else home base. Shown with its age, never live.
            $prox = 0.0;
            $from = null;
            if (isset($punch[$id])) {
                $from = ['lat' => $punch[$id]['lat'], 'lng' => $punch[$id]['lng'],
                         'label' => 'last punch ' . $punch[$id]['at']->diffForHumans()];
            } elseif ($t->home_base_lat !== null && $t->home_base_lng !== null) {
                $from = ['lat' => (float) $t->home_base_lat, 'lng' => (float) $t->home_base_lng, 'label' => 'home base'];
            }
            if ($siteLat === null) {
                $reasons[] = 'Site has no saved coordinates, so distance is not scored';
            } elseif (! $from) {
                $reasons[] = 'No last punch or home base on file';
            } else {
                $km   = self::km($from['lat'], $from['lng'], $siteLat, $siteLng);
                $prox = max(0.0, 1 - $km / self::FAR_KM);
                $reasons[] = round($km, 1) . ' km from the site (' . $from['label'] . ')';
            }

            // First-time fix
            $row = $fix->get($id);
            if ($row && (int) $row->done >= self::MIN_HISTORY) {
                $ftf = (int) $row->clean / (int) $row->done;
                $reasons[] = round($ftf * 100) . '% fixed first time (' . (int) $row->done . ' jobs)';
            } else {
                $ftf = 0.5;
                $reasons[] = 'Not enough finished jobs yet for first-time fix';
            }

            $parts = ['skill' => $skill, 'availability' => $avail, 'workload' => $work, 'proximity' => $prox, 'first_fix' => $ftf];
            $total = 0.0;
            foreach ($parts as $k => $v) {
                $total += $v * $w[$k];
            }

            $out[] = [
                'id'            => $id,
                'name'          => $t->name,
                'is_lead'       => $t->role?->code === 'team_lead',
                'total'         => (int) round($total / $wSum * 100),
                'parts'         => array_map(fn ($v) => (int) round($v * 100), $parts),
                'reasons'       => $reasons,
                'workload_pct'  => $pct,
                'workload_band' => self::band($pct),
            ];
        }

        usort($out, fn ($a, $b) => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);
        return $out;
    }
}
