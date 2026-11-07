<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quotes — {{ \App\Models\Tenant::current()->name }}</title>
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
.num{font-family:monospace;font-weight:700;font-size:12px;background:#F5F4FA;padding:4px 10px;border-radius:5px;color:#5B3BB8}
.mid b{display:block;font-size:14px}
.mid span{color:#5F5D6E;font-size:12.5px}
.pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:10.5px;font-weight:700;text-transform:uppercase}
.p-sent{background:#FFF4CC;color:#8A5A00}
.p-accepted{background:#DDF7F1;color:#00866F}
.p-rejected{background:#FDECE7;color:#C2381B}
.empty{color:#5F5D6E;text-align:center;padding:50px 20px}
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>Quotes</h1>
        <a href="{{ route('portal.dashboard') }}" class="back">← Dashboard</a>
    </div>
    <div class="card">
        @forelse ($items as $q)
            <a href="{{ route('portal.quotes.show', $q) }}" class="row">
                <span class="num">{{ $q->quote_number }}</span>
                <div class="mid">
                    <b>{{ $q->serviceRequest?->title }}</b>
                    <span>{{ $q->serviceRequest?->sr_number }} · {{ $q->currency_code }} {{ number_format($q->total, 2) }}</span>
                </div>
                <span class="pill p-{{ $q->status }}">{{ ucfirst($q->status) }}</span>
            </a>
        @empty
            <div class="empty">No quotes yet.</div>
        @endforelse
    </div>
    @if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div style="margin-top:16px">{{ $items->links() }}</div>
    @endif
</div>
</body>
</html>