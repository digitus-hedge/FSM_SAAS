<?php

namespace Tests\Feature\Dispatch;

use App\Models\ClientContact;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\UserTimeOff;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Forms\FormsTestCase;

/**
 * Shared set-up for the dispatch tests (Release B register DSP-01 to DSP-10). The company is in Dubai (UTC+4).
 * "Now" is Thursday 19 November 2026, 10:00 UTC (FormsTestCase). The default weights are 35, 25, 20, 10 and 10; a job counts 120 minutes.
 */
abstract class DispatchTestCase extends FormsTestCase
{
    protected function tech(string $name, array $over = []): User
    {
        static $n = 0;
        $n++;
        $u = $this->makeUser($this->t, 'field_technician', 'tech' . $n . '@fab.test');
        $u->forceFill(array_merge(['name' => $name], $over))->save();

        return $u->fresh();
    }

    protected function skill(User $u, $categoryId): void
    {
        DB::table('user_skills')->insert(['tenant_id' => $this->t->id, 'user_id' => $u->id, 'service_category_id' => $categoryId, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function timeOff(User $u, string $from, string $to, string $type = 'leave'): UserTimeOff
    {
        return UserTimeOff::create(['tenant_id' => $this->t->id, 'user_id' => $u->id, 'starts_at' => $from, 'ends_at' => $to, 'type' => $type]);
    }

    /** A request that is assigned to $tech at $eta (a Carbon or a string in UTC). */
    protected function assigned(?User $tech = null, $eta = null, string $priority = 'medium', ?int $categoryId = null): ServiceRequest
    {
        $cat = $categoryId ? \App\Models\ServiceCategory::withoutGlobalScopes()->find($categoryId) : $this->makeCategory();
        $sr = $this->raise($cat, ['priority' => $priority]);
        $sr->forceFill([
            'status' => 'assigned', 'assigned_tech_user_id' => ($tech ?? $this->u['field_technician'])->id,
            'scheduled_eta_at' => Carbon::parse($eta ?? '2026-11-20 06:00:00', 'UTC')->setTimezone(config('app.timezone')),
        ])->save();

        return $sr->fresh();
    }

    protected function contact(string $email, array $over = []): ClientContact
    {
        return ClientContact::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'name' => 'Contact ' . $email, 'email' => $email, 'is_active' => true, 'is_primary' => false,
        ], $over));
    }

    protected function suggestions(ServiceRequest $sr, ?string $arrival = null): array
    {
        return app(\App\Services\Dispatch\TechnicianSuggester::class)->suggest($sr->fresh()->load(['site', 'category']), $arrival ? Carbon::parse($arrival, 'UTC')->setTimezone(config('app.timezone')) : null);
    }

    protected function audits(string $action): int
    {
        return DB::table('activity_logs')->where('tenant_id', $this->t->id)->where('action', $action)->count();
    }

    protected function row(array $list, User $u): array
    {
        return collect($list)->firstWhere('id', $u->id) ?? $this->fail('The technician is not in the ranking.');
    }
}
