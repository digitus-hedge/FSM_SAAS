<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page not found — AIYWAH FSM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{max-width:440px;background:#fff;border:1px solid #E6E4EE;border-radius:16px;padding:36px 32px;text-align:center}
.code{font-family:'Figtree',sans-serif;font-size:13px;font-weight:700;color:#5F5D6E;letter-spacing:.08em;text-transform:uppercase;margin-bottom:10px}
h1{font-family:'Figtree',sans-serif;font-size:24px;font-weight:700;margin-bottom:10px}
p{color:#5F5D6E;font-size:14px;line-height:1.55;margin-bottom:24px}
.btn{display:inline-flex;align-items:center;gap:8px;background:#FFC61A;color:#1B1A24;font-weight:700;font-size:14px;padding:11px 22px;border-radius:999px;text-decoration:none}
</style>
</head>
<body>
<div class="card">
    <div class="code">Error 404</div>
    <h1>We couldn't find that page</h1>
    <p>It may have been moved, or the link might be out of date.</p>
    @php
        $isPlatform = \Illuminate\Support\Facades\Auth::guard('platform')->check();
        $isTenant   = \Illuminate\Support\Facades\Auth::check();
    @endphp
    @if ($isPlatform || $isTenant)
        <a href="{{ $isPlatform ? route('admin.dashboard') : route('tenant.dashboard') }}" class="btn">Back to dashboard</a>
    @else
        <a href="{{ route('login') }}" class="btn">Back to sign in</a>
    @endif
</div>
</body>
</html>