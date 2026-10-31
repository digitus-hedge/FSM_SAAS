<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Invoice, InvoiceItem, InvoiceEvent, InvoiceToken, Payment, ServiceRequest, TaxRate, Attachment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicesController extends Controller
{
    private function guard(): void
    {
        $role = auth()->user()?->role?->name;
        abort_unless(in_array($role, ['Company Admin', 'Finance'], true), 403);
    }

    private function viewGuard(): void
    {
        // Allow more roles to read invoices (Ops Manager can approve)
        $role = auth()->user()?->role?->name;
        abort_unless(in_array($role, ['Company Admin', 'Finance', 'Operations Manager'], true), 403);
    }

    /* ========== LIST ========== */

    public function index(Request $request)
    {
        $this->viewGuard();
        $tab = $request->input('tab', 'draft');

        $base = fn() => Invoice::query()
            ->with(['serviceRequest:id,sr_number', 'client:id,name'])
            ->orderByDesc('updated_at');

        $items = match ($tab) {
            'toinvoice' => $this->toInvoiceRequests(),
            'pending'   => $base()->where('status', 'pending_approval')->get(),
            'approved'  => $base()->where('status', 'approved')->get(),
            'sent'      => $base()->whereIn('status', ['sent', 'partially_paid'])->get(),
            'overdue'   => $base()->where('status', 'overdue')->get(),
            'paid'      => $base()->whereIn('status', ['paid', 'written_off'])->get(),
            'all'       => $base()->get(),
            default     => $base()->where('status', 'draft')->get(),
        };

        $counts = [
            'toinvoice' => $this->toInvoiceRequests()->count(),
            'draft'     => Invoice::where('status', 'draft')->count(),
            'pending'   => Invoice::where('status', 'pending_approval')->count(),
            'approved'  => Invoice::where('status', 'approved')->count(),
            'sent'      => Invoice::whereIn('status', ['sent', 'partially_paid'])->count(),
            'overdue'   => Invoice::where('status', 'overdue')->count(),
            'paid'      => Invoice::whereIn('status', ['paid', 'written_off'])->count(),
            'all'       => Invoice::count(),
        ];

        return view('admin.invoices.index', compact('items', 'tab', 'counts'));
    }

    private function toInvoiceRequests()
    {
        return ServiceRequest::query()
            ->with(['client:id,name'])
            ->whereIn('status', ['Awaiting Invoice', 'Completed'])
            ->whereNotIn('id', Invoice::whereNotIn('status', ['void'])->pluck('service_request_id'))
            ->orderByDesc('updated_at')
            ->get();
    }

    /* ========== BUILD ========== */

    public function build(ServiceRequest $sr)
    {
        $this->guard();
        $sr->load(['client', 'site']);

        $invoice = Invoice::where('service_request_id', $sr->id)
            ->whereNotIn('status', ['void'])
            ->first();

        if (! $invoice) {
            $invoice = DB::transaction(function () use ($sr) {
                $inv = Invoice::create([
                    'tenant_id'          => auth()->user()->tenant_id,
                    'invoice_number'     => Invoice::nextNumber(auth()->user()->tenant_id),
                    'service_request_id' => $sr->id,
                    'client_id'          => $sr->client_id,
                    'source'             => 'builder',
                    'status'             => 'draft',
                    'currency_code'      => 'AED',
                    'due_date'           => now()->addDays(20),
                    'notes'              => 'Payment due within 20 days of invoice date.',
                    'created_by_user_id' => auth()->id(),
                ]);
                InvoiceEvent::log($inv->id, 'draft_made');
                return $inv;
            });
        }

        $taxRates = TaxRate::orderBy('name')->get(['id', 'name', 'rate_percent']);

        return view('admin.invoices.build', compact('invoice', 'sr', 'taxRates'));
    }

    public function edit(Invoice $invoice)
    {
        $this->guard();
        $this->ensureEditable($invoice);
        $sr = $invoice->serviceRequest;
        $taxRates = TaxRate::orderBy('name')->get(['id', 'name', 'rate_percent']);
        return view('admin.invoices.build', compact('invoice', 'sr', 'taxRates'));
    }

    public function saveBuilt(Request $request, ServiceRequest $sr)
    {
        return $this->persistBuilder($request, Invoice::findOrFail($request->invoice_id));
    }

    public function update(Request $request, Invoice $invoice)
    {
        return $this->persistBuilder($request, $invoice);
    }

    private function persistBuilder(Request $request, Invoice $invoice)
    {
        $this->guard();
        $this->ensureEditable($invoice);

        $data = $request->validate([
            'due_date'              => 'required|date',
            'notes'                 => 'nullable|string|max:2000',
            'legal_invoice_number'  => 'nullable|string|max:80',
            'discount_type'         => 'required|in:none,percent,amount',
            'discount_value'        => 'nullable|numeric|min:0',
            'lines'                 => 'required|array|min:1',
            'lines.*.description'   => 'required|string|min:2|max:255',
            'lines.*.quantity'      => 'required|numeric|min:0.01',
            'lines.*.unit'          => 'nullable|string|max:20',
            'lines.*.unit_rate'     => 'required|numeric|min:0',
            'lines.*.tax_rate_id'   => 'nullable|integer|exists:tax_rates,id',
        ], [
            // 'lines.*.description.min' => 'Enter a description of 2 to 255 characters.',
            // 'lines.*.description.max' => 'Enter a description of 2 to 255 characters.',

            'due_date.required'              => 'Pick a due date.',
            'due_date.date'                  => 'Due date is invalid.',
            'lines.*.description.required'   => 'Description is required.',
            'lines.*.description.min'        => 'Enter a description of 2 to 255 characters.',
            'lines.*.description.max'        => 'Enter a description of 2 to 255 characters.',
            'lines.*.quantity.required'      => 'Quantity is required.',
            'lines.*.quantity.numeric'       => 'Quantity must be a number.',
            'lines.*.quantity.min'           => 'Quantity must be at least 0.01.',
            'lines.*.unit_rate.required'     => 'Rate is required.',
            'lines.*.unit_rate.numeric'      => 'Rate must be a number.',
            'lines.*.unit_rate.min'          => 'Rate must be 0 or more.',
        ]);

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

        DB::transaction(function () use ($invoice, $data, $built, $subtotal, $taxTotal, $discount, $total) {
            $invoice->update([
                'due_date'              => $data['due_date'],
                'notes'                 => $data['notes'] ?? null,
                'legal_invoice_number'  => $data['legal_invoice_number'] ?? null,
                'discount_type'         => $data['discount_type'],
                'discount_value'        => $data['discount_value'] ?? 0,
                'subtotal'              => round($subtotal, 3),
                'tax_total'             => round($taxTotal, 3),
                'discount_total'        => $discount,
                'total'                 => $total,
                'base_total'            => $total,
                'balance'               => $total,
            ]);

            InvoiceItem::where('invoice_id', $invoice->id)->delete();
            foreach ($built as $row) {
                InvoiceItem::create(array_merge(['invoice_id' => $invoice->id], $row));
            }

            InvoiceEvent::log($invoice->id, 'saved');
        });

        if ($request->input('and_submit')) {
            return $this->submit($invoice);
        }

        return redirect()->route('admin.invoices.edit', $invoice)
            ->with('success', 'Invoice saved.');
    }

    /* ========== UPLOAD PDF VARIANT ========== */

    public function uploadForm(ServiceRequest $sr)
    {
        $this->guard();
        $sr->load(['client', 'site']);
        return view('admin.invoices.upload', compact('sr'));
    }

    public function storeUpload(Request $request, ServiceRequest $sr)
    {
        $this->guard();
        $data = $request->validate([
            'pdf'          => 'required|file|mimes:pdf|max:10240',
            'total'        => 'required|numeric|min:0.01',
            'tax_included' => 'nullable|numeric|min:0',
            'due_date'     => 'required|date',
            'notes'        => 'nullable|string|max:500',
        ]);

        $file = $request->file('pdf');
        $filename = time() . '_' . uniqid() . '.pdf';
        $path = $file->storeAs("invoices/{$sr->id}", $filename, 'public');

        $att = Attachment::create([
            'tenant_id'           => auth()->user()->tenant_id,
            'attachable_type'     => Invoice::class,
            'attachable_id'       => 0,
            'kind'                => 'invoice_pdf',
            'storage_path'        => $path,
            'original_name'       => $file->getClientOriginalName(),
            'mime_type'           => 'application/pdf',
            'size_bytes'          => $file->getSize(),
            'sha256'              => hash_file('sha256', $file->getRealPath()),
            'captured_at'         => now(),
            'uploaded_by_user_id' => auth()->id(),
            'client_event_uuid'   => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $invoice = Invoice::create([
            'tenant_id'          => auth()->user()->tenant_id,
            'invoice_number'     => Invoice::nextNumber(auth()->user()->tenant_id),
            'service_request_id' => $sr->id,
            'client_id'          => $sr->client_id,
            'source'             => 'uploaded_pdf',
            'status'             => 'draft',
            'currency_code'      => 'AED',
            'subtotal'           => $data['total'] - ($data['tax_included'] ?? 0),
            'tax_total'          => $data['tax_included'] ?? 0,
            'total'              => $data['total'],
            'base_total'         => $data['total'],
            'balance'            => $data['total'],
            'due_date'           => $data['due_date'],
            'notes'              => $data['notes'] ?? null,
            'pdf_attachment_id'  => $att->id,
            'created_by_user_id' => auth()->id(),
        ]);

        $att->update(['attachable_id' => $invoice->id]);
        InvoiceEvent::log($invoice->id, 'draft_made', 'Uploaded ' . $file->getClientOriginalName());

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Invoice saved.');
    }

    /* ========== SHOW / PDF ========== */

    public function show(Invoice $invoice)
    {
        $this->viewGuard();
        $invoice->load(['items.taxRate', 'serviceRequest.site', 'client.contacts', 'creator', 'approver', 'submitter', 'pdfAttachment', 'events.user', 'payments.receivedBy', 'payments.reversedBy']);
        return view('admin.invoices.show', compact('invoice'));
    }

    public function pdf(Invoice $invoice)
    {
        $this->viewGuard();
        $invoice->load(['items.taxRate', 'serviceRequest.site', 'client']);
        return view('admin.invoices.pdf', compact('invoice'));
    }

    /* ========== LIFECYCLE ACTIONS ========== */

    public function submit(Invoice $invoice)
    {
        $this->guard();
        abort_unless($invoice->status === 'draft', 422, 'Only draft invoices can be submitted.');

        $invoice->update([
            'status'               => 'pending_approval',
            'submitted_at'         => now(),
            'submitted_by_user_id' => auth()->id(),
        ]);
        InvoiceEvent::log($invoice->id, 'submitted');

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Submitted for approval.');
    }

    public function approve(Invoice $invoice)
    {
        $this->guard();
        abort_unless($invoice->status === 'pending_approval', 422);

        DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status'              => 'approved',
                'approved_at'         => now(),
                'approved_by_user_id' => auth()->id(),
                'issued_at'           => $invoice->issued_at ?? now(),
            ]);
            InvoiceEvent::log(
                $invoice->id,
                'approved',
                $invoice->submitted_by_user_id === auth()->id() ? '(own invoice)' : null
            );

            $sr = $invoice->serviceRequest;
            if ($sr) {
                // When the invoice goes out, the job itself is done — advance the SR status.
                $sr->update([
                    'status'         => 'completed',
                    // 'billing_status' => 'invoiced',   // (keeps your existing cascade)
                ]);
            }
        });

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Invoice approved.');
    }

    public function sendBack(Request $request, Invoice $invoice)
    {
        $this->guard();
        abort_unless($invoice->status === 'pending_approval', 422);
        $data = $request->validate(['reason' => 'required|string|min:3|max:500']);

        $invoice->update([
            'status'           => 'draft',
            'send_back_at'     => now(),
            'send_back_reason' => $data['reason'],
        ]);
        InvoiceEvent::log($invoice->id, 'sent_back', $data['reason']);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Sent back to draft.');
    }

    public function send(Request $request, Invoice $invoice)
    {
        $this->guard();
        abort_unless(in_array($invoice->status, ['approved', 'sent', 'partially_paid', 'overdue']), 422);

        $request->validate([
            'recipients'   => 'required|array|min:1',
            'recipients.*' => 'string',
            'message'      => 'nullable|string|max:1500',
        ]);

        $contacts = $invoice->client?->contacts ?? collect();

        foreach ($request->recipients as $name) {
            $contact = $contacts->firstWhere('name', $name);
            $via     = $contact?->whatsapp_number ? 'whatsapp' : 'email';

            InvoiceToken::create([
                'tenant_id'         => $invoice->tenant_id,
                'invoice_id'        => $invoice->id,
                'client_contact_id' => $contact?->id,
                'recipient_name'    => $name,
                'token'             => InvoiceToken::generate(),
                'sent_via'          => $via,
                'sent_at'           => now(),
            ]);
        }

        $sr = $invoice->serviceRequest;

        if ($invoice->status === 'approved') {
            $invoice->update(['status' => 'sent', 'sent_at' => now()]);

            $sr->update(['billing_status' => 'invoiced', 'status' => 'completed']);
        }
        InvoiceEvent::log($invoice->id, 'sent', 'Sent to ' . implode(', ', $request->recipients));

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Invoice sent.');
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $this->guard();
        abort_unless(in_array($invoice->status, ['sent', 'partially_paid', 'overdue']), 422);

        $data = $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'received_at' => 'required|date',
            'method'      => 'required|in:cash,bank_transfer,cheque,card,other',
            'reference'   => 'nullable|string|max:120',
            'note'        => 'nullable|string|max:1000',
        ]);

        if ($data['amount'] > ($invoice->balance + 0.001)) {
            return back()->withErrors(['amount' => 'Amount exceeds balance due.'])->withInput();
        }

        DB::transaction(function () use ($invoice, $data) {
            Payment::create([
                'tenant_id'           => $invoice->tenant_id,
                'invoice_id'          => $invoice->id,
                'payment_number'      => Payment::nextNumber($invoice->tenant_id),
                'amount'              => $data['amount'],
                'received_at'         => $data['received_at'],
                'method'              => $data['method'],
                'reference'           => $data['reference'] ?? null,
                'note'                => $data['note'] ?? null,
                'received_by_user_id' => auth()->id(),
            ]);
            $invoice->recomputeBalance();
            InvoiceEvent::log($invoice->id, 'payment_received', 'AED ' . number_format($data['amount'], 3));


            $sr = $invoice->serviceRequest;
            if ($sr) {
                if ($invoice->status === 'paid') {
                    // Fully paid — move SR to Paid
                    $sr->update(['billing_status' => 'paid']);
                    InvoiceEvent::log($invoice->id, 'invoice_paid', 'Request marked as Paid');
                } else {
                    // Partial payment — keep SR at Invoiced
                    if ($sr->billing_status !== 'invoiced') {
                        $sr->update(['billing_status' => 'invoiced']);
                    }
                }
            }
        });

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Payment recorded.');
    }

    public function reversePayment(Request $request, Payment $payment)
    {
        $this->guard();
        abort_unless(! $payment->reversed_at, 422, 'Already reversed.');

        $data = $request->validate(['reason' => 'required|string|min:3|max:500']);

        DB::transaction(function () use ($payment, $data) {
            $payment->update([
                'reversed_at'         => now(),
                'reversed_by_user_id' => auth()->id(),
                'reversal_reason'     => $data['reason'],
            ]);
            $payment->invoice->recomputeBalance();
            InvoiceEvent::log($payment->invoice_id, 'payment_reversed', $payment->payment_number . ' — ' . $data['reason']);
        });

        return redirect()->route('admin.invoices.show', $payment->invoice)
            ->with('success', 'Payment reversed.');
    }

    public function writeOff(Request $request, Invoice $invoice)
    {
        $this->guard();
        abort_unless(in_array($invoice->status, ['sent', 'partially_paid', 'overdue']), 422);

        $data = $request->validate(['reason' => 'required|string|min:3|max:500']);
        $amount = $invoice->balance;

        $invoice->update([
            'status'           => 'written_off',
            'written_off_at'   => now(),
            'write_off_amount' => $amount,
            'write_off_reason' => $data['reason'],
            'balance'          => 0,
        ]);
        InvoiceEvent::log($invoice->id, 'written_off', 'AED ' . number_format($amount, 3) . ' — ' . $data['reason']);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Balance written off.');
    }

    public function undoWriteOff(Request $request, Invoice $invoice)
    {
        $this->guard();
        abort_unless($invoice->status === 'written_off', 422);

        $invoice->update([
            'status'           => 'sent',
            'written_off_at'   => null,
            'write_off_amount' => null,
            'write_off_reason' => null,
        ]);
        $invoice->recomputeBalance();
        InvoiceEvent::log($invoice->id, 'write_off_undone');

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Write-off undone.');
    }

    public function void(Request $request, Invoice $invoice)
    {
        $this->guard();
        abort_if($invoice->status === 'void', 422);

        $data = $request->validate(['reason' => 'required|string|min:3|max:500']);

        $invoice->update([
            'status'      => 'void',
            'voided_at'   => now(),
            'void_reason' => $data['reason'],
            'balance'     => 0,
        ]);
        InvoiceEvent::log($invoice->id, 'voided', $data['reason']);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Invoice voided.');
    }

    private function ensureEditable(Invoice $invoice): void
    {
        abort_unless(in_array($invoice->status, ['draft']), 422, 'Only draft invoices are editable.');
    }
}
