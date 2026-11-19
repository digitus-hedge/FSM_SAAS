<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Invoice, InvoiceItem, InvoiceEvent, InvoiceToken, Payment, ServiceRequest, ServiceRequestExpense, TaxRate, Attachment};
use App\Models\TenantSetting;
use App\Services\Billing\{DocumentTotals, ExpenseLines};
use App\Services\Currency\DocumentCurrency;
use App\Services\Permissions\PermissionLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoicesController extends Controller
{
    /** Build, edit, submit, send, write off and void: invoice.manage at change level (Company Admin, Finance). */
    private function guard(): void
    {
        abort_unless(\App\Services\Permissions\PermissionLevel::canChange(auth()->user(), 'invoice.manage'), 403);
    }

    /** Open an invoice read-only: invoice.manage or invoice.approve (Company Admin, Finance, Operations Manager). */
    private function viewGuard(): void
    {
        abort_unless(\App\Services\Permissions\PermissionLevel::canView(auth()->user(), 'invoice.manage', 'invoice.approve'), 403);
    }

    /** Approve and send back: invoice.approve at change level (Company Admin, Operations Manager), per the signed matrix. */
    private function approveGuard(): void
    {
        abort_unless(\App\Services\Permissions\PermissionLevel::canChange(auth()->user(), 'invoice.approve'), 403);
    }

    /** Record and reverse a payment: payment.record at change level (Company Admin, Finance). */
    private function paymentGuard(): void
    {
        abort_unless(\App\Services\Permissions\PermissionLevel::canChange(auth()->user(), 'payment.record'), 403);
    }

    /** Undoing a write-off is for the Company Admin only (blueprint 7.7a). There is no permission key for it. */
    private function adminOnlyGuard(): void
    {
        abort_unless(auth()->user()?->role?->code === 'company_admin', 403);
    }

    /**
     * An invoice can only be started once QC has actually passed the job as
     * out-of-coverage (status = 'awaiting_invoice'). Without this, an SR could
     * be invoiced — and then force-marked completed — before a technician was
     * ever assigned or any work was logged.
     */
    private function readyToInvoiceOrRedirect(ServiceRequest $sr)
    {
        if ($sr->status !== 'awaiting_invoice') {
            return redirect()->route('service-requests.show', $sr)
                ->with('error', "{$sr->sr_number} isn't ready to invoice yet — it must pass QC review first (status: " . $sr->statusLabel() . ').');
        }
        return null;
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
        // Fixed: was matching 'Awaiting Invoice' / 'Completed' (capitalized, with a
        // space) against a column that actually stores lowercase snake_case values
        // ('awaiting_invoice') — so this never matched a real row. Also dropped
        // 'completed' entirely: a job only reaches that status directly when QC
        // passes it as covered (billing_status = 'not_applicable'), which means no
        // invoice is needed for it at all. Only 'awaiting_invoice' (QC passed it as
        // out-of-coverage) should ever need one.
        return ServiceRequest::query()
            ->with(['client:id,name'])
            ->where('status', 'awaiting_invoice')
            ->whereNotIn('id', Invoice::whereNotIn('status', ['void'])->pluck('service_request_id'))
            ->orderByDesc('updated_at')
            ->get();
    }

    /* ========== BUILD ========== */

    public function build(ServiceRequest $sr)
{
    $this->guard();
    if ($redirect = $this->readyToInvoiceOrRedirect($sr)) {
        return $redirect;
    }
    $sr->load(['client', 'site']);

    $invoice = Invoice::where('service_request_id', $sr->id)
        ->whereNotIn('status', ['void'])
        ->first();

    if (! $invoice) {
        // Pull the client's accepted quote, if there is one, so its lines and
        // totals carry straight over — the client already agreed to this price,
        // it shouldn't need re-typing.
        $quote = \App\Models\Quote::where('service_request_id', $sr->id)
            ->where('status', 'accepted')
            ->orderByDesc('id')
            ->with('items')
            ->first();

        $invoice = DB::transaction(function () use ($sr, $quote) {
            $inv = Invoice::create([
                'tenant_id'          => auth()->user()->tenant_id,
                'invoice_number'     => Invoice::nextNumber(auth()->user()->tenant_id),
                'service_request_id' => $sr->id,
                'client_id'          => $sr->client_id,
                'source'             => 'builder',
                'status'             => 'draft',
                'currency_code'      => $quote->currency_code ?? (auth()->user()->tenant?->currency_code ?? 'AED'),
                'exchange_rate'      => $quote->exchange_rate ?? 1,
                'rate_source'        => $quote->rate_source ?? null,
                'due_date'           => now()->addDays(20),
                'notes'              => $quote->terms ?? 'Payment due within 20 days of invoice date.',
                'created_by_user_id' => auth()->id(),
                'discount_type'      => $quote->discount_type ?? 'none',
                'discount_value'     => $quote->discount_value ?? 0,
                'subtotal'           => $quote->subtotal ?? 0,
                'tax_total'          => $quote->tax_total ?? 0,
                'discount_total'     => $quote->discount_total ?? 0,
                'total'              => $quote->total ?? 0,
                'base_total'         => $quote->base_total ?? 0,
                'balance'            => $quote->total ?? 0,
            ]);

            if ($quote) {
                foreach ($quote->items as $i => $item) {
                    InvoiceItem::create([
                        'invoice_id'  => $inv->id,
                        'line_no'     => $i + 1,
                        'description' => $item->description,
                        'quantity'    => $item->quantity,
                        'unit'        => $item->unit,
                        'unit_rate'   => $item->unit_rate,
                        'tax_rate_id' => $item->tax_rate_id,
                        'line_total'  => $item->line_total,
                    ]);
                }
            }

            InvoiceEvent::log($inv->id, 'draft_made', $quote ? 'Copied from quote ' . $quote->quote_number : null);
            return $inv;
        });
    }

    $taxRates = TaxRate::orderBy('name')->get(['id', 'name', 'rate_percent']);

    return $this->builderView($invoice, $sr, $taxRates);
}
    public function edit(Invoice $invoice)
    {
        $this->guard();
        $this->ensureEditable($invoice);
        $sr = $invoice->serviceRequest;
        $taxRates = TaxRate::orderBy('name')->get(['id', 'name', 'rate_percent']);
        return $this->builderView($invoice, $sr, $taxRates);
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

        $tenant   = auth()->user()->tenant;
        $dc       = app(DocumentCurrency::class);
        $input    = $request->only(['currency_code', 'exchange_rate', 'rate_manual']);
        $hasQuote = $this->hasAcceptedQuote($invoice);

        // D5: once submitted, an invoice's currency and rate are fixed (RATE_LOCKED); a send-back makes it a draft again.
        if ($invoice->status !== 'draft') {
            $dc->resolve($tenant, $invoice, $input, true, $hasQuote);
        }
        $this->ensureEditable($invoice);

        $data = $request->validate([
            'due_date'              => 'required|date',
            'notes'                 => 'nullable|string|max:2000',
            'legal_invoice_number'  => 'nullable|string|max:80',
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
            'lines.*.source_expense_id' => 'nullable|integer',
        ], [
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

        // The currency and the rate (D4, D6): from the latest stored rate, or typed by Finance on this document.
        $cur = $dc->resolve($tenant, $invoice, $input, false, $hasQuote);
        if ($cur['changed'] && ! DocumentCurrency::mayChange(auth()->user(), 'invoice')) {
            abort(403);
        }

        // Lines made from expenses keep their link and the rate they were converted at (D8). Only links this invoice already has are kept.
        $fromExpense = $invoice->items()->whereNotNull('source_expense_id')->get()->keyBy('source_expense_id');

        // Totals worked out on the server with the arithmetic of blueprint 7.7a, in the document's currency.
        $lines  = array_values($data['lines']);
        $taxPct = TaxRate::whereIn('id', collect($lines)->pluck('tax_rate_id')->filter())->pluck('rate_percent', 'id');
        $calc   = DocumentTotals::compute(
            array_map(fn ($ln) => [
                'quantity' => $ln['quantity'], 'unit_rate' => $ln['unit_rate'],
                'tax_percent' => ! empty($ln['tax_rate_id']) ? ($taxPct[$ln['tax_rate_id']] ?? 0) : 0,
            ], $lines),
            $data['discount_type'], $data['discount_value'] ?? 0, $cur['currency_code'],
        );

        $built = [];
        foreach ($lines as $i => $ln) {
            $link = $fromExpense->get($ln['source_expense_id'] ?? 0);
            $built[] = [
                'line_no'           => $i + 1,
                'description'       => $ln['description'],
                'quantity'          => $ln['quantity'],
                'unit'              => $ln['unit'] ?? null,
                'unit_rate'         => $ln['unit_rate'],
                'tax_rate_id'       => $ln['tax_rate_id'] ?? null,
                'line_total'        => $calc['lines'][$i],
                'source_expense_id' => $link?->source_expense_id,
                'converted_at_rate' => $link?->converted_at_rate,
            ];
        }

        $old = DocumentCurrency::snapshot($invoice);

        DB::transaction(function () use ($invoice, $data, $built, $calc, $cur, $tenant) {
            $invoice->update([
                'due_date'              => $data['due_date'],
                'notes'                 => $data['notes'] ?? null,
                'legal_invoice_number'  => $data['legal_invoice_number'] ?? null,
                'discount_type'         => $data['discount_type'],
                'discount_value'        => $data['discount_value'] ?? 0,
                'currency_code'         => $cur['currency_code'],
                'exchange_rate'         => $cur['exchange_rate'],
                'rate_source'           => $cur['rate_source'],
                'subtotal'              => $calc['subtotal'],
                'tax_total'             => $calc['tax_total'],
                'discount_total'        => $calc['discount_total'],
                'total'                 => $calc['total'],
                'base_total'            => DocumentCurrency::baseTotal($calc['total'], $cur['exchange_rate'], $tenant),
                'balance'               => $calc['total'],
            ]);

            InvoiceItem::where('invoice_id', $invoice->id)->delete();
            foreach ($built as $row) {
                InvoiceItem::create(array_merge(['invoice_id' => $invoice->id], $row));
            }

            InvoiceEvent::log($invoice->id, 'saved');
        });

        if ($cur['changed']) {
            $dc->audit('invoice.rate_changed', $invoice, $tenant, $old, DocumentCurrency::snapshot($invoice->fresh()));
        }
        if ($request->input('and_submit')) {
            return $this->submit($invoice);
        }

        return redirect()->route('admin.invoices.edit', $invoice)
            ->with('success', 'Invoice saved.');
    }

    /* ========== CURRENCY, EXPENSES ========== */

    private function hasAcceptedQuote(Invoice $invoice): bool
    {
        return \App\Models\Quote::where('service_request_id', $invoice->service_request_id)->where('status', 'accepted')->exists();
    }

    /** The builder with the currency picker, the lock, and the approved expenses that can still be added (spec 5.2, D8). */
    private function builderView(Invoice $invoice, ServiceRequest $sr, $taxRates)
    {
        $tenant   = auth()->user()->tenant;
        $dc       = app(DocumentCurrency::class);
        $expenses = app(ExpenseLines::class);
        $invoice->load('items');
        $options  = $dc->options($tenant, $invoice->currency_code);

        return view('admin.invoices.build', array_merge(compact('invoice', 'sr', 'taxRates'), [
            'base'            => DocumentCurrency::base($tenant),
            'currencyOptions' => $options,
            'pickerAvailable' => $dc->pickerAvailable($tenant),
            'rateMap'         => $dc->rateMap($tenant, $options),
            'currencyFixed'   => $this->hasAcceptedQuote($invoice),
            'docLocked'       => $invoice->status !== 'draft',
            'expenseChoices'  => $expenses->eligible($invoice)->map(fn ($e) => ['expense' => $e, 'line' => $expenses->convert($e, $invoice, $tenant)]),
            'expenseNoteRate' => $expenses->staleRate($invoice),
        ]));
    }

    /** Add an approved expense to a draft invoice as a line, converted at the invoice's rate (D8, spec 6.6). */
    public function addExpense(Invoice $invoice, ServiceRequestExpense $expense)
    {
        $this->guard();
        $this->ensureEditable($invoice);

        $tenant = auth()->user()->tenant;
        $lines  = app(ExpenseLines::class);
        abort_unless($lines->eligible($invoice)->contains('id', $expense->id), 422, 'That expense cannot be added to this invoice.');

        DB::transaction(function () use ($invoice, $expense, $lines, $tenant) {
            $row = $lines->convert($expense, $invoice, $tenant);
            InvoiceItem::create([
                'invoice_id'        => $invoice->id,
                'line_no'           => $invoice->items()->count() + 1,
                'description'       => $row['description'],
                'quantity'          => $row['quantity'],
                'unit'              => null,
                'unit_rate'         => $row['unit_rate'],
                'tax_rate_id'       => null,
                'line_total'        => $row['line_total'],
                'source_expense_id' => $expense->id,
                'converted_at_rate' => $row['converted_at_rate'],
            ]);

            // The document's totals follow its lines. This is a draft, so it is allowed to be worked out again.
            $items = $invoice->items()->with('taxRate')->get();
            $calc  = DocumentTotals::compute(
                $items->map(fn ($i) => ['quantity' => $i->quantity, 'unit_rate' => $i->unit_rate, 'tax_percent' => $i->taxRate?->rate_percent ?? 0])->all(),
                $invoice->discount_type, $invoice->discount_value, $invoice->currency_code,
            );
            $invoice->update([
                'subtotal' => $calc['subtotal'], 'tax_total' => $calc['tax_total'], 'discount_total' => $calc['discount_total'],
                'total' => $calc['total'], 'balance' => $calc['total'],
                'base_total' => DocumentCurrency::baseTotal($calc['total'], $invoice->exchange_rate, $tenant),
            ]);

            InvoiceEvent::log($invoice->id, 'expense_added', $expense->description);
        });

        return redirect()->route('admin.invoices.edit', $invoice)->with('success', 'Expense added as a line.');
    }

    /* ========== UPLOAD PDF VARIANT ========== */

    public function uploadForm(ServiceRequest $sr)
    {
        $this->guard();
        if ($redirect = $this->readyToInvoiceOrRedirect($sr)) {
            return $redirect;
        }
        $sr->load(['client', 'site']);
        return view('admin.invoices.upload', compact('sr'));
    }

    public function storeUpload(Request $request, ServiceRequest $sr)
    {
        $this->guard();
        if ($redirect = $this->readyToInvoiceOrRedirect($sr)) {
            return $redirect;
        }

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
            'currency_code'      => auth()->user()->tenant?->currency_code ?? 'AED',
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

        // 5.8: the Reminders line needs the plan (billing_full) and the company's maximum.
        $tenant    = auth()->user()->tenant;
        $reminders = $tenant->hasFeature('billing_full') ? [
            'max'       => TenantSetting::forTenant($tenant->id)->overdue_max_reminders,
            'canChange' => PermissionLevel::canChange(auth()->user(), 'invoice.manage'),
        ] : null;
        $base = DocumentCurrency::base($tenant);

        return view('admin.invoices.show', compact('invoice', 'reminders', 'base'));
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
        $this->approveGuard();
    abort_unless($invoice->status === 'pending_approval', 422);

    if ($invoice->submitted_by_user_id === auth()->id()) {
        return redirect()->route('admin.invoices.show', $invoice)
            ->with('error', 'You submitted this invoice — ask another admin or Finance to approve it.');
    }

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

            // Note: the SR already reached 'awaiting_invoice' only after QC passed
            // it as out-of-coverage (see ensureReadyToInvoice() / RequestsReviewController::qcPass()),
            // so by the time an invoice is approved the job itself was genuinely
            // done — this no longer force-completes an unworked job.
            $sr = $invoice->serviceRequest;
            if ($sr && $sr->status === 'awaiting_invoice') {
                $sr->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);
            }
        });

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Invoice approved.');
    }

    public function sendBack(Request $request, Invoice $invoice)
    {
        $this->approveGuard();
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

          $token = InvoiceToken::create([
                'tenant_id'         => $invoice->tenant_id,
                'invoice_id'        => $invoice->id,
                'client_contact_id' => $contact?->id,
                'recipient_name'    => $name,
                'token'             => InvoiceToken::generate(),
                'sent_via'          => $via,
                'sent_at'           => now(),
            ]);

            if ($via === 'email' && $contact?->email) {
                \Illuminate\Support\Facades\Mail::to($contact->email)
                    ->send(new \App\Mail\InvoiceSentMail($token));
            }
        }

        $sr = $invoice->serviceRequest;

        if ($invoice->status === 'approved') {
            $invoice->update(['status' => 'sent', 'sent_at' => now()]);

            // 'approve()' already completes the SR once — this just keeps
            // billing_status in sync without re-forcing a status that was
            // already set correctly (or, if somehow still open, without
            // forcing it closed from here).
            if ($sr) {
                $sr->update(['billing_status' => 'invoiced']);
            }
        }
        InvoiceEvent::log($invoice->id, 'sent', 'Sent to ' . implode(', ', $request->recipients));

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Invoice sent.');
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $this->paymentGuard();
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
            InvoiceEvent::log($invoice->id, 'payment_received', ($invoice->currency_code ?? 'AED') . ' ' . number_format($data['amount'], 3));


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
        $this->paymentGuard();
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
        InvoiceEvent::log($invoice->id, 'written_off', ($invoice->currency_code ?? 'AED') . ' ' . number_format($amount, 3) . ' — ' . $data['reason']);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Balance written off.');
    }

    public function undoWriteOff(Request $request, Invoice $invoice)
    {
        $this->adminOnlyGuard();
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