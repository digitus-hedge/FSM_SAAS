<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestExpense;
use App\Models\Tenant;

/** Asset 360 (AST-04): everything about one asset on one page. */
class Asset360Controller extends Controller
{
    /** Invoice statuses that count as "invoiced" (not draft, not waiting approval, not void or written off). */
    private const INVOICED = ['approved', 'sent', 'partially_paid', 'paid', 'overdue'];

    public function show(Asset $asset)
    {
        abort_unless(\App\Services\Permissions\PermissionLevel::canView(auth()->user(), 'asset.view'), 403);

        $asset->load(['client', 'site', 'category', 'parent', 'children']);

        // A request counts for the asset as its own asset or as a row of a batch (BAT-01).
        $requests = ServiceRequest::where(fn ($q) => $q->where('primary_asset_id', $asset->id)
                ->orWhereIn('id', \App\Models\ServiceRequestAsset::where('asset_id', $asset->id)->select('service_request_id')))
            ->orderByDesc('id')
            ->get(['id', 'sr_number', 'title', 'status', 'priority', 'created_at', 'completed_at', 'contract_id', 'coverage_type']);
        $srIds = $requests->pluck('id');

        // ---- Cost: approved expenses (what it cost) and invoiced amounts (what was billed) ----
        $expenses = ServiceRequestExpense::where('approval_status', 'approved')
            ->where(fn ($q) => $q->whereIn('service_request_id', $srIds)->orWhere('asset_id', $asset->id));
        $expenseTotal = (float) (clone $expenses)->sum('total_amount');
        $expenseCount = (clone $expenses)->count();
        $expenseBySr  = (clone $expenses)->selectRaw('service_request_id, SUM(total_amount) as t')
            ->groupBy('service_request_id')->pluck('t', 'service_request_id');

        $invoices = Invoice::whereIn('service_request_id', $srIds)->whereIn('status', self::INVOICED);
        $invoicedTotal = (float) (clone $invoices)->sum('base_total');
        $invoiceCount  = (clone $invoices)->count();
        $invoicedBySr  = (clone $invoices)->selectRaw('service_request_id, SUM(base_total) as t')
            ->groupBy('service_request_id')->pluck('t', 'service_request_id');

        // ---- Photos: the asset's own photo, plus before/after photos and image attachments on its requests ----
        $photos = Attachment::where(function ($q) use ($srIds, $asset) {
                $q->where(fn ($w) => $w->where('attachable_type', ServiceRequest::class)
                        ->whereIn('attachable_id', $srIds)
                        ->whereIn('kind', ['before_photo', 'after_photo', 'sr_attachment']))
                  ->orWhere(fn ($w) => $w->where('attachable_type', Asset::class)
                        ->where('attachable_id', $asset->id));
            })
            ->whereNotNull('storage_path')
            ->orderByDesc('id')
            ->limit(120)
            ->get()
            ->filter(fn ($a) => $a->is_image)
            ->values();


        // ---- Contracts (only when the plan has contracts and the user may view them) ----
        $contractsOn = (bool) Tenant::current()?->hasFeature('contracts')
            && \App\Services\Permissions\PermissionLevel::canView(auth()->user(), 'contract.view');
        $pinned = null;
        $covering = collect();
        $assetContracts = collect();
        $contractNumbers = collect();

        if ($contractsOn) {
            // Contracts of this client that cover this asset's site and category (none listed = all).
            $forAsset = fn () => Contract::query()
                ->where('client_id', $asset->client_id)
                ->where(fn ($q) => $q->whereDoesntHave('sites')
                    ->orWhereHas('sites', fn ($s) => $s->where('project_sites.id', $asset->project_site_id)))
                ->where(fn ($q) => $q->whereDoesntHave('categories')
                    ->orWhereHas('categories', fn ($c) => $c->where('service_categories.id', $asset->service_category_id)));

            // All contracts that apply to this asset, newest first (drafts included, cancelled last).
            $assetContracts = $forAsset()->orderByRaw("status = 'cancelled'")->orderByDesc('end_date')->orderByDesc('id')->get();

            // Contracts that would cover a request for this asset today (same rule as request coverage, 6.3).
            $covering = $assetContracts->filter(fn ($c) => in_array($c->status, ['active', 'expiring_soon'], true)
                && $c->start_date->lte(today()) && $c->end_date->gte(today()))
                ->sortBy([['end_date', 'asc'], ['id', 'asc']])->values();

            $pinned = $asset->default_contract_id
                ? Contract::withTrashed()->find($asset->default_contract_id)
                : null;

            $contractNumbers = Contract::withTrashed()
                ->whereIn('id', $requests->pluck('contract_id')->filter()->unique())
                ->pluck('contract_number', 'id');
        }

        // FRM-06: the checklists filled for this asset, newest first
        $checklists = collect();
        foreach (ServiceRequest::where('primary_asset_id', $asset->id)->whereNotNull('checklist_result')->get() as $sr) {
            $checklists->push(['at' => $sr->checklist_saved_at, 'result' => $sr->checklist_result, 'sr' => $sr,
                'version' => \App\Models\FormTemplateVersion::withoutGlobalScopes()->with('template')->find($sr->form_template_version_id)]);
        }
        foreach (\App\Models\ServiceRequestAsset::where('asset_id', $asset->id)->whereNotNull('checklist_result')->with('serviceRequest')->get() as $row) {
            $checklists->push(['at' => $row->completed_at ?? $row->serviceRequest?->updated_at, 'result' => $row->checklist_result, 'sr' => $row->serviceRequest,
                'version' => $row->version?->load('template')]);
        }

        // PMC: the schedules this asset is on, with their next due dates
        $schedules = \App\Models\PmSchedule::withoutGlobalScopes()->where('tenant_id', $asset->tenant_id)->whereIn('status', ['active', 'paused'])
            ->whereIn('id', \App\Models\PmScheduleAsset::withoutGlobalScopes()->where('asset_id', $asset->id)->select('pm_schedule_id'))->orderBy('next_due_date')->get();

        return view('admin.assets.show', [
            'pmSchedules'   => $schedules,
            'atWorkshop'    => app(\App\Services\Trades\Depot::class)->locationOf($asset),
            'checklists'    => $checklists->sortByDesc('at')->values(),
            'asset'         => $asset,
            'requests'      => $requests,
            'expenseTotal'  => $expenseTotal,
            'expenseCount'  => $expenseCount,
            'expenseBySr'   => $expenseBySr,
            'invoicedTotal' => $invoicedTotal,
            'invoiceCount'  => $invoiceCount,
            'invoicedBySr'  => $invoicedBySr,
            'photos'        => $photos,
            'srNumbers'     => $requests->pluck('sr_number', 'id'),
            'currency'      => auth()->user()->tenant?->currency_code ?? 'AED',
            'canManage'     => \App\Services\Permissions\PermissionLevel::canChange(auth()->user(), 'asset.manage'),
            'contractsOn'     => $contractsOn,
            'pinned'          => $pinned,
            'covering'        => $covering,
            'assetContracts'  => $assetContracts,
            'contractNumbers' => $contractNumbers,
        ]);
    }
}
