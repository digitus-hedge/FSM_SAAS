<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Team') — {{ \App\Models\Tenant::current()?->name ?? config('app.name') }}</title>
@include('technician._design_system')
<style>
body{font-family:var(--font-body)}
h1,h2,h3,.fig{font-family:var(--font-display)}

/* ---- shell ---- */
.shell{display:grid;grid-template-columns:252px minmax(0,1fr);min-height:100vh}
.side{position:sticky;top:0;height:100vh;display:flex;flex-direction:column;padding:22px 16px 20px;border-right:1px solid var(--line);background:var(--surface);overflow:auto}
.logo{display:flex;align-items:center;gap:10px;padding:2px 10px 26px;font:700 20px/1 var(--font-display);letter-spacing:-.02em;color:var(--ink)}
.logo .mk{width:32px;height:32px;border-radius:9px;background:var(--ink);color:var(--bg);display:grid;place-items:center;font:700 13px var(--font-display);flex-shrink:0;overflow:hidden}
.logo .mk img{width:100%;height:100%;object-fit:cover}
.navitem{display:flex;align-items:center;gap:12px;width:100%;border:0;background:none;text-align:left;padding:11px 14px;border-radius:12px;font-weight:600;color:var(--ink);text-decoration:none;font-size:14.5px}
.navitem + .navitem{margin-top:3px}
.navitem .ic{color:var(--muted);flex-shrink:0}
.navitem:hover{background:var(--soft)}
.navitem.current{background:var(--tint);font-weight:700}
.navitem.current .ic{color:var(--accent)}
.navitem .cnt{margin-left:auto;background:var(--red);color:#fff;border-radius:999px;min-width:20px;height:20px;font-size:11px;font-weight:700;display:grid;place-items:center;padding:0 6px}
.sidefoot{margin-top:auto;border:1px solid var(--line);border-radius:16px;padding:14px;font-size:13px;color:var(--muted)}
.sidefoot b{display:block;color:var(--ink);font:600 14.5px var(--font-display);margin-bottom:2px}

/* ---- main ---- */
.main{min-width:0;padding:22px 36px 60px}
.page{max-width:1200px;margin:0 auto}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:4px 0 24px}
.crumb{font-weight:700;color:var(--muted);font-size:13.5px}
.topbar .right{display:flex;align-items:center;gap:12px}
.bellbtn{position:relative;width:40px;height:40px;border-radius:999px;border:1px solid var(--line);background:var(--surface);display:grid;place-items:center;color:var(--muted);flex-shrink:0}
.bellbtn .dotb{position:absolute;top:8px;right:9px;width:8px;height:8px;border-radius:999px;background:var(--red);border:2px solid var(--surface)}
.me{display:flex;align-items:center;gap:10px}
.me .av{width:38px;height:38px;border-radius:999px;background:var(--ink);color:var(--bg);display:grid;place-items:center;font-weight:700;font-size:13px;flex-shrink:0}
.me b{display:block;line-height:1.2;font-size:13.5px}
.me small{color:var(--muted);line-height:1.2;display:block;font-size:12px}

/* ---- page head ---- */
.pagehead{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.pagehead h1{font-size:28px;line-height:1.15;margin:0;letter-spacing:-.02em;font-weight:700}
.pagehead p{color:var(--muted);font-size:14px;margin-top:6px}

/* ---- kpis ---- */
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:22px}
.kpi{border:1px solid var(--line);background:var(--surface);border-radius:16px;padding:16px 18px;text-align:left;display:flex;flex-direction:column;gap:4px}
.kpi span{color:var(--muted);font-size:13px;font-weight:600}
.kpi b{font:700 30px var(--font-display);letter-spacing:-.02em}
.kpi.dark{background:var(--ink);color:#fff;border-color:var(--ink)}
.kpi.dark span{color:rgba(255,255,255,.65)}

/* ---- cards/sections ---- */
.card{border:1px solid var(--line);border-radius:16px;background:var(--surface);padding:18px}
.card + .card{margin-top:16px}
.sech{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px}
.sech h3{font-size:15.5px;font-weight:700;margin:0}
.sech a, .sech button{color:var(--ink);font-weight:600;font-size:13px;text-decoration:underline;text-underline-offset:3px;background:none;border:0;cursor:pointer}

/* ---- table ---- */
.tblwrap{overflow-x:auto}
.tbl{width:100%;border-collapse:collapse}
.tbl th{font-size:11.5px;color:var(--muted);text-align:left;padding:10px 12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--line);white-space:nowrap}
.tbl td{padding:13px 12px;border-bottom:1px solid var(--line);vertical-align:middle;font-size:13.5px}
.tbl tr:last-child td{border-bottom:0}
.tbl tr:hover td{background:var(--soft)}

/* ---- chip / avatar ---- */
.chip{--c:var(--slate);display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-weight:700;font-size:12px;color:var(--c);background:color-mix(in srgb,var(--c) 14%,var(--surface));white-space:nowrap}
.av2{width:34px;height:34px;border-radius:999px;color:#fff;display:grid;place-items:center;font-weight:700;font-size:12.5px;flex-shrink:0}

/* ---- buttons ---- */
.btn{display:inline-flex;align-items:center;gap:7px;border-radius:10px;padding:9px 15px;font-weight:700;font-size:13.5px;border:1px solid transparent;white-space:nowrap;cursor:pointer;font-family:inherit}
.btn.dark{background:var(--ink);color:var(--bg)}
.btn.dark:hover{filter:brightness(1.12)}
.btn.ghost{background:var(--surface);border:1px solid var(--line);color:var(--ink)}
.btn.ghost:hover{border-color:var(--ink)}
.btn.sm{padding:6px 12px;font-size:12.5px}

/* ---- empty state ---- */
.empty{padding:40px 16px;text-align:center;color:var(--muted);font-size:13.5px}

/* ---- responsive ---- */
.menubtn{display:none}
@media (max-width:1000px){
  .shell{grid-template-columns:1fr}
  .side{position:fixed;z-index:30;left:0;top:0;bottom:0;width:260px;transform:translateX(-105%);transition:transform .2s}
  .side.open{transform:none}
  .main{padding:16px 18px 60px}
  .menubtn{display:grid;width:38px;height:38px;border-radius:10px;border:1px solid var(--line);background:var(--surface);place-items:center;color:var(--ink)}
  .me small{display:none}
}
</style>
</head>
<body>
@php
    $navNeeds = ($needsAction ?? collect())->count();
    $navOverrides = ($overrides ?? collect())->count();
    $navConflicts = ($conflicts ?? collect())->count();
    $navCount = $navNeeds + $navOverrides + $navConflicts;
@endphp
<div class="shell">
    <aside class="side" id="webSide">
        <div class="logo">
            <span class="mk">{{ strtoupper(mb_substr(\App\Models\Tenant::current()?->name ?? 'A', 0, 2)) }}</span>
            <span>{{ \App\Models\Tenant::current()?->name ?? config('app.name') }}</span>
        </div>
        <nav>
            <a href="{{ route('technician.team.desktop') }}" class="navitem {{ request()->routeIs('technician.team.desktop') ? 'current' : '' }}">
                <svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>
                <span>Home</span>
            </a>
            <a href="{{ route('technician.team.desktop.requests') }}" class="navitem {{ request()->routeIs('technician.team.desktop.requests') ? 'current' : '' }}">
                <svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>
                <span>Requests</span>
                @if ($navNeeds > 0)<span class="cnt">{{ $navNeeds }}</span>@endif
            </a>
            <a href="{{ route('technician.team.desktop.overrides') }}" class="navitem {{ request()->routeIs('technician.team.desktop.overrides') ? 'current' : '' }}">
                <svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
                <span>Overrides</span>
                @if ($navOverrides > 0)<span class="cnt">{{ $navOverrides }}</span>@endif
            </a>
            <a href="{{ route('technician.team.desktop.conflicts') }}" class="navitem {{ request()->routeIs('technician.team.desktop.conflicts') ? 'current' : '' }}">
                <svg class="ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                <span>Field conflicts</span>
                @if ($navConflicts > 0)<span class="cnt">{{ $navConflicts }}</span>@endif
            </a>
        </nav>
        <div class="sidefoot">
            <b>Need help?</b>
            Contact your Operations Manager for anything outside your team.
        </div>
    </aside>

    <div class="main">
        <div class="page">
            <header class="topbar">
                <div style="display:flex;align-items:center;gap:12px">
                    <button type="button" class="menubtn" id="webMenuBtn" aria-label="Menu">
                        @include('technician._icon', ['name' => 'menu', 'size' => 18])
                    </button>
                    <span class="crumb">@yield('crumb', 'Team Lead')</span>
                </div>
                <div class="right">
                    <a href="{{ route('technician.notifications') }}" class="bellbtn" aria-label="Notifications">
                        @include('technician._icon', ['name' => 'bell', 'size' => 18])
                        @if ($navCount > 0)<span class="dotb"></span>@endif
                    </a>
                    <a href="{{ route('technician.profile') }}" class="me" style="text-decoration:none;color:inherit">
                        @php
                            $wi = collect(preg_split('/\s+/', trim(auth()->user()->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                        @endphp
                        <span class="av">{{ strtoupper($wi ?: '?') }}</span>
                        <span>
                            <b>{{ auth()->user()->name }}</b>
                            <small>Team Lead</small>
                        </span>
                    </a>
                </div>
            </header>

            @yield('content')
        </div>
    </div>
</div>

<script>
document.getElementById('webMenuBtn')?.addEventListener('click', function () {
    document.getElementById('webSide').classList.toggle('open');
});
</script>
</body>
</html>