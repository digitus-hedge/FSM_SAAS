<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Quote, QuoteItem, QuoteEvent, ServiceRequest, TaxRate, Attachment};
use App\Services\Billing\DocumentTotals;
use App\Services\Currency\DocumentCurrency;
use App\Services\Permissions\PermissionLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotesController extends Controller
{
    /** Quotes are built and sent by whoever holds quote.manage at change level: Company Admin and Finance (blueprint 7.7a). */
    private function guard(): void
    {
        abort_unless(PermissionLevel::canChange(auth()->user(), 'quote.manage'), 403);
    }

    /** Open the list, a quote and its PDF read-only: quote.manage at view level or above (Operations Manager, blueprint 7.7a). */
    private function viewGuard(): void
    {
        abort_unless(PermissionLevel::canView(auth()->user(), 'quote.manage'), 403);
    }

    /** What the builder needs to show the currency picker, the rate line and the lock (spec 5.2). */
    private function currencyView(Quote $quote): array
    {
        $tenant  = auth()->user()->tenant;
        $dc      = app(DocumentCurrency::class);
        $options = $dc->options($tenant, $quote->currency_code);

        return [
            'base'            => DocumentCurrency::base($tenant),
            'currencyOptions' => $options,
            'pickerAvailable' => $dc->pickerAvailable($tenant),
            'rateMap'         => $dc->rateMap($tenant, $options),
            'docLocked'       => $quote->status !== 'draft',
        ];
    }

    /* ============================================================
     * STARTING A QUOTE — "Make quote" modal lands here
     * ============================================================ */


    public function index(Request $request)
{
    $this->viewGuard();
    $tenantId = auth()->user()->tenant_id;
    $tab = $request->input('tab', 'all');

    $base = fn () => Quote::query()
        ->where('tenant_id', $tenantId)
        ->with(['serviceRequest:id,sr_number', 'client:id,name'])
        ->orderByDesc('id');

    $items = match ($tab) {
        'sent'     => $base()->where('status', 'sent')->get(),
        'accepted' => $base()->where('status', 'accepted')->get(),
        'rejected' => $base()->where('status', 'rejected')->get(),
        'expired'  => $base()->where('status', 'expired')->get(),
        'all'      => $base()->get(),
        default    => $base()->where('status', 'draft')->get(),
    };

    $counts = [
        'draft'    => Quote::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
        'sent'     => Quote::where('tenant_id', $tenantId)->where('status', 'sent')->count(),
        'accepted' => Quote::where('tenant_id', $tenantId)->where('status', 'accepted')->count(),
        'rejected' => Quote::where('tenant_id', $tenantId)->where('status', 'rejected')->count(),
        'expired'  => Quote::where('tenant_id', $tenantId)->where('status', 'expired')->count(),
        'all'      => Quote::where('tenant_id', $tenantId)->count(),
    ];

    return view('admin.quotes.index', compact('items', 'tab', 'counts'));
}

    public function build(ServiceRequest $sr)
    {
        $this->guard();
        $sr->load(['client', 'site']);

        // Reuse an open draft if one exists for this SR, else create one
        $quote = Quote::where('service_request_id', $sr->id)
            ->where('status', 'draft')
            ->where('source', 'builder')
            ->first();

        if (! $quote) {
            $quote = DB::transaction(function () use ($sr) {
                $q = Quote::create([
                    'tenant_id'            => auth()->user()->tenant_id,
                    'quote_number'         => Quote::nextNumber(auth()->user()->tenant_id),
                    'service_request_id'   => $sr->id,
                    'client_id'            => $sr->client_id,
                    'source'               => 'builder',
                    'status'               => 'draft',
                    'currency_code'        => auth()->user()->tenant?->currency_code ?? 'AED',
                    'exchange_rate'        => 1,
                    'valid_until'          => now()->addDays(10),
                    'terms'                => 'Prices are before tax. Work starts after acceptance. Payment is due as stated on the invoice.',
                    'created_by_user_id'   => auth()->id(),
                ]);
                QuoteEvent::log($q->id, 'draft_made');
                return $q;
            });
        }

        $taxRates = TaxRate::orderBy('name')->get(['id', 'name', 'rate_percent']);

        return view('admin.quotes.build', array_merge(compact('quote', 'sr', 'taxRates'), $this->currencyView($quote)));
    }

    public function edit(Quote $quote)
    {
        $this->guard();
        // A sent quote is revised, not edited: its totals, currency and rate stay as sent.
        if ($quote->status !== 'draft') {
            return redirect()->route('admin.quotes.show', $quote)->with('error', 'Only draft quotes can be edited. Revise the quote instead.');
        }
        $sr = $quote->serviceRequest;
        $taxRates = TaxRate::orderBy('name')->get(['id', 'name', 'rate_percent']);
        return view('admin.quotes.build', array_merge(compact('quote', 'sr', 'taxRates'), $this->currencyView($quote)));
    }

    public function saveBuilt(Request $request, ServiceRequest $sr)
    {
        return $this->persistBuilder($request, Quote::findOrFail($request->quote_id));
    }

    public function update(Request $request, Quote $quote)
    {
        return $this->persistBuilder($request, $quote);
    }

    private function persistBuilder(Request $request, Quote $quote)
    {
        $this->guard();

        $tenant = auth()->user()->tenant;
        $dc     = app(DocumentCurrency::class);
        $input  = $request->only(['currency_code', 'exchange_rate', 'rate_manual']);

        // D5: after it is sent, a quote's currency and rate are fixed (RATE_LOCKED), and its totals are never worked out again.
        if ($quote->status !== 'draft') {
            $dc->resolve($tenant, $quote, $input, true);
            abort(422, 'Only draft quotes can be edited. Revise the quote instead.');
        }

        // $data = $request->validate([
        //     'valid_until'           => 'required|date',
        //     'terms'                 => 'nullable|string|max:2000',
        //     'discount_type'         => 'required|in:none,percent,amount',
        //     'discount_value'        => 'nullable|numeric|min:0',
        //     'lines'                 => 'required|array|min:1',
        //     'lines.*.description'   => 'required|string|min:2|max:255',
        //     'lines.*.quantity'      => 'required|numeric|min:0.01',
        //     'lines.*.unit'          => 'nullable|string|max:20',
        //     'lines.*.unit_rate'     => 'required|numeric|min:0',
        //     'lines.*.tax_rate_id'   => 'nullable|integer|exists:tax_rates,id',
        // ], [
        //     'lines.*.description.min' => 'Enter a description of 2 to 255 characters.',
        //     'lines.*.description.max' => 'Enter a description of 2 to 255 characters.',
        // ]);


        $data = $request->validate([
            'valid_until'           => 'required|date',
            'terms'                 => 'nullable|string|max:2000',
            'discount_type'         => 'required|in:none,percent,amount',
            'discount_value'        => 'nullable|numeric|min:0',
            'currency_code'         => 'nullable|string|size:3',
            'exchange_rate'         => 'nullable|numeric',
            'rate_manual'           => 'nullable|boolean',
            'lines'                 => 'required|array|min:1',
            'lines.*.description'   => 'required|string|min:2|max:255',
            'lines.*.quantity'      => 'required|numeric|min:0.01',
            'lines.*.unit'          => 'nullable|string|max:20',
            'lines.*.unit_rate'     => 'required|numeric|min:0',
            'lines.*.tax_rate_id'   => 'nullable|integer|exists:tax_rates,id',
        ], [
            'valid_until.required'          => 'Pick a valid-until date.',
            'lines.*.description.required'  => 'Description is required.',
            'lines.*.description.min'       => 'Enter a description of 2 to 255 characters.',
            'lines.*.description.max'       => 'Enter a description of 2 to 255 characters.',
            'lines.*.quantity.required'     => 'Quantity is required.',
            'lines.*.quantity.numeric'      => 'Quantity must be a number.',
            'lines.*.quantity.min'          => 'Quantity must be at least 0.01.',
            'lines.*.unit_rate.required'    => 'Rate is required.',
            'lines.*.unit_rate.numeric'     => 'Rate must be a number.',
            'lines.*.unit_rate.min'         => 'Rate must be 0 or more.',
        ]);

        // The currency and the rate (D4, D6): from the latest stored rate, or typed by Finance on this document.
        $cur = $dc->resolve($tenant, $quote, $input, false);
        if ($cur['changed'] && ! DocumentCurrency::mayChange(auth()->user(), 'quote')) {
            abort(403);
        }

        // Totals worked out on the server with the arithmetic of blueprint 7.7a, in the document's currency.
        $lines   = array_values($data['lines']);
        $taxPct  = TaxRate::whereIn('id', collect($lines)->pluck('tax_rate_id')->filter())->pluck('rate_percent', 'id');
        $calc    = DocumentTotals::compute(
            array_map(fn ($ln) => [
                'quantity' => $ln['quantity'], 'unit_rate' => $ln['unit_rate'],
                'tax_percent' => ! empty($ln['tax_rate_id']) ? ($taxPct[$ln['tax_rate_id']] ?? 0) : 0,
            ], $lines),
            $data['discount_type'], $data['discount_value'] ?? 0, $cur['currency_code'],
        );

        $built = [];
        foreach ($lines as $i => $ln) {
            $built[] = [
                'line_no'     => $i + 1,
                'description' => $ln['description'],
                'quantity'    => $ln['quantity'],
                'unit'        => $ln['unit'] ?? null,
                'unit_rate'   => $ln['unit_rate'],
                'tax_rate_id' => $ln['tax_rate_id'] ?? null,
                'line_total'  => $calc['lines'][$i],
            ];
        }

        $old = DocumentCurrency::snapshot($quote);

        DB::transaction(function () use ($quote, $data, $built, $calc, $cur, $tenant) {
            $quote->update([
                'valid_until'    => $data['valid_until'],
                'terms'          => $data['terms'] ?? null,
                'discount_type'  => $data['discount_type'],
                'discount_value' => $data['discount_value'] ?? 0,
                'currency_code'  => $cur['currency_code'],
                'exchange_rate'  => $cur['exchange_rate'],
                'rate_source'    => $cur['rate_source'] ?? 'provider', // the column is an enum that cannot be null; a base-currency quote has no rate to speak of
                'subtotal'       => $calc['subtotal'],
                'tax_total'      => $calc['tax_total'],
                'discount_total' => $calc['discount_total'],
                'total'          => $calc['total'],
                'base_total'     => DocumentCurrency::baseTotal($calc['total'], $cur['exchange_rate'], $tenant),
            ]);

            ServiceRequest::where('id', $quote->service_request_id)
                ->where('tenant_id', $quote->tenant_id)
                ->update(['status' => 'quoted']);

            QuoteItem::where('quote_id', $quote->id)->delete();
            foreach ($built as $row) {
                QuoteItem::create(array_merge(['quote_id' => $quote->id], $row));
            }

            QuoteEvent::log($quote->id, 'saved');
        });

        if ($cur['changed']) {
            $dc->audit('quote.rate_changed', $quote, $tenant, $old, DocumentCurrency::snapshot($quote->fresh()));
        }
        if ($request->input('and_send')) {
            return redirect()->route('admin.quotes.show', $quote)
                ->with('open_send', 1);
        }

        return redirect()->route('admin.quotes.edit', $quote)
            ->with('success', 'Quote saved.');
    }

    /* ============================================================
     * UPLOAD PDF variant
     * ============================================================ */

    public function uploadForm(ServiceRequest $sr)
    {
        $this->guard();
        $sr->load(['client', 'site']);
        return view('admin.quotes.upload', compact('sr'));
    }

    public function storeUpload(Request $request, ServiceRequest $sr)
    {
        $this->guard();
        $data = $request->validate([
            'pdf'          => 'required|file|mimes:pdf|max:10240',
            'total'        => 'required|numeric|min:0.01',
            'tax_included' => 'nullable|numeric|min:0',
            'valid_until'  => 'required|date',
            'and_send'     => 'nullable|boolean',
        ]);

        $file = $request->file('pdf');
        $filename = time() . '_' . uniqid() . '.pdf';
        $path = $file->storeAs("quotes/{$sr->id}", $filename, 'public');

        $att = Attachment::create([
            'tenant_id'          => auth()->user()->tenant_id,
            'attachable_type'    => Quote::class,
            'attachable_id'      => 0, // set after quote creation
            'kind'               => 'quote_pdf',
            'storage_path'       => $path,
            'original_name'      => $file->getClientOriginalName(),
            'mime_type'          => 'application/pdf',
            'size_bytes'         => $file->getSize(),
            'sha256'             => hash_file('sha256', $file->getRealPath()),
            'captured_at'        => now(),
            'uploaded_by_user_id' => auth()->id(),
            'client_event_uuid'  => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $quote = Quote::create([
            'tenant_id'          => auth()->user()->tenant_id,
            'quote_number'       => Quote::nextNumber(auth()->user()->tenant_id),
            'service_request_id' => $sr->id,
            'client_id'          => $sr->client_id,
            'source'             => 'uploaded_pdf',
            'status'             => 'draft',
            'currency_code'      => auth()->user()->tenant?->currency_code ?? 'AED',
            'subtotal'           => $data['total'] - ($data['tax_included'] ?? 0),
            'tax_total'          => $data['tax_included'] ?? 0,
            'total'              => $data['total'],
            'base_total'         => $data['total'],
            'valid_until'        => $data['valid_until'],
            'terms'              => 'Prices are before tax. Work starts after acceptance. Payment is due as stated on the invoice.',
            'pdf_attachment_id'  => $att->id,
            'created_by_user_id' => auth()->id(),
        ]);

        $att->update(['attachable_id' => $quote->id]);


        $sr->update([
            'status' => 'quoted',
        ]);

        QuoteEvent::log($quote->id, 'draft_made', 'Uploaded ' . $file->getClientOriginalName());

        if (! empty($data['and_send'])) {
            return redirect()->route('admin.quotes.show', $quote)->with('open_send', 1);
        }

        return redirect()->route('admin.quotes.show', $quote)->with('success', 'Quote saved.');
    }

    /* ============================================================
     * SHOW / SEND / REVISE / PDF
     * ============================================================ */

    public function show(Quote $quote)
    {
        $this->viewGuard();
        $quote->load(['items.taxRate', 'serviceRequest.site', 'client.contacts', 'creator', 'pdfAttachment', 'events.user']);
        return view('admin.quotes.show', compact('quote'));
    }

    public function pdf(Quote $quote)
    {
        $this->viewGuard();
        $quote->load(['items.taxRate', 'serviceRequest.site', 'client']);
        return view('admin.quotes.pdf', compact('quote'));
    }

    public function send(Request $request, Quote $quote)
    {

        // $this->guard();
        // $request->validate([
        //     'recipients'   => 'required|array|min:1',
        //     'recipients.*' => 'string',
        //     'message'      => 'nullable|string|max:1500',
        // ]);

        // $names = implode(', ', $request->recipients);
        // $quote->update([
        //     'status'  => 'sent',
        //     'sent_at' => now(),
        // ]);
        // $quote->serviceRequest->update(['billing_status' => 'quoted']);

        // QuoteEvent::log($quote->id, 'sent', 'Sent to ' . $names);

        // return redirect()->route('admin.quotes.show', $quote)->with('success', 'Quote sent.');



        $this->guard();
        $request->validate([
            'recipients'   => 'required|array|min:1',
            'recipients.*' => 'string',
            'message'      => 'nullable|string|max:1500',
        ]);

        $contacts = $quote->client?->contacts ?? collect();

        foreach ($request->recipients as $name) {
            $contact = $contacts->firstWhere('name', $name);
            $via     = $contact?->whatsapp_number ? 'whatsapp' : 'email';

             $token = \App\Models\QuoteToken::create([
                'tenant_id'         => $quote->tenant_id,
                'quote_id'          => $quote->id,
                'client_contact_id' => $contact?->id,
                'recipient_name'    => $name,
                'token'             => \App\Models\QuoteToken::generate(),
                'sent_via'          => $via,
                'sent_at'           => now(),
            ]);

            if ($via === 'email' && $contact?->email) {
                app(\App\Services\Messaging\MessageLogger::class)->send($contact->email, new \App\Mail\QuoteSentMail($token), 'quote.sent', [
                    'tenant_id' => $quote->tenant_id, 'contact_id' => $contact->id, 'related' => $quote, 'payload' => ['token_id' => $token->id],
                ]);
            }
        }

        $quote->update(['status' => 'sent', 'sent_at' => now()]);
        $quote->serviceRequest->update([
            'status'         => 'quoted',
            
        ]);

        \App\Models\QuoteEvent::log($quote->id, 'sent', 'Sent to ' . implode(', ', $request->recipients));

        return redirect()->route('admin.quotes.show', $quote)->with('success', 'Quote sent.');
    }

    public function revise(Quote $quote)
    {
        $this->guard();

        $copy = DB::transaction(function () use ($quote) {
            $new = $quote->replicate(['sent_at', 'responded_at', 'responded_by_name', 'responded_ip']);
            $new->status               = 'draft';
            $new->version_no           = $quote->version_no + 1;
            $new->supersedes_quote_id  = $quote->id;
            $new->quote_number         = Quote::nextNumber(auth()->user()->tenant_id);
            $new->created_by_user_id   = auth()->id();

            // 6.4: the revision keeps the currency and refills the rate from the latest stored rate. Finance can change it.
            $tenant = auth()->user()->tenant;
            $dc     = app(DocumentCurrency::class);
            $old    = DocumentCurrency::snapshot($quote);
            if (strtoupper($new->currency_code) !== DocumentCurrency::base($tenant) && ($latest = $dc->latest($tenant, $new->currency_code))) {
                $new->exchange_rate = $latest['exchange_rate'];
                $new->rate_source   = $latest['rate_source'];
            }
            $new->base_total = DocumentCurrency::baseTotal($new->total, $new->exchange_rate, $tenant);
            $new->save();

            if (DocumentCurrency::snapshot($new) !== $old) {
                $dc->audit('quote.rate_changed', $new, $tenant, $old, DocumentCurrency::snapshot($new));
            }

            foreach ($quote->items as $it) {
                $copyIt = $it->replicate();
                $copyIt->quote_id = $new->id;
                $copyIt->save();
            }

            $quote->update(['status' => 'superseded']);
            QuoteEvent::log($new->id, 'draft_made', 'Revised from ' . $quote->quote_number);
            return $new;
        });

        return redirect()->route('admin.quotes.edit', $copy)->with('success', 'New revision ready.');
    }
}
