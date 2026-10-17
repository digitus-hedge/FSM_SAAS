<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Platform Console') — AIYWAH FSM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#FBFAF6; --ink:#1B1A24; --muted:#5F5D6E; --subtle:#8B89A0;
  --line:#E6E4EE; --soft:#F5F4FA;
  --yellow:#FFC61A; --yellow-soft:#FFF4CC; --amber:#8A5A00;
  --green:#00866F; --green-soft:#DDF7F1; --red:#C2381B; --red-soft:#FDECE7;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--ink);font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.shell{display:grid;grid-template-columns:240px 1fr;min-height:100vh}

/* SIDEBAR */
.side{background:#fff;border-right:1px solid var(--line);padding:22px 14px;display:flex;flex-direction:column;gap:4px}
.brand{display:flex;align-items:center;gap:10px;padding:0 10px 20px;color:var(--ink);font-family:'Figtree';font-size:18px;font-weight:700;letter-spacing:-.3px;text-decoration:none}
.brand .logo{width:34px;height:34px;border-radius:9px;background:var(--ink);color:var(--yellow);display:flex;align-items:center;justify-content:center;font-weight:700}
.brand small{display:block;font-family:'DM Sans';font-size:11px;font-weight:400;color:var(--muted);letter-spacing:0;margin-top:1px}
.nav-item{display:flex;align-items:center;gap:10px;font-size:14px;padding:10px 12px;border-radius:999px;color:var(--ink);text-decoration:none}
.nav-item:hover{background:var(--soft)}
.nav-item.active{background:var(--yellow-soft);color:var(--amber);font-weight:600}
.nav-item svg{flex-shrink:0;color:var(--muted)}
.nav-item.active svg{color:var(--amber)}
.side .foot{margin-top:auto;display:flex;align-items:center;gap:10px;padding:12px 10px;border-top:1px solid var(--line)}
.avatar{width:34px;height:34px;border-radius:50%;background:var(--yellow-soft);color:var(--amber);font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.side .foot b{color:var(--ink);font-size:13px;display:block;line-height:1.2}
.side .foot span{font-size:11px;color:var(--muted)}
.logout-btn{background:none;border:0;color:var(--muted);font-size:12px;cursor:pointer;padding:0;margin-top:3px;text-decoration:underline;font-family:inherit}

/* CONTENT */
.content{padding:28px 44px 48px;overflow:hidden}
.flash{background:var(--green-soft);color:var(--green);padding:12px 16px;border-radius:12px;margin-bottom:20px;font-weight:600;font-size:14px}
.err-flash{background:var(--red-soft);color:var(--red);padding:12px 16px;border-radius:12px;margin-bottom:20px;font-weight:600;font-size:14px}
</style>
</head>
<body>
<div class="shell">

<nav class="side">
    <a href="{{ route('admin.dashboard') }}" class="brand">
        <span class="logo">A</span>
        <span>AIYWAH FSM<small>Platform console</small></span>
    </a>

    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Workspaces
    </a>

    <a href="{{ route('admin.tenants.create') }}" class="nav-item {{ request()->routeIs('admin.tenants.create') ? 'active' : '' }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        New workspace
    </a>

    <div class="foot">
        <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
        <div style="flex-grow:1">
            <b>{{ auth()->user()->name }}</b>
            <form method="POST" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button type="submit" class="logout-btn">Sign out</button>
            </form>
        </div>
    </div>
</nav>

<main class="content">
    @if (session('success'))
        <div class="flash">{{ session('success') }}</div>
    @endif
    @if ($errors->any() && ! $errors->has('email'))
        <div class="err-flash">{{ $errors->first() }}</div>
    @endif

    @yield('content')
</main>

</div>
</body>
</html>