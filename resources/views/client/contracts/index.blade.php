<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My contracts — {{ \App\Models\Tenant::current()->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.wrap{max-width:860px;margin:0 auto;padding:28px 20px 60px}
.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
h1{font-family:'Figtree',sans-serif;font-size:22px;font-weight:800}
.back{color:#5F5D6E;font-size:13px;text-decoration:none;font-weight:600}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;overflow:hidden}
.row{display:grid;grid-template-columns:150px 1fr 150px;gap:14px;padding:16px 18px;border-top:1px solid #E6E4EE;align-items:center;text-decoration:none;color:inherit}
.row:first-child{border-top:0}
.mid span,.sub{color:#5F5D6E;font-size:12.5px}
.chip{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#EEF0F6;color:#3B3A4A}
.chip.active{background:#E3F6EA;color:#166534}.chip.expiring_soon{background:#FFF1D6;color:#8A5A00}.chip.expired{background:#FDE2E2;color:#991B1B}
.empty{color:#5F5D6E;text-align:center;padding:50px 20px}
@media(max-width:640px){.row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>My contracts</h1>
        <a href="{{ route('portal.dashboard') }}" class="back">← Dashboard</a>
    </div>
    <div class="card">
        @forelse ($contracts as $c)
            <a class="row" href="{{ route('portal.contracts.show', $c->id) }}">
                <div><b>{{ $c->contract_number }}</b><br><span class="sub">{{ $c->type_label }}</span></div>
                <div class="mid">{{ $c->start_date->format('j M Y') }} to {{ $c->end_date->format('j M Y') }}<br>
                    <span>@if ($c->max_calls) {{ $c->calls_used_current_period }} of {{ $c->max_calls }} calls used @else No call limit @endif</span></div>
                <div><span class="chip {{ $c->status }}">{{ ucfirst(str_replace('_', ' ', $c->status)) }}</span></div>
            </a>
        @empty
            <div class="empty">You have no contracts with {{ \App\Models\Tenant::current()->name }} yet.</div>
        @endforelse
    </div>
</div>
</body>
</html>
