@php
  // Sender (tenant) info — same source pattern as the public page
  $tenant = \App\Models\Tenant::current()
         ?? $quote->serviceRequest?->tenant
         ?? \App\Models\Tenant::find($quote->tenant_id);

  $senderName    = $tenant?->name ?? config('app.name');
  $senderLegal   = $tenant?->legal_name ?? $tenant?->full_name ?? null;
  $senderAddress = $tenant?->address
                 ?? $tenant?->billing_address
                 ?? $tenant?->city
                 ?? null;
  $senderTrn     = $tenant?->trn ?? $tenant?->tax_number ?? null;
  $senderLogo    = ($tenant?->logo_path ?? null) ? asset('storage/'.$tenant->logo_path) : null;

  $words  = preg_split('/\s+/', trim((string) $senderName));
  $badge  = strtoupper(substr($words[0] ?? '?', 0, 1) . substr($words[1] ?? '', 0, 1));
  if (! $badge) $badge = strtoupper(substr($senderName, 0, 2));

  // Buyer (client) info
  $client     = $quote->client;
  $site       = $quote->serviceRequest?->site;
  $clientAddr = $client?->address ?? $client?->billing_address ?? null;
  $clientTrn  = $client?->trn ?? $client?->tax_number ?? null;
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $quote->quote_number }}</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Figtree:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#1B1A24; --muted:#5F5D6E; --subtle:#8B89A0;
    --line:#E6E4EE; --soft:#F5F4FA;
    --accent:#2F5BEA; --accent-dark:#1E3FB8;
    --green:#00866F; --red:#C2381B;
  }
  *{box-sizing:border-box;margin:0;padding:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  body{font-family:'DM Sans',system-ui,sans-serif;color:var(--ink);font-size:14px;line-height:1.5;background:#fff;padding:40px 48px;max-width:900px;margin:0 auto}

  /* ===== BLUE HEADER CARD ===== */
  .hdr-card{
    background:linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
    color:#fff;
    border-radius:16px;
    padding:28px 32px;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
    margin-bottom:28px;
  }
  .hdr-card .co-name{font-family:'Figtree';font-weight:800;font-size:22px;letter-spacing:-.3px;margin-bottom:8px}
  .hdr-card .co-legal{font-size:14px;font-weight:500;opacity:.95;margin-bottom:4px}
  .hdr-card .co-addr{font-size:13.5px;opacity:.9;line-height:1.5}
  .hdr-card .co-trn{font-size:13px;opacity:.85;margin-top:4px}
  .hdr-card .logo{
    width:60px;height:60px;flex-shrink:0;
    background:rgba(255,255,255,.9);color:var(--accent);
    border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    font-family:'Figtree';font-weight:800;font-size:20px;
    overflow:hidden;
  }
  .hdr-card .logo img{width:100%;height:100%;object-fit:cover}

  /* ===== QUOTE TITLE + META GRID ===== */
  .quote-title{
    font-family:'Figtree';font-weight:800;font-size:28px;
    margin-bottom:14px;
  }
  .meta-grid{
    display:grid;grid-template-columns:1fr 1fr;gap:10px 32px;
    margin-bottom:22px;
    font-size:14px;
  }
  .meta-grid .lbl{font-weight:700;color:var(--ink);display:inline-block;min-width:84px}
  .meta-grid .val{color:var(--ink)}

  /* ===== TO CARD ===== */
  .to-card{
    background:var(--soft);
    border-radius:12px;
    padding:16px 20px;
    margin-bottom:26px;
    font-size:14px;
    line-height:1.65;
  }
  .to-card .co{font-family:'Figtree';font-weight:800;font-size:15px;margin-bottom:2px}
  .to-card .ln{color:var(--ink)}

  /* ===== LINES TABLE ===== */
  table.ln{width:100%;border-collapse:collapse;font-size:13.5px}
  table.ln th{
    text-align:left;padding:12px 0;
    font-size:11px;font-weight:700;color:var(--muted);
    text-transform:uppercase;letter-spacing:.08em;
    border-bottom:1.5px solid var(--ink);
  }
  table.ln th.r{text-align:right}
  table.ln td{padding:16px 0;border-bottom:1px solid var(--line);vertical-align:top}
  table.ln td.r{text-align:right}
  table.ln td.d{color:var(--ink);font-weight:500}

  /* ===== TOTALS ===== */
  .totals-wrap{display:flex;justify-content:flex-end;margin-top:18px}
  table.totals{min-width:300px;font-size:14px}
  table.totals td{padding:8px 0;color:var(--muted)}
  table.totals td.v{text-align:right;color:var(--ink);font-weight:600}
  table.totals tr.big td{
    font-family:'Figtree';font-weight:800;font-size:17px;
    color:var(--ink);
    border-top:2px solid var(--ink);
    padding-top:14px;
  }

  /* ===== TERMS ===== */
  .terms{
    clear:both;margin-top:30px;
    background:var(--soft);
    border-radius:10px;
    padding:14px 18px;
    font-size:13px;line-height:1.55;color:var(--muted);
  }
  .terms b{color:var(--ink);font-weight:700}

  /* ===== FOOTER ===== */
  .foot{
    margin-top:40px;padding-top:18px;
    border-top:1px solid var(--line);
    display:flex;justify-content:space-between;
    font-size:11.5px;color:var(--subtle);
  }

  @media print{
    body{padding:24px 32px;max-width:none}
    .no-print{display:none}
  }
</style>
</head>
<body>

{{-- ===== BLUE HEADER CARD ===== --}}
<div class="hdr-card">
  <div>
    <div class="co-name">{{ $senderName }}</div>
    @if ($senderLegal)
      <div class="co-legal">{{ $senderLegal }}</div>
    @endif
    @if ($senderAddress)
      <div class="co-addr">{{ $senderAddress }}</div>
    @endif
    @if ($senderTrn)
      <div class="co-trn">TRN {{ $senderTrn }}</div>
    @endif
  </div>
  <div class="logo">
    @if ($senderLogo)
      <img src="{{ $senderLogo }}" alt="{{ $senderName }}">
    @else
      {{ $badge }}
    @endif
  </div>
</div>

{{-- ===== QUOTE TITLE ===== --}}
<div class="quote-title">Quote</div>

<div class="meta-grid">
  <div><span class="lbl">Number</span> <span class="val">{{ $quote->quote_number }}</span></div>
  <div><span class="lbl">Date</span> <span class="val">{{ $quote->created_at->format('d M Y') }}</span></div>
  <div><span class="lbl">Valid until</span> <span class="val">{{ $quote->valid_until?->format('d M Y') ?? '—' }}</span></div>
  <div><span class="lbl">Request</span> <span class="val">{{ $quote->serviceRequest?->sr_number ?? '—' }}</span></div>
</div>

{{-- ===== TO CARD ===== --}}
<div class="to-card">
  <div class="co">{{ $client?->name ?? '—' }}</div>
  @if ($clientAddr)   <div class="ln">{{ $clientAddr }}</div> @endif
  @if ($clientTrn)    <div class="ln">TRN {{ $clientTrn }}</div> @endif
  @if ($site?->name)  <div class="ln">{{ $site->name }}@if ($site->city), {{ $site->city }}@endif</div> @endif
</div>

{{-- ===== LINES TABLE ===== --}}
<table class="ln">
  <thead>
    <tr>
      <th>Description</th>
      <th class="r">Qty</th>
      <th>Unit</th>
      <th class="r">Rate</th>
      <th>Tax</th>
      <th class="r">Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($quote->items as $it)
      <tr>
        <td class="d">{{ $it->description }}</td>
        <td class="r">{{ rtrim(rtrim(number_format($it->quantity,2),'0'),'.') }}</td>
        <td>{{ $it->unit }}</td>
        <td class="r">{{ number_format($it->unit_rate,2) }}</td>
        <td>{{ $it->taxRate?->name }}@if($it->taxRate) {{ rtrim(rtrim(number_format($it->taxRate->rate_percent,2),'0'),'.') }}%@endif</td>
        <td class="r">{{ number_format($it->line_total,2) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

{{-- ===== TOTALS ===== --}}
<div class="totals-wrap">
  <table class="totals">
    <tr><td>Subtotal</td><td class="v">AED {{ number_format($quote->subtotal,2) }}</td></tr>
    @if ($quote->discount_total > 0)
      <tr><td>Discount</td><td class="v">-AED {{ number_format($quote->discount_total,2) }}</td></tr>
    @endif
    <tr><td>Tax</td><td class="v">AED {{ number_format($quote->tax_total,2) }}</td></tr>
    <tr class="big"><td>Total</td><td class="v">AED {{ number_format($quote->total,2) }}</td></tr>
  </table>
</div>

{{-- ===== TERMS ===== --}}
@if ($quote->terms)
  <div class="terms"><b>Terms.</b> {{ $quote->terms }}</div>
@endif

{{-- ===== FOOTER ===== --}}
<div class="foot">
  <div>{{ $senderName }}</div>
  <div>{{ $quote->quote_number }} · Generated {{ now()->format('d M Y, H:i') }}</div>
</div>

<script>window.onload = () => window.print();</script>
</body>
</html>