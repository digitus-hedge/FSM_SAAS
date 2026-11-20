<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
@php
  $tenant   = $invoice->tenant;
  $cur      = $invoice->currency_code;
  $fmt      = fn ($v) => \App\Services\Currency\Money::format($v, $cur);
  $senderName = $tenant?->name ?? 'Invoice';
@endphp
<title>{{ $invoice->invoice_number }} — {{ $senderName }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#fff;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.wrap{max-width:640px;margin:0 auto;padding:28px 20px 60px}
.co{font-family:'Figtree',sans-serif;font-weight:800;font-size:15px;color:#5F5D6E;margin-bottom:14px}
h1{font-family:'Figtree',sans-serif;font-size:22px;font-weight:800;margin-bottom:6px}
.pill{display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:18px;background:#FFF0D1;color:#8A5A00}
.pill.paid{background:#DDF7F1;color:#00866F}
.pill.overdue{background:#FDECE7;color:#C2381B}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;padding:20px;margin-bottom:14px}
.card h3{font-family:'Figtree',sans-serif;font-size:14px;font-weight:700;margin-bottom:10px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;color:#5F5D6E;font-size:11px;text-transform:uppercase;padding-bottom:8px}
td{padding:8px 0;border-top:1px solid #F2F1F6}
.r{text-align:right}
.tot{display:flex;justify-content:space-between;margin-top:6px;font-size:14px}
.tot b{font-size:16px}
.bank div{display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid #F2F1F6;font-size:13px}
.bank div:first-child{border-top:0}
.btn{display:inline-flex;background:#fff;border:1px solid #E6E4EE;color:#1B1A24;font-weight:700;padding:10px 18px;border-radius:999px;text-decoration:none;font-size:13.5px}
</style>
@include('layouts._public_brand', ['brandTenant' => $invoice->tenant_id])
</head>
<body>
<div class="wrap">
    <div class="co">{{ $senderName }}</div>

    <h1>Invoice {{ $invoice->invoice_number }}</h1>
    <span class="pill {{ $invoice->status === 'paid' ? 'paid' : ($invoice->status === 'overdue' ? 'overdue' : '') }}">{{ ucwords(str_replace('_', ' ', $invoice->status)) }}</span>

    <div class="card">
        <div style="margin-bottom:10px;color:#5F5D6E">
            {{ $invoice->client?->name }}@if ($invoice->serviceRequest?->title) · {{ $invoice->serviceRequest->title }}@endif
        </div>
        <a href="{{ route('public.invoice.pdf', $token->token) }}" target="_blank" class="btn">Download invoice PDF</a>
    </div>

    <div class="card">
        <h3>Summary</h3>
        <table>
            <thead><tr><th>Description</th><th>Qty</th><th class="r">Rate</th><th class="r">Total</th></tr></thead>
            <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit }}</td>
                    <td class="r">{{ $fmt($item->unit_rate) }}</td>
                    <td class="r">{{ $fmt($item->line_total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="tot"><span>Subtotal</span><span>{{ $cur }} {{ $fmt($invoice->subtotal) }}</span></div>
        @if ($invoice->discount_total > 0)
            <div class="tot"><span>Discount</span><span>-{{ $cur }} {{ $fmt($invoice->discount_total) }}</span></div>
        @endif
        <div class="tot"><span>Tax</span><span>{{ $cur }} {{ $fmt($invoice->tax_total) }}</span></div>
        <div class="tot"><span>Total</span><span>{{ $cur }} {{ $fmt($invoice->total) }}</span></div>
        <div class="tot"><span>Balance due</span><b>{{ $cur }} {{ $fmt($invoice->balance) }}</b></div>
        <div class="tot"><span>Due date</span><span>{{ $invoice->due_date?->format('d M Y') }}</span></div>
    </div>

    @if (! empty($invoice->bank_snapshot_json))
        <div class="card bank">
            <h3>Bank details</h3>
            @foreach ($invoice->bank_snapshot_json as $k => $v)
                <div><span>{{ ucwords(str_replace('_', ' ', $k)) }}</span><span>{{ $v }}</span></div>
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
