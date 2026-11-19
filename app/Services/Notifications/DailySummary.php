<?php

namespace App\Services\Notifications;

use App\Models\DailySummaryConfig;
use App\Models\DailySummaryRun;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTimeOff;
use Carbon\Carbon;

/**
 * The daily summary message (Reports-Summaries-Preferences spec SUM-01 to SUM-03, D9 to D11). One summary for each user and day,
 * by email and in-app through NotificationService, nothing sent when every block is empty.
 */
class DailySummary
{
    public const ROLES = ['operations_manager', 'finance', 'field_technician', 'team_lead'];

    public const BLOCKS = [
        'operations_manager' => ['today_requests' => 'Requests scheduled today', 'unassigned' => 'Approved but not assigned', 'overdue' => 'Overdue requests', 'sla_at_risk' => 'SLA due within 4 hours', 'technicians_off' => 'Technicians off today'],
        'finance'            => ['invoices_due' => 'Invoices due today', 'invoices_overdue' => 'Overdue invoices', 'payments_yesterday' => 'Payments received yesterday', 'quotes_waiting' => 'Quotes waiting for an answer'],
        'field_technician'   => ['todays_jobs' => 'Your jobs today'],
        'team_lead'          => ['todays_jobs' => 'Your team\'s jobs today'],
    ];

    /** The settings of a role: the saved row, or the defaults (active, 07:00, Monday to Friday, every block). */
    public function config(int $tenantId, string $role): array
    {
        $row = DailySummaryConfig::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('base_role', $role)->first();

        return [
            'send_time' => substr((string) ($row?->send_time ?? '07:00:00'), 0, 5),
            'days'      => $row?->days_of_week_json ?? [1, 2, 3, 4, 5],
            'blocks'    => $row?->content_blocks_json ?? array_keys(self::BLOCKS[$role]),
            'active'    => $row ? (bool) $row->is_active : true,
        ];
    }

    /** @return array{sent:int, empty:int} for one company at its local time */
    public function runForTenant(Tenant $tenant, Carbon $nowLocal): array
    {
        $out = ['sent' => 0, 'empty' => 0];
        $today = Carbon::parse($nowLocal->toDateString());
        $notify = app(NotificationService::class);

        foreach (self::ROLES as $role) {
            $cfg = $this->config($tenant->id, $role);
            if (! $cfg['active'] || (int) substr($cfg['send_time'], 0, 2) !== (int) $nowLocal->format('G') || ! in_array((int) $nowLocal->isoWeekday(), $cfg['days'], true)) {
                continue;
            }

            $users = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deactivated_at')->where('daily_summary_on', true)
                ->whereHas('role', fn ($q) => $q->where('code', $role))->get();

            foreach ($users as $user) {
                // one summary for each user and day, even when the job runs again
                $run = DailySummaryRun::withoutGlobalScopes()->firstOrCreate(
                    ['user_id' => $user->id, 'run_date' => $today->toDateString()], ['tenant_id' => $tenant->id, 'blocks_count' => 0]
                );
                if (! $run->wasRecentlyCreated) {
                    continue;
                }

                $lines = $this->lines($tenant, $user, $role, $cfg['blocks'], $today);
                if (! $lines) {
                    $out['empty']++;
                    continue;
                }
                $run->forceFill(['sent_at' => now(), 'blocks_count' => count($lines)])->save();
                $notify->notify('summary.daily', [$user], ['date' => $today->format('j M Y'), 'summary' => implode("\n", $lines)], url('/'));
                $out['sent']++;
            }
        }

        return $out;
    }

    /** The lines of one summary: only blocks that have something to say. @return string[] */
    public function lines(Tenant $tenant, User $user, string $role, array $blocks, Carbon $today): array
    {
        $tid = $tenant->id;
        $tz  = $tenant->timezone ?: config('app.timezone');
        $day0 = Carbon::parse($today->toDateString(), $tz)->utc();
        $day1 = $day0->copy()->addDay();
        $req = fn () => ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tid);
        $lines = [];
        $add = function (string $block, string $text) use (&$lines, $blocks) {
            if (in_array($block, $blocks, true)) {
                $lines[] = $text;
            }
        };

        if ($role === 'operations_manager') {
            if (($n = $req()->whereBetween('scheduled_eta_at', [$day0, $day1])->whereNotIn('status', ['completed', 'cancelled'])->count()) > 0) {
                $add('today_requests', "{$n} requests are scheduled today.");
            }
            if (($n = $req()->where('status', 'approved')->whereNull('assigned_tech_user_id')->whereNull('parent_sr_id')->where('is_master_sr', false)->count()) > 0) {
                $add('unassigned', "{$n} approved requests have no technician.");
            }
            if (($n = $req()->whereNotIn('status', ['completed', 'cancelled', 'pending_review', 'awaiting_invoice'])->whereNotNull('sla_resolution_due_at')->where('sla_resolution_due_at', '<', now())->count()) > 0) {
                $add('overdue', "{$n} requests are past their SLA.");
            }
            if (($n = $req()->whereNotIn('status', ['completed', 'cancelled'])->whereBetween('sla_resolution_due_at', [now(), now()->addHours(4)])->count()) > 0) {
                $add('sla_at_risk', "{$n} requests reach their SLA within 4 hours.");
            }
            if (($n = UserTimeOff::where('tenant_id', $tid)->where('starts_at', '<=', $day1)->where('ends_at', '>=', $day0)->distinct('user_id')->count('user_id')) > 0) {
                $add('technicians_off', "{$n} technicians are off today.");
            }
        } elseif ($role === 'finance') {
            if (($n = Invoice::withoutGlobalScopes()->where('tenant_id', $tid)->whereIn('status', ['sent', 'partially_paid'])->whereDate('due_date', $today->toDateString())->count()) > 0) {
                $add('invoices_due', "{$n} invoices are due today.");
            }
            if (($n = Invoice::withoutGlobalScopes()->where('tenant_id', $tid)->where('status', 'overdue')->count()) > 0) {
                $add('invoices_overdue', "{$n} invoices are overdue.");
            }
            $paid = Payment::withoutGlobalScopes()->where('tenant_id', $tid)->whereBetween('received_at', [$day0->copy()->subDay(), $day0])->sum('base_amount');
            if ($paid > 0) {
                $add('payments_yesterday', number_format((float) $paid, 2) . ' ' . $tenant->base_currency . ' was received yesterday.');
            }
            if (($n = Quote::withoutGlobalScopes()->where('tenant_id', $tid)->where('status', 'sent')->count()) > 0) {
                $add('quotes_waiting', "{$n} quotes are waiting for the client.");
            }
        } else {
            $mine = $req()->whereBetween('scheduled_eta_at', [$day0, $day1])->whereNotIn('status', ['completed', 'cancelled'])->orderBy('scheduled_eta_at');
            $role === 'team_lead'
                ? $mine->where(fn ($q) => $q->where('team_lead_user_id', $user->id)->orWhere('assigned_tech_user_id', $user->id))
                : $mine->where('assigned_tech_user_id', $user->id);
            foreach ($mine->with('site:id,name')->get() as $sr) {
                $add('todays_jobs', Carbon::parse($sr->scheduled_eta_at)->setTimezone($tz)->format('H:i') . " {$sr->sr_number} {$sr->title} at " . ($sr->site?->name ?? ''));
            }
        }

        return $lines;
    }
}
