<?php

namespace Tests\Feature\Sla;

use App\Models\ServiceRequest;
use App\Models\SlaPolicy;
use App\Models\TenantBusinessHour;
use App\Models\TenantHoliday;
use App\Services\Sla\SlaClock;
use Carbon\Carbon;
use Tests\Feature\Forms\FormsTestCase;

/**
 * Shared set-up for the SLA tests (blueprint 7.1 "SLA clock", 9.2). The company is in Dubai (UTC+4, no daylight saving).
 * "Now" is Thursday 19 November 2026, 10:00 UTC = 14:00 in Dubai. Default hours: Monday to Friday 09:00-18:00, Saturday 09:00-13:00, Sunday closed.
 */
abstract class SlaTestCase extends FormsTestCase
{
    protected function policy(int $response, int $resolution, bool $business = false, string $priority = 'medium'): SlaPolicy
    {
        return SlaPolicy::withoutGlobalScopes()->create([
            'tenant_id' => $this->t->id, 'service_category_id' => null, 'priority' => $priority,
            'response_minutes' => $response, 'resolution_minutes' => $resolution, 'uses_business_hours' => $business,
        ]);
    }

    protected function clock(): SlaClock
    {
        return new SlaClock();      // a new instance for each call: the calendar is read once for each instance
    }

    protected function holiday(string $date, string $name = 'Holiday'): TenantHoliday
    {
        return TenantHoliday::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'holiday_date' => $date, 'name' => $name]);
    }

    /** A closed weekday (0 = Sunday ... 6 = Saturday), for a company that has its own hours rows. */
    protected function weekHours(array $open): void
    {
        foreach (range(0, 6) as $wd) {
            $hours = $open[$wd] ?? null;
            TenantBusinessHour::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->t->id, 'weekday' => $wd], [
                'is_open' => (bool) $hours, 'opens_at' => $hours[0] ?? null, 'closes_at' => $hours[1] ?? null,
            ]);
        }
    }

    /** A UTC instant from a Dubai wall-clock time. */
    protected function dubai(string $time): Carbon
    {
        return Carbon::parse($time, 'Asia/Dubai')->utc();
    }

    protected function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
    }

    /** A request through the form, approved by the Operations Manager: that is when the clock starts (blueprint 7.1). */
    protected function openRequest(array $over = [], string $priority = 'medium'): ServiceRequest
    {
        $sr = $this->raise($this->makeCategory(), ['priority' => $priority]);
        app(\App\Services\Approval\RequestApprover::class)->complete($sr, 'out_of_coverage', null, null, false, $this->u['operations_manager']->id);
        $sr = $sr->fresh();
        if ($over) {
            $sr->forceFill($over)->save();
        }

        return $sr->fresh();
    }

    protected function notified(string $event, $user): int
    {
        return \App\Models\UserNotification::where('user_id', $user->id)->where('event_key', $event)->count();
    }

    protected function audits(string $action): int
    {
        return \Illuminate\Support\Facades\DB::table('activity_logs')->where('tenant_id', $this->t->id)->where('action', $action)->count();
    }
}
