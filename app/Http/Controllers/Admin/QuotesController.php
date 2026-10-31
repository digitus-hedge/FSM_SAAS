<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Quote, QuoteItem, QuoteEvent, ServiceRequest, TaxRate, Attachment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotesController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->role?->name === 'Company Admin', 403);
    }

    /* ============================================================
     * STARTING A QUOTE — "Make quote" modal lands here
     * ============================================================ */


    public function index(Request $request)
    {
        $this->guard();
        $tab = $request->input('tab', 'draft');

        $base = fn() => Quote::query()
            ->with(['serviceRequest:id,sr_number', 'client:id,name'])
            ->orderByDesc('updated_at');

        $items = match ($tab) {
            'sent'       => $base()->where('status', 'sent')->get(),
            'accepted'   => $base()->where('status', 'accepted')->get(),
            'rejected'   => $base()->where('status', 'rejected')->get(),
            'expired'    => $base()->where('status', 'expired')->get(),
            'all'        => $base()->get(),
            default      => $base()->where('status', 'draft')->get(),
        };

        $counts = [
            'draft'    => Quote::where('status', 'draft')->count(),
            'sent'     => Quote::where('status', 'sent')->count(),
            'accepted' => Quote::where('status', 'accepted')->count(),
            'rejected' => Quote::where('status', 'rejected')->count(),
            'expired'  => Quote::where('status', 'expired')->count(),
            'all'      => Quote::count(),
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
                    'currency_code'        => 'AED',
                    'valid_until'          => now()->addDays(10),
                    'terms'                => 'Prices are before tax. Work starts after acceptance. Payment is due as stated on the invoice.',
                    'created_by_user_id'   => auth()->id(),
                ]);
                QuoteEvent::log($q->id, 'draft_made');
                return $q;
            });
        }

        $taxRates = TaxRate::orderBy('name')->get(['id', 'name', 'rate_percent']);

        return view('admin.quotes.build', compact('quote', 'sr', 'taxRates'));
    }

    public function edit(Quote $quote)
    {
        $this->guard();
        $sr = $quote->serviceRequest;
        $taxRates = TaxRate::orderBy('name')->get(['id', 'name', 'rate_percent']);
        return view('admin.quotes.build', compact('quote', 'sr', 'taxRates'));
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

        // Compute totals server-side
        $subtotal = 0;
        $taxTotal = 0;
        $built = [];
        foreach ($data['lines'] as $i => $ln) {
            $line = (float) $ln['quantity'] * (float) $ln['unit_rate'];
            $tax  = 0;
            if (! empty($ln['tax_rate_id'])) {
                $tr = TaxRate::find($ln['tax_rate_id']);
                $tax = round($line * (($tr->rate_percent ?? 0) / 100), 3);
            }
            $subtotal += $line;
            $taxTotal += $tax;
            $built[] = [
                'line_no'     => $i + 1,
                'description' => $ln['description'],
                'quantity'    => $ln['quantity'],
                'unit'        => $ln['unit'] ?? null,
                'unit_rate'   => $ln['unit_rate'],
                'tax_rate_id' => $ln['tax_rate_id'] ?? null,
                'line_total'  => round($line, 3),
            ];
        }

        $discount = 0;
        if ($data['discount_type'] === 'percent') $discount = round($subtotal * ((float)$data['discount_value'] / 100), 3);
        if ($data['discount_type'] === 'amount')  $discount = round((float)$data['discount_value'], 3);

        $total = max(0, round($subtotal + $taxTotal - $discount, 3));

        DB::transaction(function () use ($quote, $data, $built, $subtotal, $taxTotal, $discount, $total) {
            $quote->update([
                'valid_until'    => $data['valid_until'],
                'terms'          => $data['terms'] ?? null,
                'discount_type'  => $data['discount_type'],
                'discount_value' => $data['discount_value'] ?? 0,
                'subtotal'       => round($subtotal, 3),
                'tax_total'      => round($taxTotal, 3),
                'discount_total' => $discount,
                'total'          => $total,
                'base_total'     => $total,
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
            'currency_code'      => 'AED',
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
        $this->guard();
        $quote->load(['items.taxRate', 'serviceRequest.site', 'client.contacts', 'creator', 'pdfAttachment', 'events.user']);
        return view('admin.quotes.show', compact('quote'));
    }

    public function pdf(Quote $quote)
    {
        $this->guard();
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

            \App\Models\QuoteToken::create([
                'tenant_id'         => $quote->tenant_id,
                'quote_id'          => $quote->id,
                'client_contact_id' => $contact?->id,
                'recipient_name'    => $name,
                'token'             => \App\Models\QuoteToken::generate(),
                'sent_via'          => $via,
                'sent_at'           => now(),
            ]);
        }

        $quote->update(['status' => 'sent', 'sent_at' => now()]);
        $quote->serviceRequest->update(['status' => 'quoted']);

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
            $new->save();

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
