@extends('layouts.admin')
@section('title', $invoice->invoice_number)
@section('content')
<style>
.crumb{color:var(--muted);font-size:13px;margin-bottom:10px}
.crumb a{color:var(--ink);text-decoration:none;font-weight:600}
.hero{background:#fff;border:1px solid var(--line);border-radius:14px;padding:24px;margin-bottom:16px;display:grid;grid-template-columns:1fr auto;gap:20px}
.hero h1{font-family:'Figtree';font-size:22px;font-weight:800;margin-bottom:2px}
.hero .meta{color:var(--muted);font-size:13px;margin-top:8px;display:flex;gap:14px;flex-wrap:wrap}
.hero .meta b{color:var(--ink);font-weight:600}
.hero-totals{text-align:right;min-width:180px}
.hero-totals .big{font-family:'Figtree';font-size:28px;font-weight:800;line-height:1.1}
.hero-totals .big-lbl{font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-top:4px}
.hero-totals .bal{color:var(--amber);font-weight:700;margin-top:10px;font-size:14px}
.hero-totals .bal.paid{color:var(--green)}
.action-bar{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 20px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.cols{display:grid;grid-template-columns:1fr 320px;gap:16px}
@media (max-width:900px){.cols{grid-template-columns:1fr}}
.panel{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-bottom:16px}
.panel-h{padding:14px 18px;border-bottom:1px solid var(--line);font-family:'Figtree';font-weight:700;font-size:14px}
.panel-b{padding:16px 18px}
.lines{width:100%;border-collapse:collapse;font-size:13.5px}
.lines th{text-align:left;padding:10px 16px;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;background:#F5F4FA;font-weight:700}
.lines td{padding:12px 16px;border-top:1px solid var(--line);font-variant-numeric:tabular-nums}
.lines .r{text-align:right}
.tot-row{display:flex;justify-content:space-between;padding:8px 18px;font-size:14px;font-variant-numeric:tabular-nums}
.tot-row.sub{border-top:1px solid var(--line);padding-top:12px}
.tot-row.tot{font-family:'Figtree';font-weight:800;font-size:17px;border-top:2px solid var(--ink);padding:14px 18px;margin-top:4px}
.tot-row.pay{color:var(--green)}
.tot-row.bal{color:var(--amber);font-weight:700}
.pay-tbl{width:100%;border-collapse:collapse;font-size:13px}
.pay-tbl th{text-align:left;padding:10px 16px;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;background:#F5F4FA}
.pay-tbl td{padding:11px 16px;border-top:1px solid var(--line);font-variant-numeric:tabular-nums}
.pay-tbl .r{text-align:right}
.pay-reversed{color:var(--muted);text-decoration:line-through}
.ev{padding:12px 18px;border-top:1px solid var(--line);display:flex;gap:10px;font-size:13px}
.ev:first-child{border-top:0}
.ev .dot{width:8px;height:8px;border-radius:50%;background:#FFC61A;margin-top:6px;flex-shrink:0}
.btn{display:inline-flex;align-items:center;gap:6px;background:#FFC61A;color:#1B1A24;font-weight:700;font-size:13px;padding:9px 18px;border-radius:999px;border:0;cursor:pointer;font-family:inherit;text-decoration:none}
.btn:hover{background:#F0B700}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--ink);font-weight:600}
.btn.ghost:hover{background:#F5F4FA}
.btn.danger{background:#C2381B;color:#fff}
.btn.blue{background:#2F5BEA;color:#fff}
.btn.blue:hover{background:#1E47D0}
.pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;vertical-align:middle}
.p-draft{background:#F0EFF5;color:#5A5869}
.p-pending_approval{background:#EDE9FE;color:#5B3BB8}
.p-approved{background:#E3EBF4;color:#2B5A8C}
.p-sent,.p-partially_paid{background:#FFF4CC;color:#8A5A00}
.p-paid{background:#DDF7F1;color:#00866F}
.p-overdue{background:#FDECE7;color:#C2381B}
.p-written_off,.p-void{background:#F0EFF5;color:#777;text-decoration:line-through}
</style>

<div class="crumb"><a href="{{ route('admin.invoices.index') }}">Invoices</a> / {{ $invoice->invoice_number }}</div>

<div class="hero">
  <div>
    <h1>{{ $invoice->invoice_number }}
      <span class="pill p-{{ $invoice->status }}">{{ ucfirst(str_replace('_',' ',$invoice->status)) }}</span>
    </h1>
    <div class="meta"><div><b>{{ $invoice->serviceRequest?->sr_number }}</b> · {{ $invoice->serviceRequest?->title ?? '—' }}</div></div>
    <div class="meta">
      <div><b>{{ $invoice->client?->name }}</b></div>
      @if ($invoice->due_date)<div>Due <b>{{ $invoice->due_date->format('d M Y') }}</b></div>@endif
      @if ($invoice->issued_at)<div>Issued {{ $invoice->issued_at->format('d M Y') }}</div>@endif
      @if ($invoice->legal_invoice_number)<div>Legal # {{ $invoice->legal_invoice_number }}</div>@endif
    </div>
  </div>
  <div class="hero-totals">
    <div class="big-lbl">Total</div>
    <div class="big">AED {{ number_format($invoice->total, 2) }}</div>
    @if ($invoice->balance > 0)
      <div class="bal">Balance AED {{ number_format($invoice->balance, 2) }}</div>
    @else
      <div class="bal paid">{{ $invoice->status === 'paid' ? 'Paid in full' : ($invoice->status === 'written_off' ? 'Written off' : '—') }}</div>
    @endif
  </div>
</div>

<div class="action-bar">
  @if ($invoice->status === 'draft')
    <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn">Edit</a>
    <form method="POST" action="{{ route('admin.invoices.submit', $invoice) }}" style="display:inline">@csrf
      <button class="btn ghost">Submit for approval</button>
    </form>
  @endif

  @if ($invoice->status === 'pending_approval')
    <form method="POST" action="{{ route('admin.invoices.approve', $invoice) }}" style="display:inline">@csrf
      <button class="btn">Approve</button>
    </form>

    <!-- <form method="POST" action="{{ route('admin.invoices.send-back', $invoice) }}" style="display:inline" onsubmit="return (this.reason.value=prompt('Reason for sending back?','')) !== null">@csrf
      <input type="hidden" name="reason">
      <button class="btn ghost">Send back</button>
    </form> -->


    <button type="button" class="btn ghost" onclick="openSendBack()">Send back</button>
<form id="sendback-form" method="POST" action="{{ route('admin.invoices.send-back', $invoice) }}" style="display:none">
  @csrf
  <input type="hidden" name="reason" id="sendback-reason">
</form>

  @endif

  @if ($invoice->status === 'approved')
    <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" style="display:inline">@csrf
      @foreach (($invoice->client?->contacts ?? []) as $c)
        <input type="hidden" name="recipients[]" value="{{ $c->name }}">
      @endforeach
      <button class="btn blue">Send to client</button>
    </form>
  @endif

  @if (in_array($invoice->status, ['sent','partially_paid','overdue']))
    <button class="btn blue" onclick="document.getElementById('pay-modal').style.display='flex'">Record payment</button>
   <button type="button" class="btn ghost" onclick="openWriteOff()">Write off balance</button>
<form id="writeoff-form" method="POST" action="{{ route('admin.invoices.write-off', $invoice) }}" style="display:none">
  @csrf
  <input type="hidden" name="reason" id="writeoff-reason">
</form>
    <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" style="display:inline">@csrf
      @foreach (($invoice->client?->contacts ?? []) as $c)
        <input type="hidden" name="recipients[]" value="{{ $c->name }}">
      @endforeach
      <button class="btn ghost">Resend</button>
    </form>
  @endif

  @if ($invoice->status === 'written_off')
    <form method="POST" action="{{ route('admin.invoices.undo-write-off', $invoice) }}" style="display:inline">@csrf
      <button class="btn ghost">Undo write-off</button>
    </form>
  @endif

  <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn ghost" target="_blank">Download PDF</a>

  @if ($invoice->status !== 'void')
    <span style="margin-left:auto"></span>

  <button type="button" class="btn danger" onclick="openVoid()">Void</button>
<form id="void-form" method="POST" action="{{ route('admin.invoices.void', $invoice) }}" style="display:none">
  @csrf
  <input type="hidden" name="reason" id="void-reason">
</form>

    
  @endif
</div>

<div class="cols">
  <div>
    <div class="panel">
      <div class="panel-h">Line items</div>
      <table class="lines">
        <thead><tr><th>#</th><th>Description</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Tax</th><th class="r">Line total</th></tr></thead>
        <tbody>
          @foreach ($invoice->items as $it)
            <tr>
              <td>{{ $it->line_no }}</td>
              <td>{{ $it->description }}</td>
              <td class="r">{{ number_format($it->quantity, 2) }}</td>
              <td class="r">{{ number_format($it->unit_rate, 3) }}</td>
              <td class="r">{{ $it->taxRate?->rate_percent ? $it->taxRate->rate_percent . '%' : '—' }}</td>
              <td class="r">{{ number_format($it->line_total, 3) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="tot-row sub"><span>Subtotal</span><span>AED {{ number_format($invoice->subtotal, 3) }}</span></div>
      @if ($invoice->discount_total > 0)
        <div class="tot-row"><span>Discount</span><span>— {{ number_format($invoice->discount_total, 3) }}</span></div>
      @endif
      <div class="tot-row"><span>Tax</span><span>AED {{ number_format($invoice->tax_total, 3) }}</span></div>
      <div class="tot-row tot"><span>Total</span><span>AED {{ number_format($invoice->total, 3) }}</span></div>
      @php $paid = $invoice->payments->whereNull('reversed_at')->sum('amount'); @endphp
      <div class="tot-row pay"><span>Paid</span><span>AED {{ number_format($paid, 3) }}</span></div>
      <div class="tot-row bal {{ $invoice->balance <= 0 ? 'paid' : '' }}">
        <span>Balance due</span><span>AED {{ number_format($invoice->balance, 3) }}</span>
      </div>
    </div>

    <div class="panel">
      <div class="panel-h">Payments</div>
      @if ($invoice->payments->isEmpty())
        <div style="padding:36px 20px;text-align:center;color:var(--muted);font-size:13px">No payments recorded yet.</div>
      @else
        <table class="pay-tbl">
          <thead><tr><th>Payment #</th><th>Received</th><th>Method</th><th>Reference</th><th class="r">Amount</th><th></th></tr></thead>
          <tbody>
            @foreach ($invoice->payments as $p)
              <tr class="{{ $p->reversed_at ? 'pay-reversed' : '' }}">
                <td style="font-family:'JetBrains Mono',monospace;font-size:12px">{{ $p->payment_number }}</td>
                <td>{{ $p->received_at->format('d M Y') }}</td>
                <td>{{ ucfirst(str_replace('_',' ',$p->method)) }}</td>
                <td style="color:var(--muted)">{{ $p->reference ?? '—' }}</td>
                <td class="r">AED {{ number_format($p->amount, 3) }}</td>
                <td style="text-align:right">
                  @if (! $p->reversed_at)
                    <button type="button" class="btn ghost" style="padding:4px 10px;font-size:11px" onclick="openReverse({{ $p->id }}, '{{ $p->payment_number }}')">Reverse</button>
<form id="reverse-form-{{ $p->id }}" method="POST" action="{{ route('admin.invoices.payment.reverse', $p) }}" style="display:none">
  @csrf
  <input type="hidden" name="reason" id="reverse-reason-{{ $p->id }}">
</form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    @if ($invoice->notes)
      <div class="panel">
        <div class="panel-h">Notes</div>
        <div class="panel-b" style="font-size:13.5px;color:var(--muted);white-space:pre-line">{{ $invoice->notes }}</div>
      </div>
    @endif
  </div>

  <div>
    <div class="panel">
      <div class="panel-h">Event log</div>
      @foreach ($invoice->events as $e)
        <div class="ev"><div class="dot"></div>
          <div>
            <div><b>{{ ucfirst(str_replace('_',' ',$e->type)) }}</b>{{ $e->label ? ' — ' . $e->label : '' }}</div>
            <div style="color:var(--muted);font-size:11.5px;margin-top:2px">
              {{ $e->user?->name ?? 'System' }} · {{ $e->occurred_at->format('d M Y, H:i') }}
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>

<!-- Payment modal -->
<div id="pay-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;max-width:520px;width:100%;max-height:90vh;overflow:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
      <h2 style="font-family:'Figtree';font-size:17px;font-weight:800">Record payment · {{ $invoice->invoice_number }}</h2>
      <button onclick="document.getElementById('pay-modal').style.display='none'" style="background:none;border:0;font-size:22px;cursor:pointer;color:var(--muted)">×</button>
    </div>
    <form method="POST" action="{{ route('admin.invoices.payment', $invoice) }}">
      @csrf
      <div style="padding:20px 24px">
        <div style="padding:12px 14px;background:#F5F4FA;border-radius:10px;margin-bottom:16px;display:flex;justify-content:space-between">
          <div><div style="color:var(--muted);font-size:11px;font-weight:700;text-transform:uppercase">Balance due</div><div style="font-family:'Figtree';font-weight:800;font-size:20px;color:var(--amber)">AED {{ number_format($invoice->balance, 2) }}</div></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div><label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase">Amount *</label><input name="amount" value="{{ number_format($invoice->balance, 2, '.', '') }}" required style="width:100%;height:42px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font-weight:700;text-align:right;margin-top:4px"></div>
          <div><label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase">Received *</label><input type="date" name="received_at" value="{{ now()->format('Y-m-d') }}" required style="width:100%;height:42px;padding:0 14px;border:1px solid var(--line);border-radius:10px;margin-top:4px"></div>
        </div>
        <div style="margin-bottom:14px"><label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase">Method *</label>
          <select name="method" required style="width:100%;height:42px;padding:0 14px;border:1px solid var(--line);border-radius:10px;margin-top:4px">
            <option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="cheque">Cheque</option><option value="card">Card</option><option value="other">Other</option>
          </select></div>
        <div style="margin-bottom:14px"><label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase">Reference</label><input name="reference" placeholder="UTR, cheque #, receipt ref" style="width:100%;height:42px;padding:0 14px;border:1px solid var(--line);border-radius:10px;margin-top:4px"></div>
        <div><label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase">Internal note</label><textarea name="note" style="width:100%;min-height:60px;padding:10px 14px;border:1px solid var(--line);border-radius:10px;font-family:inherit;margin-top:4px"></textarea></div>
      </div>
      <div style="padding:14px 24px;border-top:1px solid var(--line);display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn ghost" onclick="document.getElementById('pay-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn blue">Record payment</button>
      </div>
    </form>
  </div>
</div>

<script>
const INV_NUMBER = @json($invoice->invoice_number);

/* ===== SEND BACK ===== */
function openSendBack() {
  Swal.fire({
    title: 'Send back',
    html: '<div style="color:#5F5D6E;font-size:13.5px;margin-bottom:4px">Finance will see the reason.</div>',
    input: 'textarea',
    inputLabel: 'Reason',
    inputPlaceholder: '',
    inputAttributes: { 'aria-label': 'Reason', style: 'min-height:120px;resize:vertical' },
    showCancelButton: true,
    confirmButtonText: 'Send back',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    customClass: { confirmButton: 'swal-btn-blue' },
    inputValidator: v => (!v || !v.trim()) ? 'Please give a reason.' : (v.trim().length < 3 ? 'At least 3 characters.' : null),
    width: 520,
  }).then(r => {
    if (r.isConfirmed) {
      document.getElementById('sendback-reason').value = r.value.trim();
      document.getElementById('sendback-form').submit();
    }
  });
}

/* ===== WRITE OFF ===== */
function openWriteOff() {
  Swal.fire({
    icon: 'warning',
    title: 'Write off balance',
    html: '<div style="color:#5F5D6E;font-size:13.5px;margin-bottom:4px">The remaining balance will be marked as written off.</div>',
    input: 'textarea',
    inputLabel: 'Reason',
    inputPlaceholder: 'e.g. Client declared bankruptcy',
    inputAttributes: { style: 'min-height:120px;resize:vertical' },
    showCancelButton: true,
    confirmButtonText: 'Write off',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    customClass: { confirmButton: 'swal-btn-amber' },
    inputValidator: v => (!v || !v.trim()) ? 'Please give a reason.' : (v.trim().length < 3 ? 'At least 3 characters.' : null),
    width: 520,
  }).then(r => {
    if (r.isConfirmed) {
      document.getElementById('writeoff-reason').value = r.value.trim();
      document.getElementById('writeoff-form').submit();
    }
  });
}

/* ===== VOID ===== */
function openVoid() {
  Swal.fire({
    icon: 'error',
    title: 'Void ' + INV_NUMBER,
    html: '<div style="color:#C2381B;font-size:13.5px;margin-bottom:4px;font-weight:600">This cannot be undone.</div>',
    input: 'textarea',
    inputLabel: 'Reason',
    inputPlaceholder: 'e.g. Issued in error',
    inputAttributes: { style: 'min-height:120px;resize:vertical' },
    showCancelButton: true,
    confirmButtonText: 'Void invoice',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    customClass: { confirmButton: 'swal-btn-red' },
    inputValidator: v => (!v || !v.trim()) ? 'Please give a reason.' : (v.trim().length < 3 ? 'At least 3 characters.' : null),
    width: 520,
  }).then(r => {
    if (r.isConfirmed) {
      document.getElementById('void-reason').value = r.value.trim();
      document.getElementById('void-form').submit();
    }
  });
}

/* ===== REVERSE PAYMENT ===== */
function openReverse(paymentId, paymentNumber) {
  Swal.fire({
    icon: 'warning',
    title: 'Reverse ' + paymentNumber,
    html: '<div style="color:#5F5D6E;font-size:13.5px;margin-bottom:4px">The payment will be marked as reversed and the invoice balance will go back up.</div>',
    input: 'textarea',
    inputLabel: 'Reason',
    inputPlaceholder: 'e.g. Bounced cheque',
    inputAttributes: { style: 'min-height:110px;resize:vertical' },
    showCancelButton: true,
    confirmButtonText: 'Reverse payment',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    customClass: { confirmButton: 'swal-btn-red' },
    inputValidator: v => (!v || !v.trim()) ? 'Please give a reason.' : (v.trim().length < 3 ? 'At least 3 characters.' : null),
    width: 520,
  }).then(r => {
    if (r.isConfirmed) {
      document.getElementById('reverse-reason-' + paymentId).value = r.value.trim();
      document.getElementById('reverse-form-' + paymentId).submit();
    }
  });
}
</script>

<style>
  .swal-btn-blue{background:#2F5BEA !important;color:#fff !important;border-radius:999px !important;padding:10px 22px !important;font-weight:700 !important}
  .swal-btn-amber{background:#F0B700 !important;color:#1B1A24 !important;border-radius:999px !important;padding:10px 22px !important;font-weight:700 !important}
  .swal-btn-red{background:#C2381B !important;color:#fff !important;border-radius:999px !important;padding:10px 22px !important;font-weight:700 !important}
  .swal2-popup{border-radius:16px !important;padding:24px !important}
  .swal2-title{font-family:'Figtree',sans-serif !important;font-weight:800 !important;font-size:19px !important;text-align:left !important;padding:0 0 2px !important}
  .swal2-html-container{text-align:left !important;padding:0 !important;margin-top:0 !important}
  .swal2-input-label{text-align:left !important;font-weight:700 !important;font-size:13px !important;color:#1B1A24 !important;margin-top:14px !important}
  .swal2-textarea{border:1.5px solid #2F5BEA !important;border-radius:10px !important;font-family:inherit !important;font-size:14px !important;padding:12px 14px !important;box-shadow:0 0 0 3px rgba(47,91,234,.12) !important}
  .swal2-actions{justify-content:flex-end !important;margin-top:18px !important}
  .swal2-cancel.swal2-styled{background:#fff !important;color:#1B1A24 !important;border:1px solid #E6E4EE !important;border-radius:999px !important;padding:10px 22px !important;font-weight:600 !important}
</style>
@endsection