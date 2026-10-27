<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class RequestsReviewController extends Controller
{
    private function guard(): void {
        abort_unless(auth()->user()?->role?->name === 'Company Admin', 403);
    }

    public function index(Request $request)
{
    $this->guard();
    $tab = $request->input('tab', 'waiting');

    $base = fn() => ServiceRequest::query()
        ->with(['site:id,name,address','client:id,name'])
        ->withExists(['quotes as has_draft_quote' => fn($q) => $q->where('status','draft')])
        ->orderByDesc('id');

    $items = match ($tab) {
        'invoice' => $base()->where('billing_status', 'awaiting_invoice')->get(),
        'all'     => $base()->get(),
        default   => $base()->whereIn('billing_status', ['pending_quote','not_applicable'])
                            ->whereIn('status', ['pending','in_progress','pending_review','approved'])->get(),
    };

    $counts = [
        'waiting' => ServiceRequest::whereIn('billing_status', ['pending_quote','not_applicable'])
                        ->whereIn('status', ['pending','in_progress','pending_review','approved'])->count(),
        'invoice' => ServiceRequest::where('billing_status', 'awaiting_invoice')->count(),
        'all'     => ServiceRequest::count(),
    ];

    return view('admin.requests-review.index', compact('items','tab','counts'));
}
}