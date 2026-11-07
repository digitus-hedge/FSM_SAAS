<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $invoice->invoice_number }} — {{ \App\Models\Tenant::current()->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.wrap{max-width:640px;margin:0 auto;padding:28px 20px 60px}
.back{color:#5F5D6E;font-size:13px;text-decoration:none;font-weight:600;display:inline-block;margin-bottom:14px}
h1{font-family:'Figtree',sans-serif;font-size:20px;font-weight:800;margin-bottom:6px}
.pill{display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:18px}
.p-sent{background:#FFF0D1;color:#8A5A00}
.p-partially-paid{background:#E3EBF4;color:#2B5A8C}
.p-paid{background:#DDF7F1;color:#00866F}
.p-overdue{background:#FDECE7;color:#C2381B}
.p-written-off{background:#F5F4FA;color:#5F5D6E}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;padding:20px;margin-bottom:14px}
.card h3{font-family:'Figtree',sans-serif;font-size:14px;font-weight:700;margin-bottom:10px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;color:#5F5D6E;font-size:11px;text-transform:uppercase;padding-bottom:8px}
td{padding:8px 0;border-top:1px solid #F2F1F6}
.tot{display:flex;justify-content:space-between;margin-top:6px;font-size:14px}
.tot b{font-size:16px}
.bank div{display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid #F2F1F6;font-size:13px}
.bank div:first-child{border-top:0}
.btn{display:inline-flex;background:#fff;border:1px solid #E6E4EE;color:#1B1A24;font-weight:700;padding:10px 18px;border-radius:999px;text-decoration:none;font-size:13.5px}
</style>
</head>
<body>
<div class="wrap">
    <a href="{{ route('portal.invoices.index') }}" class="back">← Invoices</a>

    <h1>{{ $invoice->invoice_number }} — {{ $invoice->serviceRequest?->title }}</h1>
    <span class="pill p-{{ str_replace('_','-',$invoice->status) }}">{{ ucwords(str_replace('_',' ',$invoice->status)) }}</span>

    <div class="card">
        <a href="{{ route('portal.invoices.pdf', $invoice) }}" target="_blank" class="btn">Download invoice PDF</a>
    </div>

    <div class="card">
        <h3>Summary</h3>
        <table>
            <thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th style="text-align:right">Total</th></tr></thead>
            <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }} {{ $item->unit }}</td>
                    <td>{{ number_format($item->unit_rate, 2) }}</td>
                    <td style="text-align:right">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="tot"><span>Total</span><span>{{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}</span></div>
        <div class="tot"><span>Balance due</span><b>{{ $invoice->currency_code }} {{ number_format($invoice->balance, 2) }}</b></div>
        <div class="tot"><span>Due date</span><span>{{ $invoice->due_date?->format('d M Y') }}</span></div>
    </div>

    @if (!empty($invoice->bank_snapshot_json))
        <div class="card bank">
            <h3>Bank details</h3>
            @foreach ($invoice->bank_snapshot_json as $k => $v)
                <div><span>{{ ucwords(str_replace('_',' ',$k)) }}</span><span>{{ $v }}</span></div>
            @endforeach
        </div>
    @endif
</div>
</body>
</html>