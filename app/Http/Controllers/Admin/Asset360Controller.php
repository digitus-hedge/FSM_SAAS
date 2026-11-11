<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestExpense;

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
            ->get(['id', 'sr_number', 'title', 'status', 'priority', 'created_at', 'completed_at']);
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
        ]);
    }
}