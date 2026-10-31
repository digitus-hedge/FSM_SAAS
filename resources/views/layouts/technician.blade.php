<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FFFFFF">
<title>@yield('title', 'Field View') — {{ optional(\App\Models\Tenant::current())->name ?? 'AIYWAH FSM' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@700;800&display=swap" rel="stylesheet">
@include('technician._design_system', ['accentHex' => $accentHex ?? (optional(\App\Models\Tenant::current())->primary_color ?? '#2F5BEA')])
<style>.app{min-height:100dvh;max-width:480px;margin:0 auto;display:flex;flex-direction:column;background:var(--bg);position:relative}</style>
</head>
<body>
@php
    $u = auth()->user();
    $tenantObj = $u?->tenant;
    $isTeamLead = $u?->role?->code === 'team_lead';
    $needsActionCount = $isTeamLead
        ? \App\Models\ServiceRequest::where('team_lead_user_id', $u->id)->whereIn('status', ['pending','quoted','approved'])->count()
        : 0;
@endphp
<input type="checkbox" id="drawer-toggle" style="display:none">
<div class="app">
  <div class="hdr">
    <label for="drawer-toggle" class="ib" aria-label="Menu">@include('technician._icon', ['name'=>'menu','size'=>24])</label>
    <h1>@yield('title', 'Field View')</h1>
    <button type="button" class="badge"><i></i>All sent</button>
    <a href="{{ route('technician.notifications') }}" class="ib" aria-label="Notifications">@include('technician._icon', ['name'=>'bell','size'=>22])</a>
  </div>

  <div class="main">
    @if (session('success'))<div class="banner green">@include('technician._icon', ['name'=>'check','size'=>19])<span>{{ session('success') }}</span></div>@endif
    @if ($errors->any())<div class="banner red">@include('technician._icon', ['name'=>'x','size'=>19])<span>{{ $errors->first() }}</span></div>@endif
    @yield('content')
  </div>

  <nav class="nav">
    <a href="{{ route('technician.jobs.index') }}" {!! request()->routeIs('technician.jobs.index') ? 'aria-current=page' : '' !!}>@include('technician._icon', ['name'=>'home','size'=>23])Today</a>
    @if ($isTeamLead)
    <a href="{{ route('technician.team.index') }}" style="position:relative" {!! request()->routeIs('technician.team.*') ? 'aria-current=page' : '' !!}>
      @include('technician._icon', ['name'=>'users','size'=>23])Team
      @if ($needsActionCount)<span class="dot">{{ $needsActionCount }}</span>@endif
    </a>
    @endif
    <a href="{{ route('technician.profile') }}" {!! request()->routeIs('technician.profile') ? 'aria-current=page' : '' !!}>@include('technician._icon', ['name'=>'user','size'=>23])Profile</a>
  </nav>
</div>

<div class="sheetwrap" style="display:none" id="drawer-wrap">
  <nav class="drawer" aria-label="Menu">
    <div class="dh">
        <div class="mk">{{ strtoupper(substr($tenantObj->name ?? 'AF', 0, 2)) }}</div>
        <div>
            <div style="font:700 18px Figtree">{{ $tenantObj->name ?? '' }}</div>
            <div class="muted" style="font-size:14px">{{ $u->name }}, {{ $isTeamLead ? 'Team Lead' : 'Field Technician' }}</div>
        </div>
    </div>
    <div class="dl">
        <a href="{{ route('technician.jobs.index') }}" class="dit">@include('technician._icon', ['name'=>'home','size'=>22])<span class="grow">Today</span></a>
        <a href="{{ route('technician.jobs.all') }}" class="dit">@include('technician._icon', ['name'=>'list','size'=>22])<span class="grow">All my jobs</span></a>
        <a href="{{ route('technician.notifications') }}" class="dit">@include('technician._icon', ['name'=>'bell','size'=>22])<span class="grow">Notifications</span></a>
        @if ($isTeamLead)
        <a href="{{ route('technician.team.index') }}" class="dit">@include('technician._icon', ['name'=>'users','size'=>22])<span class="grow">Team</span>@if ($needsActionCount)<span class="chip sm" style="--c:var(--red)">{{ $needsActionCount }}</span>@endif</a>
        @endif
        <a href="{{ route('technician.profile') }}" class="dit">@include('technician._icon', ['name'=>'user','size'=>22])<span class="grow">Profile</span></a>
    </div>
    <div class="df">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dit" style="width:100%">@include('technician._icon', ['name'=>'out','size'=>22])<span class="grow">Sign out</span></button>
        </form>
    </div>
  </nav>
</div>

<script>
const toggle = document.getElementById('drawer-toggle');
const wrap = document.getElementById('drawer-wrap');
toggle.addEventListener('change', () => { wrap.style.display = toggle.checked ? 'flex' : 'none'; });
wrap.addEventListener('click', (e) => { if (e.target === wrap) { toggle.checked = false; wrap.style.display = 'none'; } });
</script>
</body>
</html>