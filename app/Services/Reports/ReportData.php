<?php

namespace App\Services\Reports;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The data of the four reports (Reports-Summaries-Preferences spec D2): Monthly SLA, Technician scorecard, Completion certificate and
 * Contract performance. Plain arrays, so the screen, the PDF and the Excel file show the same numbers.
 */
class ReportData
{
    public function __construct(private Figures $figures) {}

    /** The local first and last day of a month given as "2026-11". */
    public function month(string $month): array
    {
        $from = Carbon::parse($month . '-01')->startOfMonth();

        return [$from, $from->copy()->endOfMonth()->startOfDay()];
    }

    private function utc(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        $tz = $tenant->timezone ?: config('app.timezone');

        return [Carbon::parse($from->toDateString(), $tz)->utc(), Carbon::parse($to->toDateString(), $tz)->addDay()->utc()];
    }

    /** Requests of the month by priority: response and resolution against target, breaches. */
    public function sla(Tenant $tenant, string $month): array
    {
        [$from, $to] = $this->month($month);
        [$a, $b] = $this->utc($tenant, $from, $to);

        $rows = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_master_sr', false)->whereBetween('created_at', [$a, $b])->get();
        $out = [];
        foreach ($rows->groupBy('priority') as $priority => $group) {
            $withResp = $group->whereNotNull('sla_response_due_at');
            $respMet = $withResp->filter(fn ($r) => $r->first_punch_in_at && $r->first_punch_in_at->lte($r->sla_response_due_at))->count();
            $withReso = $group->whereNotNull('sla_resolution_due_at')->whereNotNull('completed_at');
            $resoMet = $withReso->filter(fn ($r) => $r->completed_at->lte($r->sla_resolution_due_at))->count();
            $out[] = [
                'priority' => $priority, 'requests' => $group->count(), 'completed' => $group->whereNotNull('completed_at')->count(),
                'response_met' => $respMet, 'response_total' => $withResp->count(),
                'resolution_met' => $resoMet, 'resolution_total' => $withReso->count(),
                'breaches' => $group->filter(fn ($r) => $r->sla_response_breached || $r->sla_resolution_breached)->count(),
            ];
        }

        return ['month' => $month, 'rows' => $out, 'totals' => [
            'requests' => $rows->count(), 'breaches' => collect($out)->sum('breaches'),
        ]];
    }

    /** One row for each technician. $onlyIds narrows it (a Team Lead sees their own team). */
    public function scorecard(Tenant $tenant, string $month, ?Collection $onlyIds = null): array
    {
        [$from, $to] = $this->month($month);
        [$a, $b] = $this->utc($tenant, $from, $to);

        $techs = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereHas('role', fn ($q) => $q->whereIn('code', ['field_technician', 'team_lead']))
            ->when($onlyIds, fn ($q) => $q->whereIn('id', $onlyIds))->orderBy('name')->get(['id', 'name']);

        $rows = [];
        foreach ($techs as $t) {
            $done = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('assigned_tech_user_id', $t->id)->whereBetween('completed_at', [$a, $b])->get();
            if ($done->isEmpty()) {
                continue;
            }
            $onTime = $done->filter(fn ($r) => ! $r->sla_resolution_due_at || $r->completed_at->lte($r->sla_resolution_due_at))->count();
            $fixed = $done->filter(fn ($r) => (int) $r->rework_count === 0 && (int) $r->qc_rework_count === 0 && (int) $r->reopened_count === 0)->count();
            $rows[] = [
                'technician' => $t->name, 'jobs' => $done->count(), 'on_time_percent' => round($onTime / $done->count() * 100, 1),
                'first_time_fix_percent' => round($fixed / $done->count() * 100, 1), 'avg_minutes' => (int) round($done->avg('total_work_minutes') ?? 0),
                'callbacks' => $done->where('reopened_count', '>', 0)->count(),
            ];
        }

        return ['month' => $month, 'rows' => $rows];
    }

    /** Active contracts with their use, requests and what was billed for them. */
    public function contractPerformance(Tenant $tenant, string $month, ?int $clientId = null): array
    {
        [$from, $to] = $this->month($month);
        [$a, $b] = $this->utc($tenant, $from, $to);

        $contracts = Contract::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('status', ['active', 'expiring_soon', 'expired', 'renewed'])
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))->with('client:id,name')->orderBy('contract_number')->get();

        $rows = [];
        foreach ($contracts as $c) {
            $reqIds = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('contract_id', $c->id)->whereBetween('created_at', [$a, $b])->pluck('id');
            $rows[] = [
                'contract' => $c->contract_number, 'client' => $c->client?->name, 'status' => $c->status,
                'calls_used' => (int) $c->calls_used_current_period, 'max_calls' => $c->max_calls, 'requests' => $reqIds->count(),
                'billed' => (float) Invoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('service_request_id', $reqIds)->whereNotIn('status', ['draft', 'void', 'pending_approval'])->sum('base_total'),
                'ends' => $c->end_date?->toDateString(),
            ];
        }

        return ['month' => $month, 'currency' => $tenant->base_currency, 'rows' => $rows];
    }

    /** One completed request: work, assets, checklist result and signature. No price (D21). */
    public function certificate(ServiceRequest $sr): array
    {
        $sr->loadMissing(['client:id,name', 'site:id,name,address', 'category:id,name', 'primaryAsset', 'assignedTech:id,name']);
        $assets = \App\Models\ServiceRequestAsset::withoutGlobalScopes()->where('service_request_id', $sr->id)->with('asset:id,name,asset_code')->get();

        return ['sr' => $sr, 'assets' => $assets, 'signed' => (bool) $sr->customer_signature_attachment_id, 'signer' => $sr->customer_signer_name];
    }
}
