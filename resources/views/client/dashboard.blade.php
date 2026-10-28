<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Portal — {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
        .wrap{max-width:720px;margin:0 auto;padding:28px 20px 60px}
        .welcome{background:#DDF7F1;border:1px solid #00866F;color:#00866F;padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:13px;display:flex;gap:10px;align-items:center}
        .welcome svg{width:16px;height:16px;flex-shrink:0}
        .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;gap:12px;flex-wrap:wrap}
        .me{font-family:'Figtree';font-size:22px;font-weight:800;letter-spacing:-.3px}
        .me .co{color:#5F5D6E;font-size:14px;font-weight:600;margin-left:6px}
        .signout{background:none;border:0;color:#5F5D6E;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;font-family:inherit}
        .signout:hover{color:#1B1A24;text-decoration:underline}
        .card{background:#fff;border:1px solid #E6E4EE;border-radius:16px;padding:20px 22px;margin-bottom:12px}
        .ph{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
        .ph h3{font-family:'Figtree';font-weight:700;font-size:15px}
        .ph a{color:#5F5D6E;font-size:13px;font-weight:600;text-decoration:none}
        .sr{display:grid;grid-template-columns:auto 1fr auto;gap:14px;padding:12px 0;border-top:1px solid #E6E4EE;align-items:center}
        .sr:first-child{border-top:0;padding-top:4px}
        .num{font-family:'JetBrains Mono',Consolas,monospace;font-weight:700;font-size:12px;background:#F5F4FA;padding:4px 10px;border-radius:5px;color:#8A5A00}
        .mid b{display:block;font-size:14px}
        .mid span{color:#5F5D6E;font-size:12.5px}
        .pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
        .p-pending{background:#FFF4CC;color:#8A5A00}
        .p-progress{background:#E3EBF4;color:#2B5A8C}
        .p-completed{background:#DDF7F1;color:#00866F}
        .p-approved{background:#E3EBF4;color:#2B5A8C}
        .p-cancelled{background:#F5F4FA;color:#5F5D6E}
        .btn{display:inline-block;background:#FFC61A;color:#1B1A24;font-weight:700;padding:10px 18px;border-radius:999px;text-decoration:none;font-size:13.5px}
        .btn.ghost{background:transparent;border:1px solid #E6E4EE;color:#1B1A24;font-weight:600}
        .empty{color:#5F5D6E;text-align:center;padding:24px;font-size:13px}
    </style>
</head>
<body>
    <div class="wrap">
        @if (session('welcome'))
            <div class="welcome">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                You're signed in. Your session stays active for 8 hours.
            </div>
        @endif

        <div class="head">
            <div class="me">Welcome, {{ $user->name }} <span class="co">· {{ $tenant->name }}</span></div>
            <form method="POST" action="{{ route('portal.sign-out') }}" style="margin:0">
                @csrf
                <button type="submit" class="signout">Sign out</button>
            </form>
        </div>

        <div class="card">
            <div class="ph">
                <h3>Your service requests</h3>
                <a href="#">View all →</a>
            </div>

            @if ($requests->isEmpty())
                <div class="empty">No requests yet. Your contact at {{ $tenant->name }} will raise them for you.</div>
            @else
                @foreach ($requests as $r)
                    <div class="sr">
                        <span class="num">{{ $r->sr_number }}</span>
                        <div class="mid">
                            <b>{{ $r->title ?? '—' }}</b>
                            <span>{{ $r->created_at->format('d M Y') }}</span>
                        </div>
                        <span class="pill p-{{ strtolower(str_replace([' ', '_'], '-', $r->status)) }}">{{ $r->status }}</span>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="card">
            <div class="ph"><h3>Quick actions</h3></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                @if ($user->role?->code === 'client_admin')
                    <a href="#" class="btn">+ Raise a new request</a>
                @endif
                <a href="#" class="btn ghost">View quotes</a>
                <a href="#" class="btn ghost">View invoices</a>
            </div>
            @if ($user->role?->code !== 'client_admin')
                <div style="color:#8B89A0;font-size:12px;margin-top:12px">Raising a request is available to Client Admins only.</div>
            @endif
        </div>
    </div>
</body>
</html>
