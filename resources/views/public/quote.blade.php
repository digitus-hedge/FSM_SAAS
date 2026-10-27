<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $quote->quote_number }}</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Figtree:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root{--ink:#1B1A24;--muted:#5F5D6E;--line:#E6E4EE;--soft:#F8F7FB;--accent:#2F5BEA;--green:#00866F;--red:#C2381B}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#F0EFF5;font-family:'DM Sans',sans-serif;color:var(--ink);font-size:14px}
  .wrap{max-width:420px;margin:0 auto;background:#fff;min-height:100vh;overflow:hidden}
  .hdr{background:var(--accent);color:#fff;padding:18px 20px;display:flex;align-items:center;gap:12px}
  .hdr .logo{width:38px;height:38px;background:#fff;color:var(--accent);border-radius:9px;display:flex;align-items:center;justify-content:center;font-weight:800;font-family:'Figtree';font-size:14px;flex-shrink:0;overflow:hidden}
  .hdr .logo img{width:100%;height:100%;object-fit:cover}
  .hdr .co{font-family:'Figtree';font-weight:800;font-size:16px;line-height:1.25;min-width:0}
  .hdr .co .addr{font-family:'DM Sans';font-size:11.5px;font-weight:400;opacity:.9;margin-top:2px;line-height:1.3}
  .back{text-align:center;padding:14px 20px;font-weight:700;color:var(--ink);text-decoration:underline;font-size:14px;display:block}
  .body{padding:0 20px 28px}
  h1{font-family:'Figtree';font-weight:800;font-size:22px;margin-bottom:4px}
  .meta{color:var(--muted);font-size:13.5px;line-height:1.5;margin-bottom:18px}
  table.ln{width:100%;border-collapse:collapse;font-size:13.5px;margin-top:10px}
  table.ln th{text-align:left;padding:10px 0;font-size:11.5px;color:var(--muted);font-weight:700;border-bottom:1px solid var(--line)}
  table.ln td{padding:14px 0;border-bottom:1px solid #F2F1F6;vertical-align:top}
  table.ln td.r,.ln th.r{text-align:right}
  .totals{margin-top:14px;font-size:14px}
  .totals .row{display:flex;justify-content:space-between;padding:6px 0}
  .totals .total{font-family:'Figtree';font-weight:800;font-size:16px;border-top:1px solid var(--line);padding-top:12px;margin-top:4px}
  .terms{margin-top:18px;color:var(--muted);font-size:13px;line-height:1.5}
  .terms b{color:var(--ink)}
  .btn{display:block;width:100%;padding:14px;border:1px solid var(--line);background:#fff;color:var(--ink);text-align:center;border-radius:10px;font-weight:700;font-family:inherit;font-size:14.5px;cursor:pointer;text-decoration:none;margin-top:10px}
  .btn-primary{background:var(--accent);color:#fff;border:0}
  .btn-reject{background:#fff;color:var(--ink)}
  .msg-success{background:#DDF7F1;border:1px solid #B9EADD;color:var(--green);padding:12px 14px;border-radius:10px;margin-top:12px;font-size:13.5px;display:flex;gap:8px;align-items:flex-start}
  .msg-error{background:#FDECE7;border:1px solid #F2C2C2;color:var(--red);padding:12px 14px;border-radius:10px;margin-top:12px;font-size:13.5px}
  .accept-box{margin-top:16px;padding-top:14px;border-top:1px solid var(--line)}
  .accept-box h3{font-family:'Figtree';font-weight:800;font-size:16px;margin-bottom:10px}
  .fld{margin-bottom:12px}
  .fld label{display:block;font-weight:700;font-size:13.5px;margin-bottom:6px}
  .req{color:var(--red)}
  .fld input[type=text], .fld textarea{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-family:inherit;font-size:14px}
  .chk{display:flex;gap:8px;align-items:flex-start;padding:8px 0;cursor:pointer}
  .chk input{margin-top:3px;accent-color:var(--accent);width:16px;height:16px}
  .err{color:var(--red);font-size:12.5px;margin-top:4px}
  .btn-row{display:flex;gap:8px;margin-top:10px}
  .btn-row .btn{flex:1}
</style>
</head>
<body>

@php
  // ---- Dynamic sender (tenant) header ----
  $tenant = \App\Models\Tenant::current()
         ?? $quote->serviceRequest?->tenant
         ?? \App\Models\Tenant::find($quote->tenant_id);

  $senderName    = $tenant?->name ?? config('app.name');
  $senderAddress = $tenant?->address
                 ?? $tenant?->billing_address
                 ?? $tenant?->city
                 ?? null;
  $senderLogoUrl = ($tenant?->logo_path ?? null)
                 ? asset('storage/'.$tenant->logo_path)
                 : null;

  // Fallback 2-letter badge from name (A + F → "AF")
  $words = preg_split('/\s+/', trim((string) $senderName));
  $badge = strtoupper(substr($words[0] ?? '?', 0, 1) . substr($words[1] ?? '', 0, 1));
  if (! $badge) $badge = strtoupper(substr($senderName, 0, 2));
@endphp

<div class="wrap">
  <div class="hdr">
    <div class="logo">
      @if ($senderLogoUrl)
        <img src="{{ $senderLogoUrl }}" alt="{{ $senderName }}">
      @else
        {{ $badge }}
      @endif
    </div>
    <div class="co">
      <div>{{ $senderName }}</div>
      @if ($senderAddress)
        <div class="addr">{{ $senderAddress }}</div>
      @endif
    </div>
  </div>
  <a href="#" class="back" onclick="history.back();return false">Back to messages</a>

  <div class="body">
    <h1>{{ $quote->quote_number }}</h1>
    <div class="meta">
      Valid until {{ $quote->valid_until?->format('d M Y') }}<br>
      {{ $quote->serviceRequest?->title }}@if ($quote->serviceRequest?->site), {{ $quote->serviceRequest->site->name }}@endif
    </div>

    <table class="ln">
      <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Total</th></tr></thead>
      <tbody>
        @foreach ($quote->items as $it)
          <tr>
            <td>{{ $it->description }}</td>
            <td class="r">{{ rtrim(rtrim(number_format($it->quantity,2),'0'),'.') }}</td>
            <td class="r">{{ number_format($it->unit_rate,2) }}</td>
            <td class="r">{{ number_format($it->line_total,2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="totals">
      <div class="row"><span>Subtotal</span><strong>AED {{ number_format($quote->subtotal,2) }}</strong></div>
      @if ($quote->discount_total > 0)
        <div class="row"><span>Discount</span><strong>-AED {{ number_format($quote->discount_total,2) }}</strong></div>
      @endif
      <div class="row"><span>Tax {{ $quote->subtotal > 0 ? rtrim(rtrim(number_format($quote->tax_total/$quote->subtotal*100,0),'0'),'.') : '0' }}%</span><strong>AED {{ number_format($quote->tax_total,2) }}</strong></div>
      <div class="row total"><span>Total</span><span>AED {{ number_format($quote->total,2) }}</span></div>
    </div>

    @if ($quote->terms)
      <div class="terms"><b>Terms.</b> {{ $quote->terms }}</div>
    @endif

    <a href="{{ route('public.quote.pdf', $t->token) }}" target="_blank" class="btn">Download PDF</a>

    @if (session('accepted') || $quote->status === 'accepted')
      <div class="msg-success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="margin-top:1px"><polyline points="20 6 9 17 4 12"/></svg>
        <div>Thank you. You accepted this quote on {{ $quote->responded_at?->format('d M, H:i') }}.</div>
      </div>
      @if (session('accepted') || session('rejected'))
        <script>if (window.parent !== window) window.parent.postMessage('client-responded','*');</script>
      @endif
    @elseif (session('rejected') || $quote->status === 'rejected')
      <div class="msg-error">You rejected this quote on {{ $quote->responded_at?->format('d M, H:i') }}.</div>
      <script>if (window.parent !== window) window.parent.postMessage('client-responded','*');</script>
    @elseif ($quote->status === 'sent')

      <div class="btn-row">
        <button type="button" class="btn btn-primary" onclick="toggle('accept')">Accept</button>
        <button type="button" class="btn btn-reject" onclick="toggle('reject')">Reject</button>
      </div>

      <form id="accept" method="POST" action="{{ route('public.quote.accept', $t->token) }}" class="accept-box" style="display:none">
        @csrf
        <h3>Accept this quote</h3>
        <div class="fld">
          <label>Your full name <span class="req">*</span></label>
          <input type="text" name="name" value="{{ old('name', $t->recipient_name) }}" required>
          @error('name')<div class="err">{{ $message }}</div>@enderror
        </div>
        <label class="chk">
          <input type="checkbox" name="confirmed" value="1" {{ old('confirmed') ? 'checked' : '' }}>
          <span>I am allowed to accept this quote for {{ $quote->client?->name }}</span>
        </label>
        @error('confirmed')<div class="err">{{ $message }}</div>@enderror
        <div class="btn-row">
          <button type="button" class="btn" onclick="toggle(null)">Back</button>
          <button type="submit" class="btn btn-primary">Accept</button>
        </div>
      </form>

      <form id="reject" method="POST" action="{{ route('public.quote.reject', $t->token) }}" class="accept-box" style="display:none">
        @csrf
        <h3>Reject this quote</h3>
        <div class="fld">
          <label>Your full name <span class="req">*</span></label>
          <input type="text" name="name" value="{{ old('name', $t->recipient_name) }}" required>
          @error('name')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="fld">
          <label>Reason (optional)</label>
          <textarea name="reason" rows="3"></textarea>
        </div>
        <div class="btn-row">
          <button type="button" class="btn" onclick="toggle(null)">Back</button>
          <button type="submit" class="btn btn-reject">Reject</button>
        </div>
      </form>

    @else
      <div class="msg-error">This quote is not available anymore ({{ $quote->status }}).</div>
    @endif
  </div>
</div>

<script>
function toggle(which) {
  ['accept','reject'].forEach(id => document.getElementById(id).style.display = (id === which ? 'block' : 'none'));
  if (which) document.getElementById(which).scrollIntoView({behavior:'smooth',block:'start'});
}
</script>
</body>
</html>