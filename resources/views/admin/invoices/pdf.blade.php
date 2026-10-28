<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $invoice->invoice_number }}</title>
  <style>
    body{font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#1B1A24;max-width:800px;margin:40px auto;padding:0 24px}
    h1{font-size:22px;margin:0 0 4px;letter-spacing:-.3px}
    .meta{color:#5F5D6E;font-size:11px}
    .cols{display:flex;justify-content:space-between;margin:24px 0;gap:24px}
    .box{flex:1}
    .box h3{font-size:10px;color:#8B89A0;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;font-weight:700}
    table{width:100%;border-collapse:collapse;margin-top:16px}
    th{background:#F5F4FA;color:#5F5D6E;font-size:10px;text-transform:uppercase;letter-spacing:.06em;padding:8px;text-align:left;font-weight:700}
    td{padding:9px 8px;border-bottom:1px solid #E6E4EE;vertical-align:top}
    td.r{text-align:right;font-variant-numeric:tabular-nums}
    .tots{margin-top:16px;margin-left:auto;width:280px}
    .tots .row{display:flex;justify-content:space-between;padding:6px 0;font-variant-numeric:tabular-nums}
    .tots .tot{font-weight:700;border-top:2px solid #1B1A24;padding-top:8px;margin-top:4px;font-size:14px}
    .footnote{margin-top:40px;padding-top:14px;border-top:1px solid #E6E4EE;color:#8B89A0;font-size:11px}
    .cc{font-size:9px;color:#8B89A0;letter-spacing:.1em;text-transform:uppercase;text-align:center;margin-top:16px}
  </style>
</head>
<body>
  <h1>Invoice {{ $invoice->invoice_number }}</h1>
  <div class="meta">
    @if ($invoice->issued_at) Issued {{ $invoice->issued_at->format('d M Y') }} · @endif
    @if ($invoice->due_date) Due {{ $invoice->due_date->format('d M Y') }}@endif
    @if ($invoice->legal_invoice_number) · Legal # {{ $invoice->legal_invoice_number }}@endif
  </div>

  <div class="cols">
    <div class="box"><h3>From</h3>Your Company Name<br>Tax ID …</div>
    <div class="box"><h3>Bill to</h3><b>{{ $invoice->client?->name }}</b></div>
    <div class="box"><h3>Request</h3>{{ $invoice->serviceRequest?->sr_number }}</div>
  </div>

  <table>
    <thead><tr><th>#</th><th>Description</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Tax</th><th class="r">Line total</th></tr></thead>
    <tbody>
      @foreach ($invoice->items as $it)
        <tr>
          <td>{{ $it->line_no }}</td><td>{{ $it->description }}</td>
          <td class="r">{{ number_format($it->quantity, 2) }}</td>
          <td class="r">{{ number_format($it->unit_rate, 3) }}</td>
          <td class="r">{{ $it->taxRate?->rate_percent ? $it->taxRate->rate_percent.'%' : '—' }}</td>
          <td class="r">{{ number_format($it->line_total, 3) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="tots">
    <div class="row"><span>Subtotal</span><span>AED {{ number_format($invoice->subtotal, 3) }}</span></div>
    @if ($invoice->discount_total > 0)
      <div class="row"><span>Discount</span><span>— {{ number_format($invoice->discount_total, 3) }}</span></div>
    @endif
    <div class="row"><span>Tax</span><span>AED {{ number_format($invoice->tax_total, 3) }}</span></div>
    <div class="row tot"><span>Total</span><span>AED {{ number_format($invoice->total, 3) }}</span></div>
  </div>

  @if ($invoice->notes)
    <div class="footnote">{!! nl2br(e($invoice->notes)) !!}</div>
  @endif

  <div class="cc">— Customer copy —</div>
</body>
</html>