@extends('layouts.admin')
@section('title', $quote->quote_number)

@section('content')
<style>
  .crumb{color:var(--muted);font-size:13px;margin-bottom:4px}
  .crumb a{color:var(--ink);text-decoration:underline}
  .head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:4px;flex-wrap:wrap}
  h1.q{font-family:'Figtree';font-size:28px;font-weight:800}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;margin-left:8px}
  .p-sent{background:#EDE9FE;color:#5B3BB8}
  .p-draft{background:#F0EFF5;color:#5A5869}
  .p-accepted{background:#DDF7F1;color:#00866F}
  .p-rejected{background:#FDECE7;color:#C2381B}
  .p-superseded{background:#F0EFF5;color:#777}
  .sr-ref{font-size:14px;margin:4px 0 4px}
  .sr-ref a{color:var(--ink);text-decoration:underline;font-weight:700}
  .sub{color:var(--muted);font-size:13.5px;margin-bottom:18px}
  .actions{display:flex;gap:8px}
  .btn{padding:10px 18px;border-radius:10px;font-weight:700;font-size:13.5px;border:1px solid var(--line);background:#fff;color:var(--ink);text-decoration:none;cursor:pointer;font-family:inherit}
  .btn:hover{background:var(--soft)}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:14px}
  .upload-banner{background:#DDF7F1;border:1px solid #B9EADD;border-radius:12px;padding:14px 16px;display:flex;gap:10px;align-items:center;font-size:13.5px;margin-bottom:14px}
  table.ln{width:100%;font-size:14px}
  table.ln th{text-align:left;font-size:11.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700;padding:0 0 10px;border-bottom:1px solid var(--line)}
  table.ln td{padding:14px 0;border-bottom:1px solid #F2F1F6}
  table.ln tr:last-child td{border:0}
  .totals{display:flex;justify-content:flex-end;margin-top:16px}
  .totals table{min-width:280px;font-size:14px}
  .totals td{padding:5px 0}
  .totals .big{font-family:'Figtree';font-weight:800;font-size:16px;border-top:1px solid var(--line);padding-top:10px}
  .terms-box{background:#F8F7FB;border-radius:10px;padding:14px 16px;color:var(--ink);font-size:13.5px;margin-top:8px}
  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
  .hist-row{padding:10px 0;border-bottom:1px solid #F2F1F6;font-size:13.5px}
  .hist-row:last-child{border:0}
  .hist-row .t{color:var(--muted);display:inline-block;min-width:80px}
  .hist-row .u{color:var(--muted)}

  .btn.primary {
    background: #2F5BEA;
    color: #ffffff;
}

</style>

<div class="crumb"><a href="{{ route('admin.requests-review.index') }}">Quotes</a> / {{ $quote->quote_number }}</div>

<div class="head">
  <div>
    <h1 class="q">{{ $quote->quote_number }}
      <span class="pill p-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span>
    </h1>
    <div class="sr-ref">
      <a href="#">{{ $quote->serviceRequest?->sr_number }}</a>
      {{ $quote->serviceRequest?->title }}, {{ $quote->client?->name }}@if ($quote->serviceRequest?->site), {{ $quote->serviceRequest->site->name }}@endif
    </div>
    @if ($quote->valid_until)
      <div class="sub">Valid until {{ $quote->valid_until->format('d M Y') }}</div>
    @endif
  </div>
  <div class="actions">
    @if (in_array($quote->status, ['sent','rejected','superseded']))
      <form method="POST" action="{{ route('admin.quotes.revise', $quote) }}" style="margin:0">@csrf
        <button type="submit" class="btn">Revise</button>
      </form>
      <button type="button" class="btn" onclick="openSendSheet()">Resend</button>
      <a href="{{ route('admin.quotes.pdf', $quote) }}" target="_blank" class="btn">Download PDF</a>

      @elseif ($quote->status === 'accepted')
    <a href="{{ route('admin.invoices.build', $quote->service_request_id) }}"
       class="btn primary">
        Start invoice from this quote
    </a>

    <a href="{{ route('admin.quotes.pdf', $quote) }}"
       target="_blank"
       class="btn">
        Download PDF
    </a>


    @elseif ($quote->status === 'draft')
      @if ($quote->source === 'builder')
        <a href="{{ route('admin.quotes.edit', $quote) }}" class="btn">Edit</a>
      @endif
      <button type="button" class="btn" style="background:#2F5BEA;color:#fff;border-color:#2F5BEA" onclick="openSendSheet()">Send</button>
    @endif
  </div>
</div>

{{-- UPLOADED PDF BANNER --}}
@if ($quote->source === 'uploaded_pdf' && $quote->pdfAttachment)
  <div class="card" style="padding:0;overflow:hidden">
    <div class="upload-banner" style="margin:0;border-radius:0;border:0;border-bottom:1px solid #B9EADD">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Uploaded document:
      <a href="{{ asset('storage/'.$quote->pdfAttachment->storage_path) }}" target="_blank" style="color:var(--ink);text-decoration:underline;font-weight:700">{{ $quote->pdfAttachment->original_name }}</a>.
      Total entered: AED {{ number_format($quote->total,2) }}.
    </div>
    <div style="padding:22px;display:flex;justify-content:flex-end">
      <table style="min-width:280px;font-size:14px">
        <tr><td style="color:var(--muted);padding:5px 0">Subtotal</td><td style="text-align:right;padding:5px 0">AED {{ number_format($quote->subtotal,2) }}</td></tr>
        <tr><td style="color:var(--muted);padding:5px 0">Tax included</td><td style="text-align:right;padding:5px 0">AED {{ number_format($quote->tax_total,2) }}</td></tr>
        <tr><td style="font-family:'Figtree';font-weight:800;border-top:1px solid var(--line);padding:10px 0">Total</td><td style="font-family:'Figtree';font-weight:800;text-align:right;border-top:1px solid var(--line);padding:10px 0">AED {{ number_format($quote->total,2) }}</td></tr>
      </table>
    </div>
    @if ($quote->terms)
      <div style="padding:0 22px 22px"><div style="font-size:11.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:8px">Terms</div><div class="terms-box">{{ $quote->terms }}</div></div>
    @endif
  </div>

{{-- BUILDER LINES --}}
@else
  <div class="card">
    <table class="ln">
      <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Rate</th><th>Tax</th><th style="text-align:right">Total</th></tr></thead>
      <tbody>
        @foreach ($quote->items as $it)
          <tr>
            <td>{{ $it->description }}</td>
            <td>{{ rtrim(rtrim(number_format($it->quantity,2),'0'),'.') }}</td>
            <td>{{ $it->unit }}</td>
            <td>{{ number_format($it->unit_rate,2) }}</td>
            <td>{{ $it->taxRate?->name }} @if($it->taxRate) {{ rtrim(rtrim(number_format($it->taxRate->rate_percent,2),'0'),'.') }}%@endif</td>
            <td style="text-align:right">{{ number_format($it->line_total,2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <div class="totals">
      <table>
        <tr><td style="color:var(--muted)">Subtotal</td><td style="text-align:right">AED {{ number_format($quote->subtotal,2) }}</td></tr>
        <tr><td style="color:var(--muted)">Tax {{ $quote->subtotal > 0 ? rtrim(rtrim(number_format($quote->tax_total / $quote->subtotal * 100,0),'0'),'.') : '0' }}%</td><td style="text-align:right">AED {{ number_format($quote->tax_total,2) }}</td></tr>
        @if ($quote->discount_total > 0)
          <tr><td style="color:var(--muted)">Discount</td><td style="text-align:right">-AED {{ number_format($quote->discount_total,2) }}</td></tr>
        @endif
        <tr><td class="big">Total</td><td class="big" style="text-align:right">AED {{ number_format($quote->total,2) }}</td></tr>
      </table>
    </div>
    @if ($quote->terms)
      <div style="margin-top:18px"><div style="font-size:11.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-bottom:8px">Terms</div><div class="terms-box">{{ $quote->terms }}</div></div>
    @endif
  </div>
@endif

<div class="grid-2">
  <div class="card">
    <div style="font-family:'Figtree';font-weight:800;font-size:16px;margin-bottom:10px">Client response</div>
    @if ($quote->responded_at)
      <div style="font-size:13.5px">{{ ucfirst($quote->status) }} by {{ $quote->responded_by_name }}, {{ $quote->responded_at->format('d M, H:i') }}</div>
    @else
      <div style="color:var(--muted)">No answer yet.</div>
    @endif
  </div>
  <div class="card">
    <div style="font-family:'Figtree';font-weight:800;font-size:16px;margin-bottom:10px">Versions</div>
    <div style="font-size:13.5px">
      <strong>{{ $quote->quote_number }}</strong>
      <span class="pill p-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span>
      <span style="color:var(--muted)">latest</span>
    </div>
    @if ($quote->previousVersion)
      <div style="font-size:13.5px;color:var(--muted);margin-top:6px">Previous: {{ $quote->previousVersion->quote_number }}</div>
    @endif
  </div>
</div>

<div class="card">
  <div style="font-family:'Figtree';font-weight:800;font-size:16px;margin-bottom:10px">History</div>
  @forelse ($quote->events as $ev)
    <div class="hist-row">
      <span class="t">{{ $ev->occurred_at->format('d M, H:i') }}</span>
      <strong>
        @switch($ev->type)
          @case('draft_made') Draft made @break
          @case('saved')      Saved @break
          @case('sent')       {{ $ev->label ?? 'Sent' }} @break
          @case('downloaded') Downloaded @break
          @case('accepted')   Accepted @break
          @case('rejected')   Rejected @break
          @default            {{ ucfirst($ev->type) }}
        @endswitch
      </strong>
      <span class="u">({{ $ev->user?->name ?? '—' }})</span>
    </div>
  @empty
    <div style="color:var(--muted)">No events yet.</div>
  @endforelse
</div>

{{-- SEND SHEET --}}
<script>
function openSendSheet() {
  const contacts = @json($quote->client?->contacts ?? collect());
  const rows = contacts.map((c, i) => {
    const sub = c.whatsapp_number ? 'By WhatsApp' : (c.email ? 'By Email. No WhatsApp number: email is the first choice' : 'No way to reach this contact');
    const disabled = (!c.whatsapp_number && !c.email) ? 'disabled' : '';
    const checked = (!disabled && i === 0) ? 'checked' : '';
    return `
      <label style="display:flex;gap:10px;padding:12px 0;border-bottom:1px solid #F2F1F6;cursor:pointer">
        <input type="checkbox" name="recipients[]" value="${c.name}" ${checked} ${disabled} style="margin-top:3px;accent-color:#2F5BEA">
        <div>
          <div style="font-weight:800;font-size:14.5px">${c.name}</div>
          <div style="color:var(--muted);font-size:12.5px">${sub}</div>
        </div>
      </label>
    `;
  }).join('');

   const msg = `A quote for your service request {{ $quote->serviceRequest?->sr_number }} is ready. The total is AED {{ number_format($quote->total,2) }} and it is valid until {{ $quote->valid_until?->format('d M Y') }}. Please review it and let us know your decision.\n[View quote: a secure link is created per recipient]`;

  Swal.fire({
    title: 'Send quote',
    html: `
      <form id="send-form" method="POST" action="{{ route('admin.quotes.send', $quote) }}" style="text-align:left">
        @csrf
        <p style="color:var(--muted);font-size:13.5px;margin-bottom:12px">Choose who gets it. A new link replaces any link already sent.</p>
        ${rows || '<div style="color:var(--muted)">No contacts on file.</div>'}
        <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase;font-weight:700;margin:16px 0 6px">Message</div>
        <textarea name="message" style="width:100%;min-height:110px;padding:12px;border:1px solid var(--line);border-radius:10px;font-family:inherit;font-size:13.5px;background:#F8F7FB">${msg}</textarea>
      </form>
    `,
    showCancelButton: true, confirmButtonText: 'Send', cancelButtonText: 'Cancel',
    reverseButtons: true, customClass: { confirmButton: 'swal-btn-primary-blue' },
    preConfirm: () => {
      const picked = document.querySelectorAll('#send-form input[name="recipients[]"]:checked');
      if (!picked.length) { Swal.showValidationMessage('Pick at least one recipient.'); return false; }
      document.getElementById('send-form').submit();
      return false;
    }
  });
}

@if (session('open_send'))
  openSendSheet();
@endif
</script>
<style>.swal-btn-primary-blue{background:#2F5BEA !important;color:#fff !important}</style>



{{-- ===== CLIENT PHONE SIMULATOR ===== --}}
@php
  $tokens = \App\Models\QuoteToken::where('quote_id', $quote->id)->get();
@endphp

@if ($tokens->isNotEmpty())
<style>
  .sim-dock{position:fixed;right:20px;bottom:20px;display:flex;gap:8px;z-index:50}
  .sim-dock button{background:#fff;border:1px solid var(--line);border-radius:999px;padding:10px 16px;font-weight:700;font-family:inherit;font-size:13.5px;color:var(--ink);cursor:pointer;display:inline-flex;gap:8px;align-items:center;box-shadow:0 4px 16px rgba(0,0,0,.08)}
  .sim-dock button:hover{background:var(--soft)}
  .sim-dock .count{background:#FFF4CC;color:#8A5A00;padding:1px 7px;border-radius:999px;font-size:11px}

  .sim-panel{position:fixed;right:20px;bottom:70px;width:400px;height:640px;background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.18);overflow:hidden;display:none;z-index:51;flex-direction:column}
  .sim-panel.open{display:flex}
  .sim-head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--line);background:#fff}
  .sim-head h3{font-family:'Figtree';font-weight:800;font-size:15px}
  .sim-head .x{background:none;border:0;font-size:20px;cursor:pointer;color:var(--muted);padding:0 8px}
  .sim-picker{padding:10px 14px;border-bottom:1px solid var(--line)}
  .sim-picker select{width:100%;padding:10px;border:1px solid var(--line);border-radius:10px;font-family:inherit;font-size:13.5px}
  .sim-body{flex:1;background:#F0EFF5;overflow:hidden;padding:12px}
  .sim-body iframe{width:100%;height:100%;border:0;border-radius:12px;background:#fff}

  .sim-msg{padding:14px 16px;background:#F0EFF5;flex:1;overflow-y:auto}
  .sim-msg .card{background:#fff;border-radius:12px;padding:14px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
  .sim-msg .ch{color:var(--muted);font-size:12px;margin-bottom:4px;display:flex;justify-content:space-between}
  .sim-msg .ch strong{color:var(--ink)}
  .sim-msg .ttl{font-family:'Figtree';font-weight:800;font-size:14px;margin:6px 0}
  .sim-msg .body{font-size:13.5px;color:var(--ink);line-height:1.5;margin-bottom:12px}
  .sim-msg .vq{background:var(--accent);color:#fff;padding:8px 16px;border-radius:999px;border:0;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit}
</style>

<div class="sim-dock">
  <button type="button" onclick="simOpen('phone')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
    Client phone
  </button>
  <button type="button" onclick="simOpen('msg')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    Messages <span class="count">{{ $tokens->count() }}</span>
  </button>
</div>

<div class="sim-panel" id="sim-panel">
  <div class="sim-head">
    <h3 id="sim-title">Client phone</h3>
    <button type="button" class="x" onclick="simClose()">×</button>
  </div>

  {{-- CLIENT PICKER --}}
  <div class="sim-picker">
    <select id="sim-pick" onchange="simLoad()">
      @foreach ($tokens as $tk)
        <option value="{{ $tk->token }}" data-name="{{ $tk->recipient_name }}" data-sent="{{ $tk->sent_at?->format('d M, H:i') }}">
          {{ $tk->recipient_name }} ({{ $quote->client?->name }})
        </option>
      @endforeach
    </select>
  </div>

  {{-- PHONE VIEW --}}
  <div class="sim-body" id="sim-phone">
    <iframe id="sim-iframe" src=""></iframe>
  </div>

  {{-- MESSAGES VIEW --}}
  <div class="sim-msg" id="sim-messages" style="display:none">
    <div class="card">
      <div class="ch"><strong>WhatsApp</strong> <span id="sim-sent">—</span></div>
      <div class="ttl">Your quote for service request {{ $quote->serviceRequest?->sr_number }}</div>
      <div class="body">
        A quote for your service request {{ $quote->serviceRequest?->sr_number }} is ready.
        Please review it and let us know your decision.
      </div>
      <button type="button" class="vq" onclick="simOpen('phone')">View quote</button>
    </div>
  </div>
</div>

<script>
function simOpen(view) {
  document.getElementById('sim-panel').classList.add('open');
  document.getElementById('sim-title').textContent = view === 'msg' ? 'Messages' : 'Client phone';
  document.getElementById('sim-phone').style.display = view === 'msg' ? 'none' : 'block';
  document.getElementById('sim-messages').style.display = view === 'msg' ? 'block' : 'none';
  if (view !== 'msg') simLoad();
  else {
    const opt = document.getElementById('sim-pick').options[document.getElementById('sim-pick').selectedIndex];
    document.getElementById('sim-sent').textContent = opt.dataset.sent || 'Sent';
  }
}
function simClose() { document.getElementById('sim-panel').classList.remove('open'); }
function simLoad() {
  const token = document.getElementById('sim-pick').value;
  if (! token) return;
  document.getElementById('sim-iframe').src = @json(url('/q/')) + '/' + token + '?r=' + Date.now();
}
</script>
@endif


@endsection