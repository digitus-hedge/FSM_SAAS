@php
  // ---- Sender (tenant) ----
  $tenant = \App\Models\Tenant::current()
         ?? $invoice->serviceRequest?->tenant
         ?? \App\Models\Tenant::find($invoice->tenant_id);

  $senderName    = $tenant?->name ?? config('app.name');
  $senderLegal   = $tenant?->legal_name ?? $tenant?->full_name ?? null;
  $senderAddress = $tenant?->address ?? $tenant?->billing_address ?? $tenant?->city ?? null;
  $senderTrn     = $tenant?->trn ?? $tenant?->tax_number ?? null;
  $senderLogo    = ($tenant?->logo_path ?? null) ? asset('storage/'.$tenant->logo_path) : null;

  $words  = preg_split('/\s+/', trim((string) $senderName));
  $badge  = strtoupper(substr($words[0] ?? '?', 0, 1) . substr($words[1] ?? '', 0, 1));
  if (! $badge) $badge = strtoupper(substr($senderName, 0, 2));

  // ---- Buyer (client) ----
  $client     = $invoice->client;
  $site       = $invoice->serviceRequest?->site;
  $clientAddr = $client?->address ?? $client?->billing_address ?? null;
  $clientTrn  = $client?->trn ?? $client?->tax_number ?? null;
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $invoice->invoice_number }}</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Figtree:wght@700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#1B1A24; --muted:#5F5D6E; --subtle:#8B89A0;
    --line:#E6E4EE; --soft:#F5F4FA;
    --accent:#2F5BEA; --accent-dark:#1E3FB8;
    --green:#00866F; --amber:#8A5A00; --red:#C2381B;
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

  /* ===== INVOICE TITLE + META GRID ===== */
  .doc-title{
  font-family:'Figtree';font-weight:800;font-size:28px;
  margin-bottom:14px;
  display:flex;
  align-items:baseline;   /* was: center */
  gap:10px;
  line-height:1;
}
  .status-pill{
    display:inline-block;padding:4px 12px;border-radius:999px;
    font-size:12.5px;font-weight:700;letter-spacing:.02em;
  }
  .p-draft{background:#F0EFF5;color:#5A5869}
  .p-pending{background:#FFF4CC;color:#8A5A00}
  .p-approved{background:#EDE9FE;color:#5B3BB8}
  .p-sent{background:#EDE9FE;color:#5B3BB8}
  .p-partial{background:#FFF0D1;color:#8A5A00}
  .p-paid{background:#DDF7F1;color:#00866F}
  .p-overdue{background:#FDECE7;color:#C2381B}
  .p-void{background:#FDECE7;color:#C2381B}

  .meta-grid{
    display:grid;grid-template-columns:1fr 1fr;gap:10px 32px;
    margin-bottom:22px;
    font-size:14px;
  }
  .meta-grid .lbl{font-weight:700;color:var(--ink);display:inline-block;min-width:92px}
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
  table.ln td.r{text-align:right;font-variant-numeric:tabular-nums}
  table.ln td.d{color:var(--ink);font-weight:500}
  table.ln td.n{color:var(--muted);width:30px}

  /* ===== TOTALS ===== */
  .totals-wrap{display:flex;justify-content:flex-end;margin-top:18px}
  table.totals{min-width:300px;font-size:14px}
  table.totals td{padding:8px 0;color:var(--muted)}
  table.totals td.v{text-align:right;color:var(--ink);font-weight:600;font-variant-numeric:tabular-nums}
  table.totals tr.big td{
    font-family:'Figtree';font-weight:800;font-size:17px;
    color:var(--ink);
    border-top:2px solid var(--ink);
    padding-top:14px;
  }
  table.totals tr.balance td{
    font-weight:700;color:var(--amber);
    border-top:1px dashed var(--line);
    padding-top:12px;font-size:14.5px;
  }
  table.totals tr.balance.paid td{color:var(--green)}

  /* ===== PAID STAMP ===== */
  .paid-stamp{
    position:absolute;top:120px;right:60px;
    border:3px solid var(--green);color:var(--green);
    padding:8px 20px;font-family:'Figtree';font-weight:800;font-size:28px;
    letter-spacing:.08em;transform:rotate(-14deg);border-radius:8px;
    opacity:.85;
  }

  /* ===== TERMS / NOTES / PAY INFO ===== */
  .terms{
    clear:both;margin-top:30px;
    background:var(--soft);
    border-radius:10px;
    padding:14px 18px;
    font-size:13px;line-height:1.55;color:var(--muted);
  }
  .terms b{color:var(--ink);font-weight:700}
  .pay-info{
    margin-top:14px;
    background:#F0F4FE;border:1px solid #D7E0FA;
    border-radius:10px;padding:14px 18px;
    font-size:13px;color:var(--ink);line-height:1.6;
  }
  .pay-info .ttl{font-family:'Figtree';font-weight:800;font-size:13.5px;margin-bottom:4px;color:var(--accent)}

  /* ===== FOOTER ===== */
  .foot{
    margin-top:40px;padding-top:18px;
    border-top:1px solid var(--line);
    display:flex;justify-content:space-between;
    font-size:11.5px;color:var(--subtle);
  }
  .cc{
    text-align:center;margin-top:16px;
    font-size:10px;color:var(--subtle);
    text-transform:uppercase;letter-spacing:.14em;
  }

  @media print{
    body{padding:24px 32px;max-width:none}
    .no-print{display:none}
  }
</style>
</head>
<body style="position:relative">

{{-- ===== PAID STAMP (overlay) ===== --}}
@if ($invoice->status === 'paid')
  <div class="paid-stamp">PAID</div>
@elseif ($invoice->status === 'void')
  <div class="paid-stamp" style="color:var(--red);border-color:var(--red)">VOID</div>
@endif

{{-- ===== BLUE HEADER CARD ===== --}}
<div class="hdr-card">
  <div>
    <div class="co-name">{{ $senderName }}</div>
    @if ($senderLegal) <div class="co-legal">{{ $senderLegal }}</div> @endif
    @if ($senderAddress) <div class="co-addr">{{ $senderAddress }}</div> @endif
    @if ($senderTrn) <div class="co-trn">TRN {{ $senderTrn }}</div> @endif
  </div>
  <div class="logo">
    @if ($senderLogo)
      <img src="{{ $senderLogo }}" alt="{{ $senderName }}">
    @else
      {{ $badge }}
    @endif
  </div>
</div>

{{-- ===== INVOICE TITLE + STATUS PILL ===== --}}
@php
  $statusMap = [
    'draft'            => ['p-draft',   'Draft'],
    'pending_approval' => ['p-pending', 'Pending approval'],
    'approved'         => ['p-approved','Approved'],
    'sent'             => ['p-sent',    'Sent'],
    'partially_paid'   => ['p-partial', 'Partially paid'],
    'paid'             => ['p-paid',    'Paid'],
    'overdue'          => ['p-overdue', 'Overdue'],
    'written_off'      => ['p-void',    'Written off'],
    'void'             => ['p-void',    'Void'],
  ];
  $st = $statusMap[$invoice->status] ?? ['p-draft', ucfirst($invoice->status)];
@endphp
<div class="doc-title">
  Invoice
<span style="font-family:'DM Sans', system-ui, sans-serif;font-size:15px;font-weight:600;color:var(--muted);letter-spacing:0;position:relative;top:-1px">
  {{ $invoice->invoice_number }}
</span>
</div>

<div class="meta-grid">
  <div><span class="lbl">Number</span> <span class="val">{{ $invoice->invoice_number }}</span></div>
  <div><span class="lbl">Issued</span> <span class="val">{{ $invoice->issued_at?->format('d M Y') ?? $invoice->created_at->format('d M Y') }}</span></div>
  <div><span class="lbl">Due</span> <span class="val">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</span></div>
  <div><span class="lbl">Request</span> <span class="val">{{ $invoice->serviceRequest?->sr_number ?? '—' }}</span></div>
  @if ($invoice->legal_invoice_number)
    <div style="grid-column:1 / -1"><span class="lbl">Legal #</span> <span class="val">{{ $invoice->legal_invoice_number }}</span></div>
  @endif
</div>

{{-- ===== TO CARD ===== --}}
<div class="to-card">
  <div class="co">{{ $client?->name ?? '—' }}</div>
  @if ($clientAddr)  <div class="ln">{{ $clientAddr }}</div> @endif
  @if ($clientTrn)   <div class="ln">TRN {{ $clientTrn }}</div> @endif
  @if ($site?->name) <div class="ln">{{ $site->name }}@if ($site->city), {{ $site->city }}@endif</div> @endif
</div>

{{-- ===== LINES TABLE ===== --}}
<table class="ln">
  <thead>
    <tr>
      <th>#</th>
      <th>Description</th>
      <th class="r">Qty</th>
      <th class="r">Rate</th>
      <th class="r">Tax</th>
      <th class="r">Line total</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($invoice->items as $it)
      <tr>
        <td class="n">{{ $it->line_no }}</td>
        <td class="d">{{ $it->description }}</td>
        <td class="r">{{ rtrim(rtrim(number_format($it->quantity, 2), '0'), '.') }}</td>
        <td class="r">{{ number_format($it->unit_rate, 2) }}</td>
        <td class="r">{{ $it->taxRate?->rate_percent ? rtrim(rtrim(number_format($it->taxRate->rate_percent,2),'0'),'.').'%' : '—' }}</td>
        <td class="r">{{ number_format($it->line_total, 2) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

{{-- ===== TOTALS ===== --}}
<div class="totals-wrap">
  <table class="totals">
    <tr><td>Subtotal</td><td class="v">AED {{ number_format($invoice->subtotal, 2) }}</td></tr>
    @if ($invoice->discount_total > 0)
      <tr><td>Discount</td><td class="v">-AED {{ number_format($invoice->discount_total, 2) }}</td></tr>
    @endif
    <tr><td>Tax</td><td class="v">AED {{ number_format($invoice->tax_total, 2) }}</td></tr>
    <tr class="big"><td>Total</td><td class="v">AED {{ number_format($invoice->total, 2) }}</td></tr>

    @php $paidAmt = max(0, $invoice->total - ($invoice->balance ?? 0)); @endphp
    @if ($paidAmt > 0 && $invoice->balance > 0)
      <tr><td>Paid</td><td class="v">AED {{ number_format($paidAmt, 2) }}</td></tr>
      <tr class="balance"><td>Balance due</td><td class="v">AED {{ number_format($invoice->balance, 2) }}</td></tr>
    @elseif ($invoice->status === 'paid')
      <tr class="balance paid"><td>Balance due</td><td class="v">AED 0.00</td></tr>
    @elseif ($invoice->balance > 0 && $invoice->status !== 'draft')
      <tr class="balance"><td>Balance due</td><td class="v">AED {{ number_format($invoice->balance, 2) }}</td></tr>
    @endif
  </table>
</div>

{{-- ===== PAYMENT INFO ===== --}}
@if (! in_array($invoice->status, ['draft','paid','void','written_off']) && $invoice->balance > 0)
  <div class="pay-info">
    <div class="ttl">Payment information</div>
    @php
      $bankName = $tenant?->bank_name ?? null;
      $iban     = $tenant?->iban ?? null;
      $account  = $tenant?->account_number ?? null;
    @endphp
    @if ($bankName || $iban || $account)
      @if ($bankName) <div><b>Bank:</b> {{ $bankName }}</div> @endif
      @if ($iban)     <div><b>IBAN:</b> {{ $iban }}</div> @endif
      @if ($account)  <div><b>Account:</b> {{ $account }}</div> @endif
      <div><b>Reference:</b> {{ $invoice->invoice_number }}</div>
    @else
      Please reference <b>{{ $invoice->invoice_number }}</b> on your payment. Bank details are available on request.
    @endif
  </div>
@endif

{{-- ===== TERMS / NOTES ===== --}}
@if ($invoice->notes)
  <div class="terms"><b>Notes.</b> {!! nl2br(e($invoice->notes)) !!}</div>
@endif

{{-- ===== FOOTER ===== --}}
<div class="foot">
  <div>{{ $senderName }}</div>
  <div>{{ $invoice->invoice_number }} · Generated {{ now()->format('d M Y, H:i') }}</div>
</div>

<div class="cc">— Customer copy —</div>

<script>window.onload = () => window.print();</script>
</body>
</html>