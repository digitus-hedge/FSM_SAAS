<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1B1A24">
<title>@yield('title', 'Field View') — AIYWAH FSM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#ffffff; --ink:#1B1A24; --muted:#5F5D6E; --subtle:#8B89A0;
    --line:#E6E4EE; --soft:#F5F4FA;
    --accent:#2F5BEA; --accent-soft:#EEF2FF; --accent-ink:#2749C8;
    --green:#1D7A3A; --green-soft:#E6F7EC;
    --red:#C2381B; --red-soft:#FDECE7;
    --amber:#8A5A00; --amber-soft:#FFF4E6;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{height:100%}
  body{background:#F2F1F6;color:var(--ink);font-family:'DM Sans',system-ui,sans-serif;font-size:15px;line-height:1.5;padding:max(16px,env(safe-area-inset-top,0px)) max(0px,env(safe-area-inset-right,0px)) max(16px,env(safe-area-inset-bottom,0px)) max(0px,env(safe-area-inset-left,0px))}

  /* Phone-sized device, floats centered on the grey background */
  .app{display:flex;flex-direction:column;height:calc(100vh - 32px);max-width:380px;margin:0 auto;background:var(--bg);box-shadow:0 20px 60px rgba(0,0,0,.12), 0 4px 12px rgba(0,0,0,.06);position:relative;overflow:hidden;border-radius:28px;border:1px solid var(--line);isolation:isolate}

  @media (max-width:600px){
    body{padding:env(safe-area-inset-top,0px) env(safe-area-inset-right,0px) env(safe-area-inset-bottom,0px) env(safe-area-inset-left,0px)}
    .app{height:100vh;border-radius:0;border:0;box-shadow:none;max-width:none}
  }

  /* ===== Top app bar ===== */
  .appbar{flex:0 0 auto;min-height:60px;display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--bg);border-bottom:1px solid var(--line);position:relative;z-index:10;border-top-left-radius:28px;border-top-right-radius:28px}
  @media (max-width:600px){.appbar{border-radius:0}}
  .appbar h1{font-family:'Figtree';font-size:22px;font-weight:800;flex:1;letter-spacing:-.5px}
  .appbar .back,.appbar .ham{background:none;border:0;color:var(--ink);cursor:pointer;padding:8px;margin:-8px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .appbar .back:hover,.appbar .ham:hover{background:var(--soft)}
  .appbar .sent-chip{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid var(--line);border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;color:var(--ink)}
  .appbar .sent-chip::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--green)}
  .appbar .bell{background:none;border:0;position:relative;padding:8px;cursor:pointer;color:var(--ink);text-decoration:none;display:flex;align-items:center;justify-content:center}
  .appbar .bell .dot{position:absolute;top:4px;right:4px;background:var(--red);color:#fff;font-size:10px;font-weight:800;border-radius:999px;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;padding:0 4px}

  /* ===== Content ===== */
  .main{flex:1;overflow-y:auto;overscroll-behavior-y:contain;padding:14px 16px 20px;position:relative;z-index:1}

  /* ===== Bottom nav ===== */
  .bottomnav{flex:0 0 auto;display:grid;grid-template-columns:1fr 1fr 1fr;border-top:1px solid var(--line);background:var(--bg);padding-bottom:env(safe-area-inset-bottom,0px);border-bottom-left-radius:28px;border-bottom-right-radius:28px;position:relative;z-index:10}
  @media (max-width:600px){.bottomnav{border-radius:0}}
  .bottomnav a{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 4px 8px;text-decoration:none;color:var(--muted);font-size:11.5px;font-weight:600;position:relative}
  .bottomnav a.active{color:var(--ink)}
  .bottomnav a.active::before{content:'';position:absolute;top:0;left:30%;right:30%;height:2.5px;background:var(--accent);border-radius:0 0 2px 2px}
  .bottomnav a svg{width:22px;height:22px}

  /* ===== Side drawer (INSIDE .app, slides over the phone content) ===== */
  .drawer-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);z-index:90;opacity:0;pointer-events:none;transition:opacity .2s;border-radius:28px}
  .drawer-backdrop.open{opacity:1;pointer-events:auto}
  @media (max-width:600px){.drawer-backdrop{border-radius:0}}

  .drawer{position:absolute;top:0;left:0;bottom:0;width:88%;max-width:280px;background:var(--bg);z-index:100;display:flex;flex-direction:column;transform:translateX(-100%);transition:transform .22s;
  /* box-shadow:4px 0 24px rgba(0,0,0,.25); */
  border-top-left-radius:28px;
  border-bottom-left-radius:28px;
  overflow:hidden}
  .drawer.open{transform:translateX(0)}
  @media (max-width:600px){.drawer{border-radius:0}}

  .drawer-close{position:absolute;top:12px;right:12px;background:none;border:0;color:var(--muted);cursor:pointer;padding:8px;border-radius:50%;z-index:2;display:flex;align-items:center;justify-content:center}
  .drawer-close:hover{background:var(--soft);color:var(--ink)}

  .drawer-head{display:flex;align-items:center;gap:12px;padding:18px 48px 14px 18px;border-bottom:1px solid var(--line)}
  .drawer-head .av{width:46px;height:46px;border-radius:10px;background:var(--ink);color:#fff;font-family:'Figtree';font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .drawer-head .org{font-family:'Figtree';font-size:16px;font-weight:800}
  .drawer-head .sub{font-size:12.5px;color:var(--muted);margin-top:2px}
  .drawer-body{flex:1;overflow-y:auto;padding:10px}
  .drawer-item{display:flex;align-items:center;gap:14px;padding:12px 14px;border-radius:12px;color:var(--ink);text-decoration:none;font-size:15px;font-weight:600;position:relative}
  .drawer-item:hover,.drawer-item.active{background:var(--soft)}
  .drawer-item svg{width:20px;height:20px;color:var(--muted);flex-shrink:0}
  .drawer-item .badge{margin-left:auto;background:var(--red-soft);color:var(--red);font-size:11px;font-weight:800;padding:2px 8px;border-radius:999px}
  .drawer-foot{border-top:1px solid var(--line);padding:10px}
  .drawer-foot form{margin:0}
  .drawer-foot button{background:none;border:0;cursor:pointer;font:inherit;color:inherit;width:100%;text-align:left}
  .drawer-note{font-size:11.5px;color:var(--subtle);padding:8px 14px}

  /* ===== Reusable ===== */
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;margin-bottom:12px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;padding:12px 18px;font-family:'DM Sans';font-size:14.5px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:all .12s;width:100%}
  .btn-primary{background:var(--accent);color:#fff}
  .btn-primary:hover{background:var(--accent-ink)}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-danger-out{background:#fff;color:var(--red);border-color:#F5C7C0}

  .chip{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:999px;white-space:nowrap}
  .chip-emerg{background:var(--red-soft);color:var(--red)}
  .chip-high{background:#FFF4E6;color:#B35F00}
  .chip-med{background:var(--accent-soft);color:var(--accent)}
  .chip-low{background:var(--soft);color:var(--muted)}
  .chip-assigned{background:var(--accent-soft);color:var(--accent)}
  .chip-notaccepted{background:#F2F1F6;color:var(--muted)}
  .chip-rework{background:#FFF4E6;color:#B35F00}
  .chip-ok{background:var(--green-soft);color:var(--green)}
  .chip-dot{width:6px;height:6px;border-radius:50%;background:currentColor}

  /* ===== Toast ===== */
  .toast{position:fixed;left:50%;bottom:calc(80px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);background:var(--ink);color:#fff;padding:12px 18px;border-radius:14px;font-size:14px;z-index:120;box-shadow:0 10px 30px rgba(0,0,0,.3);max-width:88%}
</style>
</head>
<body>

<div class="app">

  {{-- ===== Top app bar ===== --}}
  @hasSection('appbar')
    @yield('appbar')
  @else
    <div class="appbar">
      <button type="button" class="ham" aria-label="Menu" onclick="openDrawer()">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="7" x2="21" y2="7"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="17" x2="21" y2="17"/></svg>
      </button>
      <h1>@yield('title', 'Today')</h1>
      <span class="sent-chip">All sent</span>
      <a href="{{ route('technician.notifications') }}" class="bell" aria-label="Notifications">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        @if (($notifCount ?? 0) > 0)<span class="dot">{{ $notifCount }}</span>@endif
      </a>
    </div>
  @endif

  {{-- ===== Content ===== --}}
  <main class="main">@yield('content')</main>

  {{-- ===== Bottom nav ===== --}}
  <nav class="bottomnav">
    <a href="{{ route('technician.jobs.index') }}" class="{{ request()->routeIs('technician.jobs.*') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
      Today
    </a>
    <a href="{{ route('technician.sync') }}" class="{{ request()->routeIs('technician.sync') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      Sync
    </a>
    <a href="{{ route('technician.profile') }}" class="{{ request()->routeIs('technician.profile') ? 'active' : '' }}">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Profile
    </a>
  </nav>


    @yield('overlays')

  {{-- ===== Side drawer (INSIDE .app so it slides OVER the phone content) ===== --}}
  <div class="drawer-backdrop" id="drawer-bd" onclick="closeDrawer()"></div>

  <aside class="drawer" id="drawer">
    <button type="button" class="drawer-close" aria-label="Close menu" onclick="closeDrawer()">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>

    @php $u = auth()->user(); @endphp
    <div class="drawer-head">
      <div class="av">{{ strtoupper(substr($u->tenant->name ?? 'AF', 0, 2)) }}</div>
      <div>
        <div class="org">{{ $u->tenant->name ?? 'Workspace' }}</div>
        <div class="sub">{{ $u->name }}, {{ $u->role?->name ?? 'Field Technician' }}</div>
      </div>
    </div>

    <nav class="drawer-body">
      <a href="{{ route('technician.jobs.index') }}" class="drawer-item {{ request()->routeIs('technician.jobs.index') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
        Today
      </a>
      <a href="{{ route('technician.jobs.all') }}" class="drawer-item {{ request()->routeIs('technician.jobs.all') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        All my jobs
      </a>
      <a href="{{ route('technician.notifications') }}" class="drawer-item {{ request()->routeIs('technician.notifications') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notifications
        @if (($notifCount ?? 0) > 0)<span class="badge">{{ $notifCount }}</span>@endif
      </a>
      <a href="{{ route('technician.sync') }}" class="drawer-item {{ request()->routeIs('technician.sync') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
        Sync
      </a>
      <a href="{{ route('technician.profile') }}" class="drawer-item {{ request()->routeIs('technician.profile') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
      </a>
    </nav>

    <div class="drawer-foot">
      <a href="#" class="drawer-item" onclick="event.preventDefault();alert('Add this site to your home screen from your browser menu.')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Install the app
      </a>
      <a href="tel:+971500000000" class="drawer-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        Call the office
      </a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="drawer-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign out
        </button>
      </form>
      <div class="drawer-note">Field View, prototype</div>
    </div>
  </aside>

</div> {{-- /.app --}}

@if (session('success'))
  <div class="toast" id="toast">{{ session('success') }}</div>
  <script>setTimeout(() => document.getElementById('toast')?.remove(), 2800);</script>
@endif

<script>
  function openDrawer()  { document.getElementById('drawer').classList.add('open'); document.getElementById('drawer-bd').classList.add('open'); }
  function closeDrawer() { document.getElementById('drawer').classList.remove('open'); document.getElementById('drawer-bd').classList.remove('open'); }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
  // Close when any link inside the drawer is tapped
  document.querySelectorAll('#drawer a').forEach(a => a.addEventListener('click', closeDrawer));
</script>

</body>
</html>