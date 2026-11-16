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
        abort_unless(auth()->user()?->canDo('asset.view'), 403);

        $asset->load(['client', 'site', 'category', 'parent', 'children']);

        $requests = ServiceRequest::where('primary_asset_id', $asset->id)
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
            && (bool) auth()->user()->canDo('contract.view');
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

        return view('admin.assets.show', [
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
            'canManage'     => (bool) auth()->user()->canDo('asset.manage'),
            'contractsOn'     => $contractsOn,
            'pinned'          => $pinned,
            'covering'        => $covering,
            'assetContracts'  => $assetContracts,
            'contractNumbers' => $contractNumbers,
        ]);
    }
}
