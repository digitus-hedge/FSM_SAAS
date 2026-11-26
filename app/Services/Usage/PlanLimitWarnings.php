<?php

namespace App\Services\Usage;

use App\Models\Tenant;
use App\Models\TenantUsageCounter;
use App\Services\Notifications\NotificationService;

/**
 * plan.limit_warning (blueprint 6 "At a plan limit", 7.9a "Limits", 9.2, decision 42): the Company Admin is told once at 80 percent and once
 * at 100 percent of each plan limit, for each limit and month, in-app and by email. Nothing is ever blocked.
 *
 * "Once for each limit and month" is the `warnings_json` of the month's counter row (TenantUsageCounter::hasWarned / markWarned).
 * If one check finds a limit already past both points, only the 100 percent message is sent and both points are marked.
 * Called by `usage:refresh` after it has recomputed the counters, so the figures are the ones the Usage page shows.
 */
class PlanLimitWarnings
{
    public const THRESHOLDS = [80, 100];

    /** @return array<string,array{label:string,unit:string,used:float|int,limit:?int}> */
    public function metrics(Tenant $tenant, TenantUsageCounter $counter): array
    {
        $plan = $tenant->plan;

        return [
            'users'    => ['label' => 'Users', 'unit' => 'users', 'used' => (int) $counter->user_count, 'limit' => $plan?->user_limit],
            'requests' => ['label' => 'Service requests this month', 'unit' => 'requests', 'used' => (int) $counter->requests_count, 'limit' => $plan?->requests_per_month_limit],
            'storage'  => ['label' => 'File storage', 'unit' => 'GB', 'used' => round($counter->storage_bytes / (1024 ** 3), 2), 'limit' => $plan?->storage_gb_limit],
        ];
    }

    /** Sends what is due. Returns the messages sent as [limit key => threshold]. */
    public function check(Tenant $tenant, TenantUsageCounter $counter): array
    {
        $sent   = [];
        $admins = null;

        foreach ($this->metrics($tenant, $counter) as $key => $m) {
            $percent = $counter->percentOf($m['limit'], $m['used']);
            if ($percent === null) {
                continue;                                              // no number on the plan: "No limit"
            }

            $crossed = array_values(array_filter(self::THRESHOLDS, fn ($t) => $percent >= $t && ! $counter->hasWarned($key, $t)));
            if (! $crossed) {
                continue;
            }

            $threshold = max($crossed);
            foreach ($crossed as $t) {
                $counter->markWarned($key, $t);                        // marked first: a second run can never send it again
            }

            $svc    = app(NotificationService::class);
            $admins ??= $svc->usersWithRole('company_admin', (int) $tenant->id);
            $svc->notify('plan.limit_warning', $admins, [
                'headline' => $threshold >= 100
                    ? "You have reached your plan limit for {$m['label']}"
                    : "{$m['label']} is at {$this->shown($percent)}% of your plan limit",
                'used'     => $this->shown($m['used']),
                'limit'    => (string) $m['limit'],
                'unit'     => $m['unit'],
                'period'   => now($tenant->timezone ?: config('app.timezone'))->format('F Y'),
                'advice'   => $threshold >= 100
                    ? 'Nothing is blocked and work in progress continues. Contact Aiywah to upgrade.'
                    : 'Nothing is blocked. Contact Aiywah if you need a higher limit.',
            ], \App\Support\TenantUrl::route($tenant, 'admin.usage.index'), ['limit' => $key, 'threshold' => $threshold, 'year' => (int) $counter->year, 'month' => (int) $counter->month]);

            $sent[$key] = $threshold;
        }

        return $sent;
    }

    private function shown(float|int $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }
}
