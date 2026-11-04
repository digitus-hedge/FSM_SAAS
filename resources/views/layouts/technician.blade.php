<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FFFFFF">
<title>@yield('title', 'Field View') — {{ optional(\App\Models\Tenant::current())->name ?? 'AIYWAH FSM' }}</title>
@php
    $brandTenant = \App\Models\Tenant::current();
    $brandFont   = \App\Models\BrandFont::where('key', $brandTenant?->font_key ?? 'dm_sans')->first();
    $googleParam = $brandFont->google_param ?? 'Inter:wght@400;500;600;700;800;900';
    $fontBody    = $brandFont->body_family ?? 'Inter';
    $fontDisplay = $brandFont->display_family ?? 'Inter';
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family={{ $googleParam }}&display=swap" rel="stylesheet">
@include('technician._design_system', [
    'accentHex'   => $accentHex ?? ($brandTenant?->primary_color ?? '#E22A6B'),
    'fontBody'    => $fontBody,
    'fontDisplay' => $fontDisplay,
])
<style>
/* Mobile & tablet (<=768px): the app fills the screen, fixed header/nav, scrollable middle. */
.app{height:100dvh;width:100%;max-width:480px;margin:0 auto;display:flex;flex-direction:column;background:var(--bg);position:relative;overflow:hidden}

/* Desktop (>768px): locked to a fixed phone-card width, centered on the page — not a stretched/responsive layout. */
@media (min-width:769px){
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 16px}
    .app{height:min(860px,88vh);max-width:430px;border-radius:28px;border:1px solid var(--line);box-shadow:0 30px 70px -25px rgba(15,13,25,.35)}
}
</style>
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
    <a href="{{ route('technician.jobs.index') }}" {!! request()->routeIs('technician.jobs.index') ? 'aria-current="page"' : '' !!}>@include('technician._icon', ['name'=>'home','size'=>23])Today</a>
    @if ($isTeamLead)
    <a href="{{ route('technician.team.index') }}" style="position:relative" {!! request()->routeIs('technician.team.*') ? 'aria-current="page"' : '' !!}>
      @include('technician._icon', ['name'=>'users','size'=>23])Team
      @if ($needsActionCount)<span class="dot">{{ $needsActionCount }}</span>@endif
    </a>
    @endif
    <a href="{{ route('technician.profile') }}" {!! request()->routeIs('technician.profile') ? 'aria-current="page"' : '' !!}>@include('technician._icon', ['name'=>'user','size'=>23])Profile</a>
  </nav>

  <div class="sheetwrap" style="display:none" id="drawer-wrap">
    <nav class="drawer" aria-label="Menu">
      <div class="dh">
          <div class="mk">{{ strtoupper(substr($tenantObj->name ?? 'AF', 0, 2)) }}</div>
          <div>
              <div style="font-family:var(--font-display);font-weight:700;font-size:18px">{{ $tenantObj->name ?? '' }}</div>
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
</div>

<script>
const toggle = document.getElementById('drawer-toggle');
const wrap = document.getElementById('drawer-wrap');
toggle.addEventListener('change', () => { wrap.style.display = toggle.checked ? 'flex' : 'none'; });
wrap.addEventListener('click', (e) => { if (e.target === wrap) { toggle.checked = false; wrap.style.display = 'none'; } });
</script>
</body>
</html>