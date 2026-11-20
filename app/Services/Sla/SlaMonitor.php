<?php

namespace App\Services\Sla;

use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The SLA check (blueprint 7.1 "SLA clock", 9.2 sla.warning and sla.breached). Run each minute by `sla:check`.
 *
 * For each open request and each target (response, resolution) it sends:
 * - sla.warning once, when 80 percent of the target is used (SlaClock::warnAt, which counts business hours and pauses);
 * - sla.breached once, when the due time has passed.
 * "Once" is the flag column on the request (sla_*_warned_at, sla_*_breached), set with an update that only matches while the flag
 * is still empty, so two overlapping runs cannot both send. The response target stops at the first punch-in, the resolution
 * target at the QC pass (a request that was completed, awaits an invoice, or was ever reopened is not looked at: the result is fixed).
 *
 * No flood: a message goes out only when the warning or due point passed within the last NOTIFY_WINDOW_MINUTES. An older point
 * (a first run on old data, or a long outage) sets the flag and writes the audit row but sends nothing.
 *
 * Reads and writes with explicit company ids; the company is made "current" while it runs (contract coverage and the user scope need it).
 */
class SlaMonitor
{
    public const NOTIFY_WINDOW_MINUTES = 120;

    /** Statuses where no SLA is watched: before the clock (quoted, the client is deciding) and after the QC pass. */
    private const SKIPPED = ['quoted', 'completed', 'cancelled', 'awaiting_invoice'];

    private SlaClock $clock;

    /** @var array<string,array> targets by category, priority, contract and coverage */
    private array $targetCache = [];

    public function __construct(?SlaClock $clock = null)
    {
        $this->clock = $clock ?? new SlaClock();
    }

    /** Returns the counts for the command output. */
    public function runForTenant(Tenant $tenant, ?Carbon $now = null): array
    {
        $now ??= now();
        $out = ['checked' => 0, 'warnings' => 0, 'breaches' => 0, 'silent' => 0];
        $this->targetCache = [];
        $previous = Tenant::current();
        Tenant::setCurrent($tenant);

        try {
            ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->whereNotNull('sla_clock_started_at')->whereNull('sla_paused_at')
                ->whereNotIn('status', self::SKIPPED)
                ->where(fn ($q) => $q->whereNull('is_master_sr')->orWhere('is_master_sr', false))     // a trade has its own clock
                ->whereNull('completed_at')
                ->where(fn ($q) => $q->whereNull('reopened_count')->orWhere('reopened_count', 0))
                ->where(function ($q) {
                    $q->where(fn ($r) => $r->whereNotNull('sla_resolution_due_at')->where('sla_resolution_breached', false))
                      ->orWhere(fn ($r) => $r->whereNotNull('sla_response_due_at')->whereNull('first_punch_in_at')->where('sla_response_breached', false));
                })
                ->orderBy('id')
                ->chunkById(200, function ($chunk) use ($tenant, $now, &$out) {
                    foreach ($chunk as $sr) {
                        $out['checked']++;
                        foreach (['response', 'resolution'] as $kind) {
                            $this->check($tenant, $sr, $kind, $now, $out);
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::error('SLA check failed', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            Tenant::setCurrent($previous);
        }

        return $out;
    }

    private function check(Tenant $tenant, ServiceRequest $sr, string $kind, Carbon $now, array &$out): void
    {
        $due = $sr->{"sla_{$kind}_due_at"};
        if (! $due) {
            return;
        }
        if ($kind === 'response' && $sr->first_punch_in_at) {
            return;                                                  // the response SLA stops at the first punch-in
        }

        $warnedCol = "sla_{$kind}_warned_at";
        $breachCol = "sla_{$kind}_breached";
        [$target, $business] = $this->target($sr, $kind);

        // breach: the due time has passed
        if (! $sr->{$breachCol}) {
            if ($now->greaterThan($due)) {
                $this->breach($tenant, $sr, $kind, $due, $now, $out);
            } elseif ($target && ! $sr->{$warnedCol}) {
                $warnAt = $this->clock->warnAt($due, $target, $business, (int) $tenant->id);
                if ($now->greaterThanOrEqualTo($warnAt)) {
                    $this->warn($tenant, $sr, $kind, $due, $warnAt, $now, $out);
                }
            }
        }
    }

    private function breach(Tenant $tenant, ServiceRequest $sr, string $kind, Carbon $due, Carbon $now, array &$out): void
    {
        // the warning is moot once the target is missed: it is marked as handled and never sent afterwards
        $claimed = DB::table('service_requests')->where('id', $sr->id)->where("sla_{$kind}_breached", false)
            ->update(["sla_{$kind}_breached" => true, "sla_{$kind}_warned_at" => $sr->{"sla_{$kind}_warned_at"} ?? $now, 'updated_at' => now()]);
        if (! $claimed) {
            return;                                                  // another run took it
        }

        $send = $due->diffInMinutes($now) <= self::NOTIFY_WINDOW_MINUTES;
        if ($send) {
            $svc = app(NotificationService::class);
            // blueprint 9.2: Ops Manager and Company Admin
            $svc->notify('sla.breached', $svc->usersWithRole('operations_manager', $tenant->id)->merge($svc->usersWithRole('company_admin', $tenant->id)), [
                'sr_number' => $sr->sr_number, 'client' => $sr->client?->name ?? '', 'kind' => $kind,
            ], route('service-requests.show', $sr, false), ['service_request_id' => $sr->id, 'kind' => $kind]);
            $out['breaches']++;
        } else {
            $out['silent']++;
        }

        $this->audit($tenant, $sr, 'sla.breached', $kind, $due, $send);
    }

    private function warn(Tenant $tenant, ServiceRequest $sr, string $kind, Carbon $due, Carbon $warnAt, Carbon $now, array &$out): void
    {
        $claimed = DB::table('service_requests')->where('id', $sr->id)->whereNull("sla_{$kind}_warned_at")
            ->update(["sla_{$kind}_warned_at" => $now, 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }

        $send = $warnAt->diffInMinutes($now) <= self::NOTIFY_WINDOW_MINUTES;
        if ($send) {
            $svc = app(NotificationService::class);
            // blueprint 9.2: Ops Manager and Team Lead (the request's own Team Lead)
            $to = $svc->usersWithRole('operations_manager', $tenant->id);
            if ($sr->team_lead_user_id && ($lead = \App\Models\User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->find($sr->team_lead_user_id))) {
                $to = $to->push($lead);
            }
            $tz = $tenant->timezone ?: config('app.timezone');
            $svc->notify('sla.warning', $to, [
                'sr_number' => $sr->sr_number, 'client' => $sr->client?->name ?? '', 'kind' => $kind, 'percent' => SlaClock::WARN_PERCENT,
                'due' => $due->copy()->setTimezone($tz)->format('d M Y H:i'),
            ], route('service-requests.show', $sr, false), ['service_request_id' => $sr->id, 'kind' => $kind]);
            $out['warnings']++;
        } else {
            $out['silent']++;
        }

        $this->audit($tenant, $sr, 'sla.warning', $kind, $due, $send);
    }

    /** [target minutes, business hours flag] for a request, worked out once for each kind of request. */
    private function target(ServiceRequest $sr, string $kind): array
    {
        $key = implode('|', [$sr->service_category_id, $sr->priority, $sr->contract_id, $sr->coverage_type]);
        $this->targetCache[$key] ??= $this->clock->targets($sr);
        [$resp, $reso, $business] = $this->targetCache[$key];

        return [$kind === 'response' ? $resp : $reso, $business];
    }

    private function audit(Tenant $tenant, ServiceRequest $sr, string $action, string $kind, Carbon $due, bool $sent): void
    {
        try {
            app(AuditLogger::class)->log($action, [
                'tenant_id' => $tenant->id, 'item_type' => ServiceRequest::class, 'item_id' => $sr->id,
                'new' => ['sr_number' => $sr->sr_number, 'kind' => $kind, 'due_at' => $due->toIso8601String(), 'notified' => $sent]
                    + ($sent ? [] : ['reason' => 'older than ' . self::NOTIFY_WINDOW_MINUTES . ' minutes, flag set and nothing sent']),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SLA audit failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
