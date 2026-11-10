<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\{Attachment, Client, ProjectSite, ServiceCategory, ServiceRequest, Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\{Quote, QuoteEvent, Invoice};

class ClientPortalController extends Controller
{
    /** Professional/Enterprise only — same gate as sign-in. */
    private function guardPlan(): Tenant
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);
        $planCode = $tenant->plan?->code;
        abort_unless(in_array($planCode, ['professional', 'enterprise'], true), 403);
        return $tenant;
    }

    /** SRs this user is allowed to see at all. */
    private function scopedRequests()
    {
        $user = auth()->user();
        $query = ServiceRequest::where('client_id', $user->client_id ?? 0);

        if ($user->role?->code === 'client_viewer') {
            $siteIds = $user->site_scope_json ?? [];
            $query->whereIn('project_site_id', $siteIds ?: [0]); // [0] = see nothing if unscoped
        }

        return $query;
    }

    /* ========== LIST ========== */

    public function requests(Request $request)
    {
        $this->guardPlan();
        $tab = $request->input('tab', 'open');

        $base = fn () => $this->scopedRequests()
            ->with(['site:id,name', 'category:id,name'])
            ->orderByDesc('id');

        $items = match ($tab) {
            'closed' => $base()->whereIn('status', ['completed', 'cancelled'])->paginate(25)->withQueryString(),
            'all'    => $base()->paginate(25)->withQueryString(),
            default  => $base()->whereNotIn('status', ['completed', 'cancelled'])->paginate(25)->withQueryString(),
        };

        return view('client.requests.index', compact('items', 'tab'));
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $this->guardPlan();
        abort_unless(
            $this->scopedRequests()->whereKey($serviceRequest->id)->exists(),
            404
        );

        $serviceRequest->load(['site:id,name,address', 'category:id,name']);

        // Client-safe field set only — never priority, coverage, internal notes,
        // expenses, GPS, hold/QC reasons or technician contact details.
        return view('client.requests.show', [
            'sr'       => $serviceRequest,
            'canEdit'  => $serviceRequest->status === 'pending'
                          && $serviceRequest->created_by_user_id === auth()->id(),
        ]);
    }

    /* ========== RAISE (Client Admin only) ========== */

    private function guardCanRaise(): void
    {
        abort_unless(auth()->user()?->role?->code === 'client_admin', 403);
    }

    public function raiseForm()
    {
        $this->guardPlan();
        $this->guardCanRaise();

        $user = auth()->user();
        $sites      = ProjectSite::where('client_id', $user->client_id)->orderBy('name')->get(['id', 'name']);
        $categories = ServiceCategory::active()->orderBy('name')->get(['id', 'name']);

        return view('client.requests.raise', compact('sites', 'categories'));
    }

    public function raiseStore(Request $request)
    {
        $tenant = $this->guardPlan();
        $this->guardCanRaise();

        $user = auth()->user();

        $data = $request->validate([
            'project_site_id'     => ['required', 'integer'],
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'description'         => ['required', 'string', 'min:3', 'max:2000'],
            'photos'               => ['nullable', 'array', 'max:10'],
            'photos.*'             => ['image', 'max:10240'],
        ], [
            'description.required' => 'Please describe the issue.',
            'photos.max'           => 'Up to 10 photos.',
        ]);

        // Site must actually belong to this client.
        $site = ProjectSite::where('client_id', $user->client_id)
            ->findOrFail($data['project_site_id']);

        $category = ServiceCategory::findOrFail($data['service_category_id']);

        // At most 30 requests a day for this client company.
        $todayCount = ServiceRequest::where('client_id', $user->client_id)
            ->whereDate('created_at', today())
            ->count();
        abort_if($todayCount >= 30, 422, "Daily request limit reached for {$tenant->name}. Please try again tomorrow or contact your account manager.");

        $sr = ServiceRequest::create([
            'tenant_id'            => $tenant->id,
            'sr_number'            => ServiceRequest::nextSrNumber($tenant->id),
            'client_id'            => $user->client_id,
            'project_site_id'      => $site->id,
            'service_category_id'  => $category->id,
            'source'               => 'client_portal',
            'priority'             => 'medium', // clients can never set priority
            'status'               => 'pending',
            'title'                => trim("{$category->name} — {$site->name}"),
            'description'          => $data['description'],
            'invoice_mandatory'    => (bool) ($category->invoice_mandatory ?? false),
            'proof_rules_snapshot' => [
                'require_before_photo' => (bool) ($category->require_before_photo ?? true),
                'require_after_photo'  => (bool) ($category->require_after_photo ?? true),
                'require_signature'    => (bool) ($category->require_signature ?? true),
                'require_checklist'    => (bool) ($category->require_checklist ?? false),
            ],
            'created_by_user_id'   => $user->id,
        ]);

        foreach ($request->file('photos', []) as $photo) {
            $path = $photo->store("sr/{$sr->id}/attachments", 'public');
            Attachment::create([
                'tenant_id'           => $tenant->id,
                'attachable_type'     => ServiceRequest::class,
                'attachable_id'       => $sr->id,
                'kind'                => 'sr_attachment',
                'storage_path'        => $path,
                'original_name'       => $photo->getClientOriginalName(),
                'mime_type'           => $photo->getMimeType(),
                'size_bytes'          => $photo->getSize(),
                'captured_at'         => now(),
                'uploaded_by_user_id' => $user->id,
                'client_event_uuid'   => (string) Str::uuid(),
            ]);
        }

        return redirect()->route('portal.requests.show', $sr)->with('success', 'Request raised — ' . $sr->sr_number);
    }

    /* ========== CANCEL / EDIT while Pending ========== */

    private function guardOwnPending(ServiceRequest $sr): void
    {
        abort_unless(
            $this->scopedRequests()->whereKey($sr->id)->exists()
            && $sr->status === 'pending'
            && $sr->created_by_user_id === auth()->id(),
            404
        );
    }

    public function cancel(ServiceRequest $serviceRequest)
    {
        $this->guardPlan();
        $this->guardCanRaise();
        $this->guardOwnPending($serviceRequest);

        $serviceRequest->update([
            'status'              => 'cancelled',
            'cancellation_reason' => 'Cancelled by client.',
        ]);

        return redirect()->route('portal.requests.index')->with('success', 'Request cancelled.');
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        $this->guardPlan();
        $this->guardCanRaise();
        $this->guardOwnPending($serviceRequest);

        $data = $request->validate([
            'description' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $serviceRequest->update(['description' => $data['description']]);

        return redirect()->route('portal.requests.show', $serviceRequest)->with('success', 'Updated.');
    }

    /* ========== QUOTES ========== */

private function scopedQuotes()
{
    $user = auth()->user();
    $query = Quote::where('client_id', $user->client_id ?? 0)
        ->whereIn('status', ['sent', 'accepted', 'rejected']); // never expose drafts

    if ($user->role?->code === 'client_viewer') {
        $siteIds = $user->site_scope_json ?? [];
        $query->whereHas('serviceRequest', fn ($q) => $q->whereIn('project_site_id', $siteIds ?: [0]));
    }

    return $query;
}

public function quotes(Request $request)
{
    $this->guardPlan();
    $items = $this->scopedQuotes()
        ->with(['serviceRequest:id,sr_number,title'])
        ->orderByDesc('sent_at')
        ->paginate(25);

    return view('client.quotes.index', compact('items'));
}

public function quoteShow(Quote $quote)
{
    $this->guardPlan();
    abort_unless($this->scopedQuotes()->whereKey($quote->id)->exists(), 404);

    $quote->load(['items.taxRate', 'serviceRequest', 'pdfAttachment']);

    return view('client.quotes.show', compact('quote'));
}

public function quoteAccept(Quote $quote)
{
    $this->guardPlan();
    $this->guardCanRaise(); // only Client Admin answers quotes
    abort_unless($this->scopedQuotes()->whereKey($quote->id)->exists(), 404);
    abort_unless($quote->status === 'sent', 422, 'This quote can no longer be accepted.');

    $user = auth()->user();

    $quote->update([
        'status'            => 'accepted',
        'responded_at'      => now(),
        'responded_by_name' => $user->name,
        'responded_ip'      => request()->ip(),
    ]);
    $quote->serviceRequest->update(['status' => 'approved']);

    QuoteEvent::create([
        'tenant_id'   => $quote->tenant_id,
        'quote_id'    => $quote->id,
        'user_id'     => $user->id,
        'type'        => 'accepted',
        'label'       => "Accepted by {$user->name} (client portal)",
        'occurred_at' => now(),
    ]);

    return redirect()->route('portal.quotes.show', $quote)->with('success', 'Quote accepted.');
}

public function quoteReject(Request $request, Quote $quote)
{
    $this->guardPlan();
    $this->guardCanRaise();
    abort_unless($this->scopedQuotes()->whereKey($quote->id)->exists(), 404);
    abort_unless($quote->status === 'sent', 422, 'This quote can no longer be rejected.');

    $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
    $user = auth()->user();

    $quote->update([
        'status'            => 'rejected',
        'responded_at'      => now(),
        'responded_by_name' => $user->name,
        'responded_ip'      => $request->ip(),
        'rejection_reason'  => $data['reason'] ?? null,
    ]);

    // Blueprint 7.1: Quoted -> Cancelled when the client rejects. Reason recorded.
    $sr = $quote->serviceRequest;
    if ($sr && $sr->status === 'quoted') {
        $fromStatus = $sr->status;
        $reason = 'Quote rejected by client'
            . (! empty($data['reason']) ? ': ' . $data['reason'] : '.');

        $sr->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        \App\Models\SrStatusHistory::log($sr, $fromStatus, 'cancelled', 'user', $user->id, $reason);
    }

    QuoteEvent::create([
        'tenant_id'   => $quote->tenant_id,
        'quote_id'    => $quote->id,
        'user_id'     => $user->id,
        'type'        => 'rejected',
        'label'       => "Rejected by {$user->name} (client portal)",
        'occurred_at' => now(),
    ]);

    return redirect()->route('portal.quotes.show', $quote)->with('success', 'Quote rejected.');
}
/* ========== INVOICES (read-only, no payment) ========== */

private function scopedInvoices()
{
    $user = auth()->user();
    // Spec: "The client sees sent invoices only" — never draft/pending_approval/approved.
    $query = Invoice::where('client_id', $user->client_id ?? 0)
        ->whereNotIn('status', ['draft', 'pending_approval', 'approved']);

    if ($user->role?->code === 'client_viewer') {
        $siteIds = $user->site_scope_json ?? [];
        $query->whereHas('serviceRequest', fn ($q) => $q->whereIn('project_site_id', $siteIds ?: [0]));
    }

    return $query;
}

public function invoices(Request $request)
{
    $this->guardPlan();
    $items = $this->scopedInvoices()
        ->with(['serviceRequest:id,sr_number,title'])
        ->orderByDesc('sent_at')
        ->paginate(25);

    return view('client.invoices.index', compact('items'));
}

public function invoiceShow(Invoice $invoice)
{
    $this->guardPlan();
    abort_unless($this->scopedInvoices()->whereKey($invoice->id)->exists(), 404);

    $invoice->load(['items.taxRate', 'serviceRequest', 'pdfAttachment']);

    return view('client.invoices.show', compact('invoice'));
}

public function invoicePdf(Invoice $invoice)
{
    $this->guardPlan();
    abort_unless($this->scopedInvoices()->whereKey($invoice->id)->exists(), 404);

    $invoice->load(['items.taxRate', 'serviceRequest.site', 'client']);
    return view('admin.invoices.pdf', compact('invoice')); // same clean PDF view staff already use
}
}