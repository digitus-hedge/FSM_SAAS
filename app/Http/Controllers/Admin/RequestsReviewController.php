<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class RequestsReviewController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->canDo('sr.qc'), 403);
    }

    public function index(Request $request)
    {
        $this->guard();
        $tab = $request->input('tab', 'waiting');

        $base = fn() => ServiceRequest::query()
            ->with(['site:id,name,address', 'client:id,name'])
            ->withExists(['quotes as has_draft_quote' => fn($q) => $q->where('status', 'draft')])
            ->orderByDesc('id');

        $items = match ($tab) {
            'invoice' => $base()->where('status', 'awaiting_invoice')->get(),
            'all'     => $base()->get(),
            default   => $base()->whereIn('status', ['pending', 'in_progress', 'pending_review', 'approved'])
                ->whereDoesntHave('quotes', fn($q) => $q->whereIn('status', ['sent', 'accepted']))  
                ->get(),
        };

        $counts = [
            'waiting' => ServiceRequest::whereIn('status', ['pending', 'in_progress', 'pending_review', 'approved'])
                ->whereDoesntHave('quotes', fn($q) => $q->whereIn('status', ['sent', 'accepted']))  
                ->count(),
            'invoice' => ServiceRequest::where('status', 'awaiting_invoice')->count(),
            'all'     => ServiceRequest::count(),
        ];
        return view('admin.requests-review.index', compact('items', 'tab', 'counts'));
    }

    public function show(ServiceRequest $sr)
{
    $this->guard();
    $sr->load(['client', 'site', 'quotes', 'attachmentsRel']);

    $latestQuote = $sr->quotes->sortByDesc('id')->first();
    $draft       = $sr->quotes->where('status', 'draft')->sortByDesc('id')->first();

    $latestInvoice = \App\Models\Invoice::where('service_request_id', $sr->id)
        ->orderByDesc('id')
        ->first();

    $beforePhotos = $sr->attachmentsRel->where('kind', 'before_photo');
    $afterPhotos  = $sr->attachmentsRel->where('kind', 'after_photo');
    $signatureAtt = $sr->customer_signature_attachment_id
        ? $sr->attachmentsRel->firstWhere('id', $sr->customer_signature_attachment_id)
        : null;

    $notes = \App\Models\SrNote::where('service_request_id', $sr->id)
        ->orderByDesc('created_at')
        ->with('user:id,name')
        ->get();

    return view('admin.requests-review.show', compact(
        'sr', 'latestQuote', 'draft', 'latestInvoice',
        'beforePhotos', 'afterPhotos', 'signatureAtt', 'notes'
    ));
}

   public function qcPass(ServiceRequest $sr)
{
    $this->guard();
    abort_unless($sr->status === 'pending_review', 422, 'QC can only pass a job that is in Pending Review.');

    // Blueprint 7.1: Awaiting Invoice only when BOTH out-of-coverage AND the
    // category is invoice-mandatory. Everything else (any covered job, or an
    // out-of-coverage job in a non-invoice-mandatory category) completes here.
    $needsInvoice = $sr->coverage_type === 'out_of_coverage' && (bool) $sr->invoice_mandatory;

    if ($needsInvoice) {
        $sr->update([
            'status'          => 'awaiting_invoice',
            'billing_status'  => 'pending_invoice',
            'qc_reviewed_at'  => now(),
        ]);
        \App\Models\SrStatusHistory::log($sr, 'pending_review', 'awaiting_invoice', 'user', auth()->id());
        return redirect()
            ->route('admin.requests-review.show', $sr)
            ->with('success', 'QC passed. The job now waits for an invoice.');
    }

    $sr->update([
        'status'          => 'completed',
        'billing_status'  => 'not_applicable',
        'qc_reviewed_at'  => now(),
        'completed_at'    => now(),
    ]);
    \App\Models\SrStatusHistory::log($sr, 'pending_review', 'completed', 'user', auth()->id(), null, ['coverage' => $sr->coverage_type, 'invoice_mandatory' => $sr->invoice_mandatory]);
    return redirect()
        ->route('admin.requests-review.show', $sr)
        ->with('success', $sr->coverage_type === 'out_of_coverage'
            ? 'QC passed. No invoice required for this category — job completed.'
            : 'QC passed. Covered under ' . str_replace('_', ' ', $sr->coverage_type) . ' — job completed, no invoice needed.');
}

public function qcFail(Request $request, ServiceRequest $sr)
{
    $this->guard();
    abort_unless($sr->status === 'pending_review', 422, 'QC can only fail a job that is in Pending Review.');

    $data = $request->validate([
        'reason' => 'required|string|min:3|max:1000',
    ], [
        'reason.required' => 'Please give a reason for failing QC.',
        'reason.min'      => 'Please give at least 3 characters.',
    ]);

    $sr->update([
        'status'           => 'in_progress',
        'qc_reviewed_at'   => now(),
        'qc_rework_reason' => $data['reason'],
        'qc_rework_count'  => $sr->qc_rework_count + 1,
    ]);
    \App\Models\SrStatusHistory::log($sr, 'pending_review', 'in_progress', 'user', auth()->id(), $data['reason']);
    return redirect()
        ->route('admin.requests-review.show', $sr)
        ->with('success', 'QC failed — sent back to the technician for rework.');
}
    public function cancel(Request $request, ServiceRequest $sr)
{
    $this->guard();

    $data = $request->validate([
        'reason' => 'required|string|min:3|max:500',
    ], [
        'reason.required' => 'Please give a reason for cancelling this request.',
        'reason.min'      => 'Please give at least 3 characters.',
    ]);

    $sr->update([
        'status'               => 'cancelled',
        'billing_status'       => 'not_applicable',
        'cancellation_reason'  => $data['reason'],
        'cancelled_at'         => now(),
        'cancelled_by_user_id' => auth()->id(),
    ]);
    \App\Models\SrStatusHistory::log($sr, $sr->getOriginal('status'), 'cancelled', 'user', auth()->id(), $data['reason']);
    return redirect()
        ->route('admin.requests-review.index')
        ->with('success', 'Request cancelled.');
}
}
