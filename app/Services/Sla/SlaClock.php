<?php

namespace App\Services\Sla;

use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantBusinessHour;
use App\Models\TenantHoliday;
use Carbon\Carbon;

/**
 * The SLA clock (blueprint 7.1, "SLA clock"). Turns a start time and a target in minutes into a due time:
 * - a policy without business hours counts every minute;
 * - a policy with business hours counts only the open minutes of the company's week, in the company's time zone,
 *   and skips its holidays;
 * - a hold whose reason pauses the SLA stops the clock, and resuming pushes the due times out by the minutes that were left.
 *
 * Due times stay stored in `service_requests.sla_*_due_at` (UTC), so reports and dashboards read them as before.
 * Used by every place that starts a clock, by hold and resume, by the priority change and by the `sla:check` job.
 * A calendar is read once for each company and kept for the life of the instance: resolve a new instance after the
 * hours or holidays change (app() makes a new one each time).
 */
class SlaClock
{
    /** The warning is sent at this share of the target (blueprint 7.1). */
    public const WARN_PERCENT = 80;

    /** Used when a company has no business hours rows yet: the same defaults as the Business hours page. */
    private const DEFAULT_HOURS = [
        1 => ['09:00:00', '18:00:00'], 2 => ['09:00:00', '18:00:00'], 3 => ['09:00:00', '18:00:00'], 4 => ['09:00:00', '18:00:00'],
        5 => ['09:00:00', '18:00:00'], 6 => ['09:00:00', '13:00:00'], 0 => null,
    ];

    /** Days looked at before giving up on a company that has no open day at all (then every minute counts). */
    private const HORIZON_DAYS = 800;

    /** @var array<int,array> */
    private array $calendars = [];

    /* ---------- calendar ---------- */

    /** The company's week, holidays and time zone. */
    public function calendar(int $tenantId): array
    {
        if (isset($this->calendars[$tenantId])) {
            return $this->calendars[$tenantId];
        }

        $tz = Tenant::query()->whereKey($tenantId)->value('timezone') ?: config('app.timezone');

        $rows = TenantBusinessHour::withoutGlobalScopes()->where('tenant_id', $tenantId)->get()->keyBy('weekday');
        $week = [];
        foreach (self::DEFAULT_HOURS as $weekday => $default) {
            if ($rows->isEmpty()) {
                $week[$weekday] = $default;
                continue;
            }
            $r = $rows->get($weekday);
            $week[$weekday] = ($r && $r->is_open && $r->opens_at && $r->closes_at && $r->opens_at < $r->closes_at) ? [$r->opens_at, $r->closes_at] : null;
        }

        $holidays = TenantHoliday::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('holiday_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->flip()->all();

        return $this->calendars[$tenantId] = [
            'tz' => $tz, 'week' => $week, 'holidays' => $holidays, 'has_open_day' => collect($week)->filter()->isNotEmpty(),
        ];
    }

    /** The opening and closing instants of the day of $t in the company's zone, or null when the company is closed that day. */
    private function window(Carbon $t, array $cal): ?array
    {
        $date = $t->toDateString();
        if (isset($cal['holidays'][$date])) {
            return null;
        }
        $hours = $cal['week'][(int) $t->dayOfWeek] ?? null;
        if (! $hours) {
            return null;
        }

        return [Carbon::parse($date . ' ' . $hours[0], $cal['tz']), Carbon::parse($date . ' ' . $hours[1], $cal['tz'])];
    }

    /* ---------- arithmetic ---------- */

    // The results keep the zone they were worked out in: a Carbon is converted to the application zone when it is saved (Models\Concerns\AppTimezoneDates).

    /** $from plus $minutes of the clock's own time (open minutes when $business, else every minute). Returns the instant. */
    public function add(Carbon $from, float|int $minutes, bool $business, int $tenantId): Carbon
    {
        $seconds = (int) round($minutes * 60);
        $cal = $this->calendar($tenantId);

        if (! $business || ! $cal['has_open_day'] || $seconds <= 0) {
            return $from->copy()->addSeconds(max(0, $seconds));
        }

        $t = $from->copy()->setTimezone($cal['tz']);
        for ($i = 0; $i < self::HORIZON_DAYS; $i++) {
            if ($w = $this->window($t, $cal)) {
                $start = $t->greaterThan($w[0]) ? $t : $w[0];
                if ($start->lessThan($w[1])) {
                    $avail = $w[1]->timestamp - $start->timestamp;
                    if ($seconds <= $avail) {
                        return $start->copy()->addSeconds($seconds);
                    }
                    $seconds -= $avail;
                }
            }
            $t = $t->copy()->startOfDay()->addDay();
        }

        return $from->copy()->addSeconds($seconds);
    }

    /** $to minus $minutes of the clock's own time. The mirror of add(). */
    public function subtract(Carbon $to, float|int $minutes, bool $business, int $tenantId): Carbon
    {
        $seconds = (int) round($minutes * 60);
        $cal = $this->calendar($tenantId);

        if (! $business || ! $cal['has_open_day'] || $seconds <= 0) {
            return $to->copy()->subSeconds(max(0, $seconds));
        }

        $t = $to->copy()->setTimezone($cal['tz']);
        for ($i = 0; $i < self::HORIZON_DAYS; $i++) {
            if ($w = $this->window($t, $cal)) {
                $end = $t->lessThan($w[1]) ? $t : $w[1];
                if ($end->greaterThan($w[0])) {
                    $avail = $end->timestamp - $w[0]->timestamp;
                    if ($seconds <= $avail) {
                        return $end->copy()->subSeconds($seconds);
                    }
                    $seconds -= $avail;
                }
            }
            $t = $t->copy()->startOfDay()->subSecond();
        }

        return $to->copy()->subSeconds($seconds);
    }

    /** Minutes of the clock's own time between two instants (0 when $to is not after $from). */
    public function between(Carbon $from, Carbon $to, bool $business, int $tenantId): float
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0.0;
        }
        $cal = $this->calendar($tenantId);
        if (! $business || ! $cal['has_open_day']) {
            return ($to->timestamp - $from->timestamp) / 60;
        }

        $seconds = 0;
        $t = $from->copy()->setTimezone($cal['tz']);
        $last = $to->copy()->setTimezone($cal['tz'])->startOfDay();
        for ($i = 0; $i < self::HORIZON_DAYS && $t->copy()->startOfDay()->lessThanOrEqualTo($last); $i++) {
            if ($w = $this->window($t, $cal)) {
                $a = $t->greaterThan($w[0]) ? $t->timestamp : $w[0]->timestamp;
                $b = min($to->timestamp, $w[1]->timestamp);
                if ($b > $a) {
                    $seconds += $b - $a;
                }
            }
            $t = $t->copy()->startOfDay()->addDay();
        }

        return $seconds / 60;
    }

    /** The instant of the warning: the due time minus the last (100 - WARN_PERCENT) percent of the target. */
    public function warnAt(Carbon $due, int $targetMinutes, bool $business, int $tenantId): Carbon
    {
        return $this->subtract($due, $targetMinutes * (100 - self::WARN_PERCENT) / 100, $business, $tenantId);
    }

    /* ---------- one request ---------- */

    /**
     * Response and resolution targets and the policy's business hours flag.
     * A contract's targets replace the policy's (ServiceRequest::slaMinutes); the flag always comes from the policy.
     *
     * @return array{0:?int,1:?int,2:bool}
     */
    public function targets(ServiceRequest $sr): array
    {
        [$resp, $reso] = $sr->slaMinutes();

        return [$resp, $reso, (bool) ($sr->resolveSlaPolicy()?->uses_business_hours)];
    }

    /**
     * The two due times for a clock that started at $started. The minutes already paused (sla_paused_minutes) are added to the target.
     *
     * @return array{sla_response_due_at:?Carbon,sla_resolution_due_at:?Carbon}
     */
    public function dueTimes(ServiceRequest $sr, Carbon $started): array
    {
        [$resp, $reso, $business] = $this->targets($sr);
        $paused = (int) ($sr->sla_paused_minutes ?? 0);
        $tid = (int) $sr->tenant_id;

        return [
            'sla_response_due_at'   => $resp !== null ? $this->add($started, $resp + $paused, $business, $tid) : null,
            'sla_resolution_due_at' => $reso !== null ? $this->add($started, $reso + $paused, $business, $tid) : null,
        ];
    }

    /**
     * Blueprint 7.1: the clock starts at approval. A request that is created already approved, or raised as Emergency, starts it at creation;
     * every other request waits for RequestApprover (or the quote acceptance) to start it.
     */
    public function startAtCreation(ServiceRequest $sr): void
    {
        if ($sr->priority === 'emergency' || $sr->status === 'approved') {
            $this->start($sr);
        }
    }

    /** A path that approves a request without RequestApprover (a quote accepted, the trades of an approved master) starts the clock unless it already runs. */
    public function startOnApproval(ServiceRequest $sr): void
    {
        if (! $sr->sla_clock_started_at) {
            $this->start($sr);
        }
    }

    /** Starts the clock now (or at $started) and stores both due times. */
    public function start(ServiceRequest $sr, ?Carbon $started = null): void
    {
        $started ??= now();
        $sr->update(['sla_clock_started_at' => $started] + $this->dueTimes($sr, $started));
    }

    /**
     * Works the due times out again from the clock start, with the request's current policy, contract, hours and holidays.
     * A paused request is left alone (resume moves its due times). Returns true when a due time changed.
     */
    public function recalculate(ServiceRequest $sr): bool
    {
        if (! $sr->sla_clock_started_at || $sr->sla_paused_at) {
            return false;
        }
        $due = $this->dueTimes($sr, $sr->sla_clock_started_at);
        $changed = collect($due)->contains(fn ($v, $k) => ($v?->timestamp) !== ($sr->{$k}?->timestamp));
        if ($changed) {
            $sr->update($due);
        }

        return $changed;
    }

    /** A hold whose reason pauses the SLA starts here. Does nothing when the clock is not running or is already paused. */
    public function pause(ServiceRequest $sr, ?Carbon $at = null): void
    {
        if (! $sr->sla_clock_started_at || $sr->sla_paused_at) {
            return;
        }
        $sr->update(['sla_paused_at' => $at ?? now()]);
    }

    /**
     * The hold ended. Each due time that was still ahead at the start of the pause moves out so the same time is left as when it paused.
     * The pause is added to sla_paused_minutes in the clock's own minutes.
     */
    public function resume(ServiceRequest $sr, ?Carbon $at = null): void
    {
        if (! $sr->sla_paused_at) {
            return;
        }
        $at ??= now();
        $from = $sr->sla_paused_at;
        [, , $business] = $this->targets($sr);
        $tid = (int) $sr->tenant_id;
        $changes = ['sla_paused_at' => null];
        $counted = 0.0;

        foreach (['sla_response_due_at', 'sla_resolution_due_at'] as $col) {
            $due = $sr->{$col};
            if ($due && $due->greaterThan($from)) {
                $left = $this->between($from, $due, $business, $tid);
                $changes[$col] = $this->add($at, $left, $business, $tid);
                $counted = max($counted, $this->between($from, $at, $business, $tid));
            }
        }
        $changes['sla_paused_minutes'] = (int) ($sr->sla_paused_minutes ?? 0) + (int) round($counted);

        $sr->update($changes);
    }

    /**
     * Recalculates every open request of a company that is on a business-hours policy, for example after the hours or the holidays
     * were saved. Returns how many requests changed.
     */
    public function recalculateOpen(int $tenantId): int
    {
        $changed = 0;

        ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereNotNull('sla_clock_started_at')->whereNull('sla_paused_at')
            ->whereNotIn('status', ['completed', 'cancelled', 'awaiting_invoice'])
            ->chunkById(200, function ($chunk) use (&$changed) {
                foreach ($chunk as $sr) {
                    [, , $business] = $this->targets($sr);
                    if ($business && $this->recalculate($sr)) {
                        $changed++;
                    }
                }
            });

        return $changed;
    }
}
