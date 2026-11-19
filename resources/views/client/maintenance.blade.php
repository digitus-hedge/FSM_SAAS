<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Upcoming maintenance — {{ \App\Models\Tenant::current()->name }}</title>
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
.row{display:grid;grid-template-columns:110px 1fr auto;gap:14px;padding:16px 18px;border-top:1px solid #E6E4EE;align-items:center}
.row:first-child{border-top:0}
.date{font-weight:700}
.mid span{color:#5F5D6E;font-size:12.5px}
.empty{color:#5F5D6E;text-align:center;padding:50px 20px}
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>Upcoming maintenance</h1>
        <a href="{{ route('portal.dashboard') }}" class="back">← Dashboard</a>
    </div>
    <div class="card">
        @forelse ($visits as $v)
            <div class="row">
                <div class="date">{{ $v['date']->format('j M Y') }}</div>
                <div class="mid"><b>{{ $v['what'] }}</b><br><span>{{ $v['site'] }} · {{ $v['category'] }}</span></div>
            </div>
        @empty
            <div class="empty">No maintenance is planned for your sites in the next {{ $days }} days.</div>
        @endforelse
    </div>
</div>
</body>
</html>
