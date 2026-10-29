@extends('layouts.admin')
@section('title', $sr->sr_number)

@section('content')
<style>
  .crumb{color:var(--muted);font-size:13px;margin-bottom:4px}
  .crumb a{color:var(--ink);text-decoration:underline}
  .page-h1{font-family:'Figtree';font-size:28px;font-weight:800;margin-bottom:6px}
  .sub-line{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:6px}
  .sr-tag{background:#F0EFF5;color:#5A5869;padding:2px 10px;border-radius:999px;font-family:'Figtree';font-weight:700;font-size:12px}
  .sub-txt{color:var(--ink);font-size:14px}
  .pills{display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap}
  .pill{display:inline-block;padding:4px 12px;border-radius:999px;font-size:12.5px;font-weight:700}
  .pill-pending{background:#E8E6EF;color:#5A5869}
  .pill-progress{background:#EDE9FE;color:#5B3BB8}
  .pill-review{background:#FFF4CC;color:#8A5A00}
  .pill-completed{background:#DDF7F1;color:#00866F}
  .pill-coverage{background:#F0EFF5;color:#5A5869}
  .pill-invoice-mandatory{background:#EDE9FE;color:#5B3BB8}
  .pill-na{background:#F0EFF5;color:#777}
  .pill-awaiting{background:#FFE8CC;color:#8A5A00}
  .pill-quoted{background:#EDE9FE;color:#5B3BB8}
  .pill-accepted{background:#DDF7F1;color:#00866F}
  .pill-cancelled{background:#FDECE7;color:#C2381B}

  .alert-amber{background:#FFF4E0;border:1px solid #F0D4A3;color:#8A5A00;padding:12px 16px;border-radius:12px;display:flex;gap:10px;align-items:center;font-size:13.5px;margin-bottom:18px}
  .alert-grey{background:#F0EFF5;border:1px solid #E6E4EE;color:#5A5869;padding:12px 16px;border-radius:12px;display:flex;gap:10px;align-items:center;font-size:13.5px;margin-bottom:18px}

  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px}
  .card h2{font-family:'Figtree';font-weight:800;font-size:17px;margin-bottom:14px}
  .card p{color:var(--muted);font-size:13.5px;margin-bottom:14px;line-height:1.5}
  .kv{display:grid;grid-template-columns:120px 1fr;gap:10px 20px;font-size:14px;margin-bottom:14px;align-items:center}
  .kv .k{color:var(--ink);font-weight:500}
  .kv .v{color:var(--ink)}

  .quote-link{color:#2F5BEA;text-decoration:underline;font-weight:600}
  .amount{font-weight:600;margin-left:4px}

  .btn{display:inline-block;padding:10px 20px;border-radius:12px;font-weight:700;text-decoration:none;font-size:13.5px;cursor:pointer;border:0;font-family:inherit}
  .btn-primary{background:#2F5BEA;color:#fff}
  .btn-primary:hover{background:#2548c0}
  .btn-outline{background:#fff;color:var(--ink);border:1px solid var(--line)}
  .btn-outline:hover{background:var(--soft)}
  .btn-outline-red{background:#fff;color:#C2381B;border:1px solid #F2C2C2}
  .btn-outline-red:hover{background:#FDECE7}

  .hist-row{padding:12px 0;border-bottom:1px solid #F2F1F6;font-size:13.5px}
  .hist-row:last-child{border-bottom:0}
  .hist-row .time{color:var(--muted);display:inline-block;min-width:110px}
  .history-empty{color:var(--muted);font-size:13.5px}
</style>

@php
  $statusMap = [
    'pending'          => ['pill-pending',   'Pending'],
    'in_progress'      => ['pill-progress',  'In Progress'],
    'pending_review'   => ['pill-review',    'Pending Review'],
    'completed'        => ['pill-completed', 'Completed'],
    'quoted'           => ['pill-quoted',    'Quoted'],
    'awaiting_invoice' => ['pill-awaiting',  'Awaiting Invoice'],
    'cancelled'        => ['pill-cancelled', 'Cancelled'],
  ];
  $st = $statusMap[$sr->status] ?? ['pill-pending', ucfirst(str_replace('_',' ', $sr->status))];

  $clientSite = trim(
      ($sr->client?->name ?? '')
      . ($sr->site ? ', ' . $sr->site->name : ''),
      ', '
  );

  $isPendingReview = $sr->status === 'pending_review';
  $isAwaitingInv   = $sr->billing_status === 'awaiting_invoice';
  $isInvoiced      = $sr->billing_status === 'invoiced';
  $isCancelled     = $sr->status === 'cancelled';

  // Safe route check — invoice builder may not exist yet
  $invoiceRoute = \Route::has('admin.invoices.build')
      ? route('admin.invoices.build', $sr->id)
      : null;
@endphp

<div class="crumb"><a href="{{ route('admin.requests-review.index') }}">Requests</a> / {{ $sr->sr_number }}</div>
<h1 class="page-h1">{{ $sr->title ?? 'Service request' }}</h1>

<div class="sub-line">
  <span class="sr-tag">{{ $sr->sr_number }}</span>
  <span class="sub-txt">{{ $clientSite }}</span>
</div>

<div class="pills">
  <span class="pill {{ $st[0] }}">{{ $st[1] }}</span>
  <span class="pill pill-coverage">Out of coverage</span>
  @if (! empty($sr->invoice_mandatory))
    <span class="pill pill-invoice-mandatory">Invoice mandatory</span>
  @endif
</div>

{{-- STATE BANNERS --}}
@if ($isAwaitingInv)
  <div class="alert-amber">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Needs an invoice. Only invoice approval completes this job.
  </div>
@elseif ($isPendingReview)
  <div class="alert-amber">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    Awaiting QC check. Pass QC to move this job to Awaiting Invoice.
  </div>
@elseif ($isCancelled)
  <div class="alert-grey">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    This request was cancelled.
  </div>
@endif

<div class="grid-2">

  {{-- ===== LEFT — BILLING CARD ===== --}}
  <div class="card">
    <h2>Billing</h2>

    <div class="kv">
      <div class="k">Billing status</div>
      <div class="v">
      @php
  $bs = $sr->billing_status;
  if ($bs === 'awaiting_invoice')  { $bsCls = 'pill-awaiting';  $bsLbl = 'Pending invoice'; }
  elseif ($bs === 'invoiced')      { $bsCls = 'pill-quoted';    $bsLbl = 'Invoiced'; }
  elseif ($bs === 'paid')          { $bsCls = 'pill-completed'; $bsLbl = 'Paid'; }
  else                             { $bsCls = 'pill-na';        $bsLbl = 'Not applicable'; }
@endphp
        <span class="pill {{ $bsCls }}">{{ $bsLbl }}</span>
      </div>

      <div class="k">Quote</div>
      <div class="v">
        @if ($latestQuote)
          <a href="{{ route('admin.quotes.show', $latestQuote) }}" class="quote-link">{{ $latestQuote->quote_number }}</a>
          @if ($latestQuote->status === 'accepted')
            <span class="pill pill-accepted" style="margin-left:8px">Accepted</span>
          @elseif ($latestQuote->status === 'rejected')
            <span class="pill pill-cancelled" style="margin-left:8px">Rejected</span>
          @elseif ($latestQuote->status === 'sent')
            <span class="pill pill-quoted" style="margin-left:8px">Sent</span>
          @elseif ($latestQuote->status === 'draft')
            <span class="pill pill-na" style="margin-left:8px">Draft</span>
          @endif
          @if ($latestQuote->total > 0)
            <span class="amount">AED {{ number_format($latestQuote->total, 2) }}</span>
          @endif
        @else
          <span style="color:var(--muted)">None</span>
        @endif
      </div>

      <div class="k">Invoice</div>
<div class="v">
  @if ($latestInvoice)
    <a href="{{ route('admin.invoices.show', $latestInvoice) }}" class="quote-link">
      {{ $latestInvoice->invoice_number }}
    </a>

    @php
      // Map invoice status → pill class + label
      $invMap = [
        'draft'            => ['pill-na',        'Draft'],
        'pending_approval' => ['pill-review',    'Pending approval'],
        'approved'         => ['pill-quoted',    'Approved'],
        'sent'             => ['pill-quoted',    'Sent'],
        'partially_paid'   => ['pill-awaiting',  'Partially paid'],
        'paid'             => ['pill-accepted',  'Paid'],
        'overdue'          => ['pill-cancelled', 'Overdue'],
        'written_off'      => ['pill-na',        'Written off'],
        'void'             => ['pill-cancelled', 'Void'],
      ];
      $iv = $invMap[$latestInvoice->status] ?? ['pill-na', ucfirst($latestInvoice->status)];
    @endphp
    <span class="pill {{ $iv[0] }}" style="margin-left:8px">{{ $iv[1] }}</span>

    @if ($latestInvoice->total > 0)
      <span class="amount">AED {{ number_format($latestInvoice->total, 2) }}</span>
    @endif

 
  @else
    <span style="color:var(--muted)">None</span>
  @endif
</div>
    </div>

    {{-- ACTION BUTTON — state-driven --}}
   {{-- ACTION BUTTON — state-driven. Pending Review always wins: no billing action until QC passes. --}}
@if ($isCancelled)
  <button type="button" class="btn btn-outline" disabled style="opacity:.6;cursor:default">Cancelled</button>

@elseif ($sr->status === 'in_progress')
  {{-- Technician is still working — no billing action yet --}}

@elseif ($isPendingReview)
  <button type="button" class="btn btn-outline" disabled style="opacity:.6;cursor:default">
    Pass QC first
  </button>

@elseif ($isAwaitingInv)
  @if ($latestInvoice)
    {{-- An invoice already exists — jump straight to it --}}
    <a href="{{ route('admin.invoices.show', $latestInvoice) }}" class="btn btn-primary">View invoice</a>
  @elseif ($invoiceRoute)
    <a href="{{ $invoiceRoute }}" class="btn btn-primary">Make invoice</a>
  @else
    <button type="button" class="btn btn-primary" onclick="demoInvoice()">Make invoice</button>
  @endif

@elseif ($isInvoiced)
  @if ($latestInvoice)
    <a href="{{ route('admin.invoices.show', $latestInvoice) }}" class="btn btn-primary">View invoice</a>
  @else
    <button type="button" class="btn btn-outline" disabled style="opacity:.7;cursor:default">Invoice issued</button>
  @endif

@elseif ($draft)
  <a href="{{ $draft->source === 'builder' ? route('admin.quotes.edit', $draft) : route('admin.quotes.show', $draft) }}" class="btn btn-primary">Open quote</a>

@elseif ($latestQuote && in_array($latestQuote->status, ['sent','accepted','rejected']))
  {{-- A quote already exists and has gone out — just let them view it --}}
  <a href="{{ route('admin.quotes.show', $latestQuote) }}" class="btn btn-primary">View quote</a>

@elseif (
    in_array($sr->billing_status, ['not_applicable','pending_quote'])
    && ! in_array($sr->status, ['quoted','cancelled','completed'])
)
  <button type="button" class="btn btn-primary" onclick="openMakeQuote({{ $sr->id }})">Make quote</button>
@endif
  </div>

  {{-- ===== RIGHT — DEMO CARD (state-aware button) ===== --}}
  <div class="card">
  <h2>what the Service request module would do</h2>
  <p>These buttons stand in for actions in other modules, so you can see how billing reacts.</p>

@if ($isCancelled)
  <span style="color:var(--muted);font-size:13.5px">This request has been cancelled.</span>

@elseif ($sr->status === 'in_progress')
  <span style="color:var(--muted);font-size:13.5px">Technician is on site. Buttons will appear once the job is punched out.</span>

@elseif ($isPendingReview)
  <button type="button" class="btn btn-outline" onclick="openQcPass()">
    QC pass
  </button>
  <form id="qc-pass-form" method="POST" action="{{ route('admin.requests-review.qc-pass', $sr) }}" style="display:none">
    @csrf
    <input type="hidden" name="note" id="qc-pass-note">
  </form>

  @elseif ($isAwaitingInv)
    {{-- Awaiting invoice — no demo button; Make invoice lives in the Billing card --}}
    <span style="color:var(--muted);font-size:13.5px">Waiting for invoice. Use <strong>Make invoice</strong> on the left.</span>

  @elseif ($isInvoiced)
    <span style="color:var(--muted);font-size:13.5px">This request is fully closed.</span>

  @else
    {{-- Other states (Pending / In Progress / Quoted) can still be cancelled --}}


  <button type="button" class="btn btn-outline-red" onclick="openCancelRequest()">
  Cancel the request
</button>
<form id="cancel-form" method="POST" action="{{ route('admin.requests-review.cancel', $sr) }}" style="display:none">
  @csrf
  <input type="hidden" name="reason" id="cancel-reason">
</form>
  @endif
</div>

</div>

{{-- ===== STATUS HISTORY ===== --}}
<div class="card">
  <h2>Status history</h2>

  @php
    $events = [];

    if ($isInvoiced) {
        $events[] = [
            'from' => 'Awaiting Invoice',
            'to'   => 'Invoiced',
            'at'   => $sr->updated_at,
            'note' => 'Invoice issued',
        ];
    } elseif ($isAwaitingInv) {
        $events[] = [
            'from' => 'Pending Review',
            'to'   => 'Awaiting Invoice',
            'at'   => $sr->updated_at,
            'note' => 'QC pass',
        ];
    } elseif ($isCancelled) {
        $events[] = [
            'from' => ucfirst(str_replace('_',' ', $sr->status === 'cancelled' ? 'pending_review' : $sr->status)),
            'to'   => 'Cancelled',
            'at'   => $sr->updated_at,
            'note' => 'Request cancelled',
        ];
    } elseif ($isPendingReview) {
        $events[] = [
            'from' => 'In Progress',
            'to'   => 'Pending Review',
            'at'   => $sr->updated_at,
            'note' => 'Technician punched out',
        ];
    }
  @endphp

  @if (empty($events))
    <div class="history-empty">No history yet.</div>
  @else
    @foreach ($events as $ev)
      <div class="hist-row">
        {{ $ev['from'] }} to <strong>{{ $ev['to'] }}</strong>
        <span class="time" style="margin-left:12px">{{ $ev['at']?->format('d M, H:i') }}</span>
        @if (! empty($ev['note']))
          <span style="color:var(--muted)">, {{ $ev['note'] }}</span>
        @endif
      </div>
    @endforeach
  @endif
</div>

{{-- ===== JS ===== --}}
<script>
function openMakeQuote(srId) {
  Swal.fire({
    title: 'Make a quote',
    html: `
      <div style="text-align:left">
        <p style="color:var(--muted);margin-bottom:16px;font-size:13.5px">How do you want to make it?</p>
        <label style="display:flex;gap:12px;padding:14px 2px;cursor:pointer">
          <input type="radio" name="mq" value="build" checked style="margin-top:3px;accent-color:#2F5BEA">
          <div>
            <div style="font-weight:800;font-size:14.5px">Build the quote</div>
            <div style="color:var(--muted);font-size:12.5px;margin-top:2px">Add lines, tax and a discount. A branded PDF is made when you send it.</div>
          </div>
        </label>
        <label style="display:flex;gap:12px;padding:14px 2px;cursor:pointer">
          <input type="radio" name="mq" value="upload" style="margin-top:3px;accent-color:#2F5BEA">
          <div>
            <div style="font-weight:800;font-size:14.5px">Upload a PDF</div>
            <div style="color:var(--muted);font-size:12.5px;margin-top:2px">Use a quote you already made. You enter the total.</div>
          </div>
        </label>
      </div>`,
    showCancelButton: true, confirmButtonText: 'Continue', cancelButtonText: 'Cancel',
    reverseButtons: true, customClass: { confirmButton: 'swal-btn-primary-blue' },
    preConfirm: () => document.querySelector('input[name="mq"]:checked').value
  }).then(function (r) {
    if (r.isConfirmed) {
      var buildUrl  = "{{ url('/admin/quotes/build') }}";
      var uploadUrl = "{{ url('/admin/quotes/upload') }}";
      window.location = (r.value === 'build' ? buildUrl : uploadUrl) + '/' + srId;
    }
  });
}

function demoInvoice() {
  Swal.fire({
    icon: 'info',
    title: 'Invoice module not built yet',
    text: 'When the Invoice module is ready, this button will open the invoice builder for this request. The quote and expenses would be pre-filled.',
    confirmButtonText: 'Got it'
  });
}

function demoGoodwill() {
  Swal.fire({
    icon: 'info',
    title: 'Demo only',
    text: 'In the real Service request module, this would change the coverage to Goodwill, drop the invoice requirement, and close the request without billing.',
    confirmButtonText: 'Got it'
  });
}

function openQcPass() {
  const srNumber = @json($sr->sr_number);
  Swal.fire({
    icon: 'question',
    title: 'QC pass ' + srNumber + '?',
    text:  'The job will move to Awaiting Invoice and billing can issue the invoice.',
    showCancelButton: true,
    confirmButtonText: 'QC pass',
    cancelButtonText: 'Close',
    reverseButtons: true,
    customClass: { confirmButton: 'swal-btn-green' },
    width: 460,
  }).then(function (r) {
    if (r.isConfirmed) {
      document.getElementById('qc-pass-form').submit();
    }
  });
}


function openCancelRequest() {
  const srNumber = @json($sr->sr_number);
  Swal.fire({
    title: 'Cancel request ' + srNumber,
    text:  'This cannot be undone.',
    input: 'textarea',
    inputLabel: 'Reason *',
    inputPlaceholder: 'e.g. Client postponed to next quarter',
    inputAttributes: {
      'aria-label': 'Cancellation reason',
      'style': 'height:120px;resize:vertical'
    },
    showCancelButton: true,
    confirmButtonText: 'Cancel request',
    cancelButtonText: 'Close',
    reverseButtons: true,
    customClass: { confirmButton: 'swal-btn-red' },
    inputValidator: function (value) {
      if (! value || ! value.trim())  return 'Please give a reason.';
      if (value.trim().length < 3)    return 'Please give at least 3 characters.';
      return null;
    },
    width: 480,
  }).then(function (r) {
    if (r.isConfirmed) {
      document.getElementById('cancel-reason').value = r.value.trim();
      document.getElementById('cancel-form').submit();
    }
  });
}

</script>
<style>.swal-btn-primary-blue{background:#2F5BEA !important;color:#fff !important}</style>
@endsection