<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Invoice {{ $invoice->invoice_number }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
    .wrap{max-width:720px;margin:0 auto;padding:40px 20px 60px}
    h1{font-family:'Figtree';font-size:26px;font-weight:800;margin-bottom:4px}
    .meta{color:#5F5D6E;font-size:13px;margin-bottom:28px}
    .card{background:#fff;border:1px solid #E6E4EE;border-radius:16px;padding:24px;margin-bottom:16px}
    .lbl{font-size:11px;color:#5F5D6E;text-transform:uppercase;letter-spacing:.08em;font-weight:700;margin-bottom:4px}
    table{width:100%;border-collapse:collapse;font-size:13.5px}
    th{text-align:left;padding:10px 8px;background:#F5F4FA;color:#5F5D6E;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;font-weight:700}
    td{padding:11px 8px;border-top:1px solid #E6E4EE;font-variant-numeric:tabular-nums}
    td.r{text-align:right}
    .tot{display:flex;justify-content:space-between;padding:8px 0;font-variant-numeric:tabular-nums}
    .tot.big{font-family:'Figtree';font-weight:800;font-size:18px;border-top:2px solid #1B1A24;padding-top:12px;margin-top:8px}
    .btn{display:inline-block;background:#FFC61A;color:#1B1A24;font-weight:700;padding:12px 28px;border-radius:999px;text-decoration:none;font-size:14px}
    .btn:hover{background:#F0B700}
  </style>
</head>
<body>
<div class="wrap">
  <h1>Invoice {{ $invoice->invoice_number }}</h1>
  <div class="meta">
    From <b>Your Company</b> · For <b>{{ $invoice->client?->name }}</b><br>
    @if ($invoice->due_date) Due <b>{{ $invoice->due_date->format('d M Y') }}</b> · @endif
    Request <b>{{ $invoice->serviceRequest?->sr_number }}</b>
  </div>

  <div class="card">
    <div class="lbl">Line items</div>
    <table style="margin-top:10px">
      <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Total</th></tr></thead>
      <tbody>
        @foreach ($invoice->items as $it)
          <tr><td>{{ $it->description }}</td><td class="r">{{ number_format($it->quantity, 2) }}</td><td class="r">{{ number_format($it->unit_rate, 3) }}</td><td class="r">{{ number_format($it->line_total, 3) }}</td></tr>
        @endforeach
      </tbody>
    </table>

    <div style="margin-top:16px">
      <div class="tot"><span>Subtotal</span><span>AED {{ number_format($invoice->subtotal, 3) }}</span></div>
      @if ($invoice->discount_total > 0)
        <div class="tot"><span>Discount</span><span>— {{ number_format($invoice->discount_total, 3) }}</span></div>
      @endif
      <div class="tot"><span>Tax</span><span>AED {{ number_format($invoice->tax_total, 3) }}</span></div>
      <div class="tot big"><span>Total</span><span>AED {{ number_format($invoice->total, 3) }}</span></div>
    </div>
  </div>

  @if ($invoice->notes)
    <div class="card"><div class="lbl">Notes</div><div style="color:#5F5D6E;white-space:pre-line;margin-top:6px">{{ $invoice->notes }}</div></div>
  @endif

  <div style="text-align:center;margin-top:24px">
    <a href="{{ route('public.invoice.pdf', $token->token) }}" class="btn" target="_blank">Download PDF</a>
  </div>

  <div style="text-align:center;color:#8B89A0;font-size:11px;margin-top:40px;letter-spacing:.08em;text-transform:uppercase">— Customer copy —</div>
</div>
</body>
</html>