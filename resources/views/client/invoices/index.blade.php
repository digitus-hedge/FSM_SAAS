<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoices — {{ \App\Models\Tenant::current()->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.wrap{max-width:720px;margin:0 auto;padding:28px 20px 60px}
.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
h1{font-family:'Figtree',sans-serif;font-size:22px;font-weight:800}
.back{color:#5F5D6E;font-size:13px;text-decoration:none;font-weight:600}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;overflow:hidden}
.row{display:grid;grid-template-columns:auto 1fr auto;gap:14px;padding:16px 18px;border-top:1px solid #E6E4EE;align-items:center;text-decoration:none;color:inherit}
.row:first-child{border-top:0}
.row:hover{background:#F8F7FB}
.num{font-family:monospace;font-weight:700;font-size:12px;background:#F5F4FA;padding:4px 10px;border-radius:5px;color:#2B5A8C}
.mid b{display:block;font-size:14px}
.mid span{color:#5F5D6E;font-size:12.5px}
.pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:10.5px;font-weight:700;text-transform:uppercase}
.p-sent{background:#FFF0D1;color:#8A5A00}
.p-partially-paid{background:#E3EBF4;color:#2B5A8C}
.p-paid{background:#DDF7F1;color:#00866F}
.p-overdue{background:#FDECE7;color:#C2381B}
.p-written-off{background:#F5F4FA;color:#5F5D6E}
.empty{color:#5F5D6E;text-align:center;padding:50px 20px}
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>Invoices</h1>
        <a href="{{ route('portal.dashboard') }}" class="back">← Dashboard</a>
    </div>
    <div class="card">
        @forelse ($items as $inv)
            <a href="{{ route('portal.invoices.show', $inv) }}" class="row">
                <span class="num">{{ $inv->invoice_number }}</span>
                <div class="mid">
                    <b>{{ $inv->serviceRequest?->title }}</b>
                    <span>{{ $inv->serviceRequest?->sr_number }} · Due {{ $inv->due_date?->format('d M Y') }}</span>
                </div>
                <span class="pill p-{{ str_replace('_','-',$inv->status) }}">{{ ucwords(str_replace('_',' ',$inv->status)) }}</span>
            </a>
        @empty
            <div class="empty">No invoices yet.</div>
        @endforelse
    </div>
    @if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div style="margin-top:16px">{{ $items->links() }}</div>
    @endif
</div>
</body>
</html>