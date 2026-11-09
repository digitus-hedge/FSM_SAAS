<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Requests — {{ \App\Models\Tenant::current()->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.wrap{max-width:720px;margin:0 auto;padding:28px 20px 60px}
.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap}
h1{font-family:'Figtree',sans-serif;font-size:22px;font-weight:800}
.back{color:#5F5D6E;font-size:13px;text-decoration:none;font-weight:600}
.tabs{display:flex;gap:4px;border-bottom:1px solid #E6E4EE;margin-bottom:18px}
.tabs a{padding:10px 16px;color:#5F5D6E;text-decoration:none;font-weight:600;font-size:13.5px;border-bottom:2px solid transparent}
.tabs a.active{color:#1B1A24;border-bottom-color:#1B1A24}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;overflow:hidden}
.row{display:grid;grid-template-columns:auto 1fr auto;gap:14px;padding:16px 18px;border-top:1px solid #E6E4EE;align-items:center;text-decoration:none;color:inherit}
.row:first-child{border-top:0}
.row:hover{background:#F8F7FB}
.num{font-family:monospace;font-weight:700;font-size:12px;background:#F5F4FA;padding:4px 10px;border-radius:5px;color:#8A5A00}
.mid b{display:block;font-size:14px}
.mid span{color:#5F5D6E;font-size:12.5px}
.pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:10.5px;font-weight:700;text-transform:uppercase}
.p-pending{background:#FFF4CC;color:#8A5A00}
.p-quoted{background:#EDE9FE;color:#5B3BB8}
.p-approved,.p-assigned,.p-in-progress{background:#E3EBF4;color:#2B5A8C}
.p-pending-review,.p-awaiting-invoice{background:#FFF0D1;color:#8A5A00}
.p-completed{background:#DDF7F1;color:#00866F}
.p-cancelled{background:#F5F4FA;color:#5F5D6E}
.empty{color:#5F5D6E;text-align:center;padding:50px 20px}
.btn{display:inline-block;background:#FFC61A;color:#1B1A24;font-weight:700;padding:9px 16px;border-radius:999px;text-decoration:none;font-size:13px}
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>Your requests</h1>
        <a href="{{ route('portal.dashboard') }}" class="back">← Dashboard</a>
    </div>

    <div class="tabs">
        <a href="{{ route('portal.requests.index',['tab'=>'open']) }}" class="{{ $tab==='open'?'active':'' }}">Open</a>
        <a href="{{ route('portal.requests.index',['tab'=>'closed']) }}" class="{{ $tab==='closed'?'active':'' }}">Closed</a>
        <a href="{{ route('portal.requests.index',['tab'=>'all']) }}" class="{{ $tab==='all'?'active':'' }}">All</a>
        @if (auth()->user()->role?->code === 'client_admin')
            <a href="{{ route('portal.requests.raise') }}" class="btn" style="margin-left:auto;align-self:center">+ Raise a request</a>
        @endif
    </div>

    <div class="card">
        @forelse ($items as $sr)
            <a href="{{ route('portal.requests.show', $sr) }}" class="row">
                <span class="num">{{ $sr->sr_number }}</span>
                <div class="mid">
                    <b>{{ $sr->title }}</b>
                    <span>{{ $sr->site?->name }} · {{ $sr->created_at->format('d M Y') }}</span>
                </div>
                <span class="pill p-{{ str_replace('_','-',$sr->clientStatusKey()) }}">{{ $sr->clientStatusLabel() }}</span>
            </a>
        @empty
            <div class="empty">Nothing here.</div>
        @endforelse
    </div>

    @if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div style="margin-top:16px">{{ $items->links() }}</div>
    @endif
</div>
</body>
</html>