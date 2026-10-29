<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class RequestsReviewController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->role?->name === 'Company Admin', 403);
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
            'invoice' => $base()->where('billing_status', 'awaiting_invoice')->get(),
            'all'     => $base()->get(),
            default   => $base()->whereIn('billing_status', ['pending_quote', 'not_applicable'])
                ->whereIn('status', ['pending', 'in_progress', 'pending_review', 'approved'])->get(),
        };

        $counts = [
            'waiting' => ServiceRequest::whereIn('billing_status', ['pending_quote', 'not_applicable'])
                ->whereIn('status', ['pending', 'in_progress', 'pending_review', 'approved'])->count(),
            'invoice' => ServiceRequest::where('billing_status', 'awaiting_invoice')->count(),
            'all'     => ServiceRequest::count(),
        ];

        return view('admin.requests-review.index', compact('items', 'tab', 'counts'));
    }

    public function show(ServiceRequest $sr)
    {
        $this->guard();
        $sr->load(['client', 'site', 'quotes']);

        $latestQuote = $sr->quotes->sortByDesc('id')->first();
        $draft       = $sr->quotes->where('status', 'draft')->sortByDesc('id')->first();

           $latestInvoice = \App\Models\Invoice::where('service_request_id', $sr->id)
        ->orderByDesc('id')
        ->first();
        return view('admin.requests-review.show', compact('sr', 'latestQuote', 'draft','latestInvoice'));
    }


    public function qcPass(ServiceRequest $sr)
    {
        $this->guard();

        abort_unless($sr->status === 'pending_review', 422, 'QC can only pass a job that is in Pending Review.');

        $sr->update([
            'status'          => 'awaiting_invoice',             // or whatever your "work done" status is
            'billing_status'  => 'awaiting_invoice',
            'qc_reviewed_at'  => now(),
        ]);

        return redirect()
            ->route('admin.requests-review.show', $sr)
            ->with('success', 'QC passed. The job now waits for an invoice.');
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

    return redirect()
        ->route('admin.requests-review.index')
        ->with('success', 'Request cancelled.');
}
}
