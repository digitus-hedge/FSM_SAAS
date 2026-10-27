@extends('layouts.admin')
@section('title','Quotes')

@section('content')
<style>
  .page-h1{font-family:'Figtree';font-size:28px;font-weight:800;margin-bottom:6px}
  .page-sub{color:var(--muted);font-size:14px;margin-bottom:22px}
  .tab-strip{display:flex;gap:0;border-bottom:1px solid var(--line);margin-bottom:22px;}
  .tab-strip a{padding:12px 20px;color:var(--muted);text-decoration:none;font-weight:600;border-bottom:2px solid transparent;position:relative;top:1px;white-space:nowrap}
  .tab-strip a.active{color:var(--ink);border-bottom-color:var(--ink)}
  .tab-strip a .n{color:var(--muted);font-weight:400;margin-left:4px}
  .tab-strip a.active .n{color:var(--ink)}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  table.q{width:100%;border-collapse:collapse;font-size:13.5px}
  table.q th{text-align:left;padding:14px 16px;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line)}
  table.q td{padding:18px 16px;border-bottom:1px solid #F2F1F6;vertical-align:middle}
  table.q tr:last-child td{border-bottom:0}
  table.q tbody tr{cursor:pointer;transition:background .1s}
  table.q tbody tr:hover{background:#F8F7FB}
  .qnum{font-family:'Figtree';font-weight:800}
  .total{font-family:'Figtree';font-weight:700;white-space:nowrap}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:700}
  .p-draft{background:#F0EFF5;color:#5A5869}
  .p-sent{background:#EDE9FE;color:#5B3BB8}
  .p-accepted{background:#DDF7F1;color:#00866F}
  .p-rejected{background:#FDECE7;color:#C2381B}
  .p-expired{background:#FFF4CC;color:#8A5A00}
  .p-superseded{background:#F0EFF5;color:#777}
  .empty{padding:60px 20px;text-align:center;color:var(--muted)}

  /* ===== Floating simulator dock ===== */
  .sim-dock{position:fixed;right:20px;bottom:20px;display:flex;gap:8px;z-index:50}
  .sim-dock button{background:#fff;border:1px solid var(--line);border-radius:999px;padding:10px 16px;font-weight:700;font-family:inherit;font-size:13.5px;color:var(--ink);cursor:pointer;display:inline-flex;gap:8px;align-items:center;box-shadow:0 4px 16px rgba(0,0,0,.1)}
  .sim-dock button:hover{background:var(--soft)}
  .sim-dock .count{background:#FFF4CC;color:#8A5A00;padding:1px 7px;border-radius:999px;font-size:11px}

  .sim-panel{
  position:fixed;
  right:20px;
  bottom:76px;
  width:400px;
  height:calc(100vh - 110px);     /* fits viewport, leaves room for dock + top gap */
  max-height:720px;                /* don't get silly on huge screens */
  background:#fff;
  border:1px solid var(--line);
  border-radius:20px;
  box-shadow:0 20px 60px rgba(0,0,0,.18);
  overflow:hidden;
  display:none;
  z-index:51;
  flex-direction:column;
}
.sim-panel.open{display:flex}

  .sim-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--line);background:#fff;flex-shrink:0}
.sim-head h3{font-family:'Figtree';font-weight:800;font-size:15px;margin:0}
.sim-head .x{background:none;border:0;font-size:22px;cursor:pointer;color:var(--muted);padding:0 6px;line-height:1}

  .sim-picker{padding:10px 14px;border-bottom:1px solid var(--line);display:flex;flex-direction:column;gap:6px;flex-shrink:0}
.sim-picker .lbl{font-size:10.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-top:2px}
/* ===== Modern select styling for the simulator pickers ===== */
.sim-picker select{
  appearance:none;
  -webkit-appearance:none;
  -moz-appearance:none;
  width:100%;
  padding:11px 36px 11px 14px;
  border:1px solid var(--line);
  border-radius:12px;
  font-family:inherit;
  font-size:13.5px;
  font-weight:600;
  color:var(--ink);
  background:#fff;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235F5D6E' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat:no-repeat;
  background-position:right 14px center;
  background-size:12px;
  cursor:pointer;
  transition:border-color .15s, box-shadow .15s;
  line-height:1.2;
}
.sim-picker select:hover{
  border-color:#C7C4D5;
}
.sim-picker select:focus{
  outline:none;
  border-color:var(--accent);
  box-shadow:0 0 0 3px rgba(47,91,234,.15);
}

/* Smaller, tighter labels for the pickers */
.sim-picker .lbl{
  font-size:10.5px;
  font-weight:800;
  color:var(--muted);
  text-transform:uppercase;
  letter-spacing:.08em;
  margin:4px 2px 0;
}

/* Space between the two picker rows */
.sim-picker{
  padding:12px 14px;
  gap:4px;
}

 .sim-body{flex:1;background:#F0EFF5;overflow:hidden;padding:10px;min-height:0}
  .sim-body iframe{width:100%;height:100%;border:0;border-radius:12px;background:#fff}
  .sim-empty{color:var(--muted);text-align:center;padding:40px 20px;background:#fff;border-radius:12px;font-size:13.5px}
</style>

<h1 class="page-h1">Quotes</h1>
<p class="page-sub">Quotes for requests that were sent for a quote.</p>

<div class="tab-strip">
  <a href="{{ route('admin.quotes.index',['tab'=>'draft']) }}"    class="{{ $tab==='draft'?'active':'' }}">Draft    <span class="n">{{ $counts['draft'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'sent']) }}"     class="{{ $tab==='sent'?'active':'' }}">Sent     <span class="n">{{ $counts['sent'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'accepted']) }}" class="{{ $tab==='accepted'?'active':'' }}">Accepted <span class="n">{{ $counts['accepted'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'rejected']) }}" class="{{ $tab==='rejected'?'active':'' }}">Rejected <span class="n">{{ $counts['rejected'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'expired']) }}"  class="{{ $tab==='expired'?'active':'' }}">Expired  <span class="n">{{ $counts['expired'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'all']) }}"      class="{{ $tab==='all'?'active':'' }}">All      <span class="n">{{ $counts['all'] }}</span></a>
</div>

<div class="card">
  @if ($items->isEmpty())
    <div class="empty">
      <div style="font-family:'Figtree';font-weight:700;font-size:16px;color:var(--ink);margin-bottom:4px">Nothing here.</div>
      <div>No quotes in this tab.</div>
    </div>
  @else
    <table class="q">
      <thead>
        <tr>
          <th>Number</th>
          <th>Request</th>
          <th>Client</th>
          <th>Total</th>
          <th>Valid until</th>
          <th>Status</th>
          <th>Updated</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($items as $q)
          @php
            $openUrl = ($q->status === 'draft' && $q->source === 'builder')
                ? route('admin.quotes.edit', $q)
                : route('admin.quotes.show', $q);
          @endphp
          <tr onclick="window.location='{{ $openUrl }}'">
            <td class="qnum">{{ $q->quote_number }}</td>
            <td>{{ $q->serviceRequest?->sr_number ?? '—' }}</td>
            <td>{{ $q->client?->name ?? '—' }}</td>
            <td class="total">AED {{ number_format($q->total, 2) }}</td>
            <td>{{ $q->valid_until?->format('d M Y') ?? '—' }}</td>
            <td><span class="pill p-{{ $q->status }}">{{ ucfirst($q->status) }}</span></td>
            <td style="color:var(--muted)">{{ $q->updated_at->diffForHumans() }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

{{-- =========================================================
     FLOATING CLIENT-PHONE DOCK — simulator for any sent quote
     ========================================================= --}}
@php
  // All tokens from the most recent 15 sent quotes, grouped by quote number
  $recentTokens = \App\Models\QuoteToken::with(['quote:id,quote_number', 'contact:id,name'])
      ->whereHas('quote', fn($q) => $q->whereIn('status', ['sent','accepted','rejected']))
      ->latest('sent_at')
      ->limit(50)
      ->get()
      ->groupBy(fn($t) => $t->quote->quote_number ?? 'QT');
@endphp

<div class="sim-dock">
  <button type="button" onclick="simOpen()">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>
    </svg>
    Client phone
    @if ($recentTokens->isNotEmpty())
      <span class="count">{{ $recentTokens->count() }}</span>
    @endif
  </button>
</div>

<div class="sim-panel" id="sim-panel">
  <div class="sim-head">
    <h3>Client phone</h3>
    <button type="button" class="x" onclick="simClose()">×</button>
  </div>

  <div class="sim-picker">
    @if ($recentTokens->isEmpty())
      <div style="color:var(--muted);font-size:13px;text-align:center;padding:8px 0">
        No sent quotes yet. Send one first.
      </div>
    @else
      <div class="lbl">Quote</div>
     <select id="sim-quote" onchange="simRefreshRecipients()">
  @foreach ($recentTokens as $qNum => $tks)
    @php $clientName = $tks->first()?->quote?->client?->name; @endphp
    <option value="{{ $qNum }}">{{ $qNum }}@if ($clientName) · {{ $clientName }}@endif</option>
  @endforeach
</select>
</select>

      <div class="lbl">Recipient</div>
      <select id="sim-recipient" onchange="simLoad()"></select>
    @endif
  </div>

<div class="sim-body">
  {{-- MESSAGES VIEW (shown by default) --}}
<div id="sim-msg" style="background:#fff;border-radius:12px;padding:14px;box-shadow:0 2px 8px rgba(0,0,0,.04);height:100%;overflow-y:auto;display:flex;flex-direction:column;gap:4px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
      <strong id="sim-msg-channel" style="font-size:14px">—</strong>
      <span id="sim-msg-time" style="color:var(--muted);font-size:12px">—</span>
    </div>
    <div id="sim-msg-title" style="font-family:'Figtree';font-weight:800;font-size:14px;margin:6px 0;line-height:1.3">
      Your quote
    </div>
    <div id="sim-msg-body" style="font-size:13.5px;color:var(--ink);line-height:1.5;margin-bottom:14px">
      Loading…
    </div>
    <button type="button" id="sim-view-btn" onclick="simViewQuote()"
            style="background:#2F5BEA;color:#fff;padding:10px 22px;border-radius:999px;border:0;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;display:inline-block">
      View quote
    </button>
    <div id="sim-msg-foot" style="color:var(--muted);font-size:11.5px;margin-top:10px">—</div>
  </div>

  {{-- QUOTE VIEW (iframe, hidden until "View quote" is tapped) --}}
  <iframe id="sim-iframe" src="about:blank" style="display:none;width:100%;height:100%;border:0;border-radius:12px;background:#fff"></iframe>

  <div id="sim-empty" class="sim-empty" style="display:none">Pick a quote and recipient to preview.</div>
</div>
</div>

@php
  // Re-load with the client so we have the name available
$recentTokens = \App\Models\QuoteToken::with([
        'quote:id,quote_number,client_id,service_request_id,total,valid_until',
        'quote.client:id,name',
        'quote.serviceRequest:id,sr_number',
        'contact:id,name',
    ])->whereHas('quote', fn($q) => $q->whereIn('status', ['sent','accepted','rejected']))
      ->latest('sent_at')
      ->limit(50)
      ->get()
      ->groupBy(fn($t) => $t->quote->quote_number ?? 'QT');

  $simTokens = [];
  foreach ($recentTokens as $qNum => $tks) {
    $simTokens[$qNum] = $tks->map(fn($t) => [
    'token'          => $t->token,
    'recipient_name' => $t->recipient_name,
    'client_name'    => $t->quote?->client?->name ?? '—',
    'url'            => route('public.quote.show', $t->token),
    'channel'        => $t->sent_via === 'whatsapp' ? 'WhatsApp' : 'Email',
    'channel_note'   => $t->sent_via === 'whatsapp'
                         ? 'Delivered by WhatsApp'
                         : 'No WhatsApp number: email is the first choice',
    'sent_at'        => $t->sent_at?->format('d M, H:i') ?? '—',
    'sr_number'      => $t->quote?->serviceRequest?->sr_number ?? '—',
    'total'          => number_format($t->quote?->total ?? 0, 2),
    'valid_until'    => $t->quote?->valid_until?->format('d M Y') ?? '—',
])->values()->all();
  }
@endphp

<script>
  const SIM_TOKENS = @json($simTokens);

  function simOpen() {
    document.getElementById('sim-panel').classList.add('open');
    if (Object.keys(SIM_TOKENS).length === 0) {
      document.getElementById('sim-msg').style.display    = 'none';
      document.getElementById('sim-iframe').style.display = 'none';
      document.getElementById('sim-empty').style.display  = 'block';
      document.getElementById('sim-empty').textContent    = 'No sent quotes yet.';
      return;
    }
    simRefreshRecipients();
  }

  function simClose() {
    document.getElementById('sim-panel').classList.remove('open');
  }

  function simRefreshRecipients() {
    const qNum = document.getElementById('sim-quote').value;
    const list = SIM_TOKENS[qNum] || [];
    const sel  = document.getElementById('sim-recipient');
    sel.innerHTML = list.map(t => {
  const client = t.client_name && t.client_name !== '—' ? ` · ${t.client_name}` : '';
  return `<option value="${t.token}" data-url="${t.url}">${t.recipient_name}${client}</option>`;
}).join('');
    simShowMessage();  // default back to Messages view
  }

  function simCurrentToken() {
    const sel  = document.getElementById('sim-recipient');
    if (! sel.options.length) return null;
    const qNum  = document.getElementById('sim-quote').value;
    const token = sel.value;
    return (SIM_TOKENS[qNum] || []).find(t => t.token === token) || null;
  }

  // Default view: WhatsApp/Email bubble with "View quote" button
  function simShowMessage() {
    const t = simCurrentToken();
    if (! t) return;

    document.getElementById('sim-msg-channel').textContent = t.channel;
    document.getElementById('sim-msg-time').textContent    = t.sent_at;
    document.getElementById('sim-msg-title').textContent   = `Your quote for service request ${t.sr_number}`;
    document.getElementById('sim-msg-body').textContent    =
      `A quote for your service request ${t.sr_number} is ready. ` +
      `The total is AED ${t.total} and it is valid until ${t.valid_until}. ` +
      `Please review it and let us know your decision.`;
    document.getElementById('sim-msg-foot').textContent    = t.channel_note;

    document.getElementById('sim-msg').style.display    = 'block';
    document.getElementById('sim-iframe').style.display = 'none';
    document.getElementById('sim-empty').style.display  = 'none';
  }

  // Click "View quote" button → load the real client page in the iframe
  function simViewQuote() {
    const t = simCurrentToken();
    if (! t) return;
    document.getElementById('sim-iframe').src = t.url + '?r=' + Date.now();
    document.getElementById('sim-msg').style.display    = 'none';
    document.getElementById('sim-iframe').style.display = 'block';
  }

  // When recipient dropdown changes, go BACK to Messages view (realistic — each contact gets their own thread)
  // Already handled by simRefreshRecipients() -> simShowMessage()

  // When a client accepts/rejects inside the iframe, reload the outer list
  window.addEventListener('message', e => {
    if (e.data === 'client-responded') location.reload();
  });
</script>
@endsection