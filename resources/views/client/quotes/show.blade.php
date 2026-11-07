<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $quote->quote_number }} — {{ \App\Models\Tenant::current()->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.wrap{max-width:640px;margin:0 auto;padding:28px 20px 60px}
.back{color:#5F5D6E;font-size:13px;text-decoration:none;font-weight:600;display:inline-block;margin-bottom:14px}
h1{font-family:'Figtree',sans-serif;font-size:20px;font-weight:800;margin-bottom:6px}
.pill{display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:18px}
.p-sent{background:#FFF4CC;color:#8A5A00}
.p-accepted{background:#DDF7F1;color:#00866F}
.p-rejected{background:#FDECE7;color:#C2381B}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;padding:20px;margin-bottom:14px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;color:#5F5D6E;font-size:11px;text-transform:uppercase;padding-bottom:8px}
td{padding:8px 0;border-top:1px solid #F2F1F6}
.tot{display:flex;justify-content:flex-end;gap:30px;margin-top:14px;font-weight:700;font-size:15px}
.actions{display:flex;gap:10px;margin-top:6px}
.btn{display:inline-flex;background:#00866F;color:#fff;font-weight:700;padding:11px 20px;border-radius:999px;text-decoration:none;font-size:13.5px;border:0;cursor:pointer;font-family:inherit}
.btn.red{background:#C2381B}
.btn.ghost{background:#fff;border:1px solid #E6E4EE;color:#1B1A24}
</style>
</head>
<body>
<div class="wrap">
    <a href="{{ route('portal.quotes.index') }}" class="back">← Quotes</a>

    @if (session('success'))
        <div style="background:#DDF7F1;color:#00866F;padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:13px">{{ session('success') }}</div>
    @endif

    <h1>{{ $quote->quote_number }} — {{ $quote->serviceRequest?->title }}</h1>
    <span class="pill p-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span>

    @if ($quote->pdfAttachment)
        <div class="card">
            <a href="{{ $quote->pdfAttachment->url }}" target="_blank" class="btn ghost">Download quote PDF</a>
        </div>
    @else
        <div class="card">
            <table>
                <thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th style="text-align:right">Total</th></tr></thead>
                <tbody>
                @foreach ($quote->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->quantity }} {{ $item->unit }}</td>
                        <td>{{ number_format($item->unit_rate, 2) }}</td>
                        <td style="text-align:right">{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="tot"><span>Total</span><span>{{ $quote->currency_code }} {{ number_format($quote->total, 2) }}</span></div>
        </div>
    @endif

    @if ($quote->status === 'sent' && auth()->user()->role?->code === 'client_admin')
        <div class="card actions">
            <form method="POST" action="{{ route('portal.quotes.accept', $quote) }}" onsubmit="return confirm('Accept this quote?')">
                @csrf
                <button type="submit" class="btn">Accept quote</button>
            </form>
            <form method="POST" action="{{ route('portal.quotes.reject', $quote) }}" onsubmit="return confirm('Reject this quote?')">
                @csrf
                <button type="submit" class="btn red">Reject quote</button>
            </form>
        </div>
    @endif
</div>
</body>
</html>