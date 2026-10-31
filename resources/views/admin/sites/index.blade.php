@extends('layouts.admin')

@php
    $sites          = $sites          ?? collect();
    $clients        = $clients        ?? collect();
    $perPage        = $perPage        ?? 25;
    $perPageOptions = $perPageOptions ?? [10, 25, 50, 100];
@endphp

@section('title', 'Sites')

@section('content')
<style>
  .page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:22px}
  .page-head h1{font-family:var(--font-display),sans-serif;font-size:34px;font-weight:800;line-height:1.08;letter-spacing:-.02em;margin:0;color:#0F0F17}
  .page-head p{color:#5F5D6E;font-size:14px;margin-top:6px}

  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:var(--font-body),sans-serif;font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:all .12s;white-space:nowrap}
  .btn-primary{background:var(--amber);color:#fff}
  .btn-primary:hover{background:color-mix(in srgb, var(--amber) 85%, black 15%)}
  .btn-ghost{background:#fff;color:#0F0F17;border-color:#E5E4EC}
  .btn-ghost:hover{border-color:#0F0F17}

  /* Toolbar — one rounded card holding all filters */
  .toolbar{display:flex;align-items:stretch;margin-bottom:16px;background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:4px;overflow:hidden;flex-wrap:wrap}
  .tb-search{flex:1;min-width:220px;position:relative;display:flex;align-items:center}
  .tb-search input{width:100%;border:0;outline:none;background:transparent;font-family:var(--font-body);font-size:14px;color:#0F0F17;padding:12px 16px}
  .tb-search input::placeholder{color:#9A98A7}
  .tb-spin{position:absolute;right:14px;width:12px;height:12px;border:2px solid #E5E4EC;border-top-color:var(--amber);border-radius:50%;display:none;animation:sspin .8s linear infinite}
  @keyframes sspin{to{transform:rotate(360deg)}}
  .tb-sel{border:0;background:transparent;font-family:var(--font-body);font-size:14px;font-weight:600;color:#0F0F17;padding:10px 32px 10px 16px;outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;border-left:1px solid #E5E4EC;min-width:140px}
  .tb-clear{display:inline-flex;align-items:center;gap:4px;font-size:12.5px;font-weight:700;color:#B33636;text-decoration:none;padding:0 16px;border-left:1px solid #E5E4EC}
  .tb-clear:hover{background:#FFF5F5}

  /* Table card */
  .card{background:#fff;border:1px solid #E5E4EC;border-radius:16px;overflow:hidden}
  .tblwrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;min-width:1000px;font-size:14px}
  thead th{text-align:left;padding:16px 20px;font-weight:600;font-size:13px;color:#5F5D6E;background:#FAFAFC;border-bottom:1px solid #EEEDF3;white-space:nowrap}
  tbody td{padding:18px 20px;border-bottom:1px solid #F2F1F6;vertical-align:middle;color:#0F0F17}
  tbody tr:last-child td{border-bottom:0}
  tbody tr{cursor:pointer;transition:background .1s}
  tbody tr:hover{background:#FAFAFC}

  .code-pill{display:inline-block;font-family:var(--font-body);font-size:12px;font-weight:600;color:var(--amber);background:var(--yellow-soft);padding:4px 10px;border-radius:6px;letter-spacing:.02em;white-space:nowrap}
  .cell-name{font-weight:700;color:#0F0F17}
  .cell-muted{color:#5F5D6E}
  .cell-num{font-weight:600;color:#0F0F17;font-variant-numeric:tabular-nums}

  .status{display:inline-flex;align-items:center;font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:999px;letter-spacing:.01em;white-space:nowrap}
  .status-ok{background:#E6F7EC;color:#1D7A3A}
  .status-off{background:#F2F1F6;color:#5F5D6E}
  .status-warn{background:#FEF3C7;color:#92400E}

  .empty{padding:70px 20px;text-align:center;color:#5F5D6E;font-size:14px}
  .empty h4{font-family:var(--font-display);font-size:18px;font-weight:700;color:#0F0F17;margin:0 0 4px}
  .empty a{color:var(--amber);text-decoration:none;font-weight:700}

  .foot{padding:16px 20px;background:#FCFBFF;border-top:1px solid #EEEDF3;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;font-size:13px;color:#5F5D6E}
  .foot strong{color:#0F0F17}

  .per-page{display:flex;align-items:center;gap:8px;font-size:12.5px;color:#5F5D6E}
  .per-page select{font-family:var(--font-body);font-size:12.5px;padding:7px 24px 7px 12px;border-radius:999px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='5' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
  .pg-nav{display:flex;gap:4px}
  .pg-nav a,.pg-nav span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 10px;font-size:12.5px;font-weight:700;color:#0F0F17;background:#fff;border:1px solid #E5E4EC;border-radius:8px;text-decoration:none}
  .pg-nav a:hover{background:#F2F1F6}
  .pg-nav .on{background:var(--amber);border-color:var(--amber);color:#fff}
  .pg-nav .off{color:#9A98A7;background:#F2F1F6;cursor:not-allowed;pointer-events:none}
</style>

<div class="page-head">
  <div>
    <h1>Sites</h1>
    <p>Where the work is done.</p>
  </div>
  <div style="display:flex;gap:10px">
   <a href="{{ route('admin.sites.import.show') }}" class="btn btn-ghost">
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
  Import
</a>
    <a href="{{ route('admin.sites.create') }}" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      New site
    </a>
  </div>
</div>

@php $hasFilter = request()->filled('q') || request()->filled('client_id') || request()->filled('status') || request()->filled('location'); @endphp

<form method="GET" class="toolbar" id="site-filter">
  <div class="tb-search">
    <input type="text" name="q" value="{{ request('q') }}"
           placeholder="Search by code, name, address or city"
           autocomplete="off" id="site-search">
    <span class="tb-spin" id="site-spin"></span>
  </div>
  <select name="client_id" class="tb-sel auto-submit">
    <option value="">All clients</option>
    @foreach ($clients as $c)
      <option value="{{ $c->id }}" @selected((string) request('client_id') === (string) $c->id)>{{ $c->name }}</option>
    @endforeach
  </select>
  <select name="status" class="tb-sel auto-submit">
    <option value="">All status</option>
    <option value="active"   @selected(request('status')==='active')>Active</option>
    <option value="inactive" @selected(request('status')==='inactive')>Inactive</option>
  </select>
  <select name="location" class="tb-sel auto-submit">
    <option value="">Any location</option>
    <option value="set"   @selected(request('location')==='set')>Location set</option>
    <option value="unset" @selected(request('location')==='unset')>Not set</option>
  </select>
  @if ($hasFilter)
    <a href="{{ route('admin.sites.index') }}" class="tb-clear">× Clear</a>
  @endif
  <input type="hidden" name="per_page" value="{{ $perPage }}">
</form>

<div class="card">
  <div class="tblwrap">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Client</th>
          <th>City</th>
          <th>Location</th>
          <th>Radius</th>
          <th>Always enforce</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($sites as $site)
          <tr onclick="window.location='{{ route('admin.sites.edit', $site) }}'">
            <td><span class="code-pill">{{ $site->site_code }}</span></td>
            <td class="cell-name">{{ $site->name }}</td>
            <td class="cell-muted">{{ $site->client?->name ?? '—' }}</td>
            <td class="cell-muted">{{ $site->city ?? '—' }}</td>
            <td>
              @if ($site->lat && $site->lng)
                <span class="status status-ok">Saved</span>
              @else
                <span class="status status-warn">Not set</span>
              @endif
            </td>
            <td class="cell-num">
              @if ($site->geofence_radius_meters)
                {{ $site->geofence_radius_meters }} m
              @else
                <span style="color:#9A98A7">—</span>
              @endif
            </td>
            <td class="cell-muted">{{ ($site->always_enforce_geofence ?? false) ? 'Yes' : 'No' }}</td>
            <td>
              @if ($site->is_active)
                <span class="status status-ok">Active</span>
              @else
                <span class="status status-off">Inactive</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="empty">
              <h4>No sites yet</h4>
              <p><a href="{{ route('admin.sites.create') }}">Add your first site</a> to start logging service requests.</p>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="foot">
    <div>
      @if ($sites->total() > 0)
        Showing <strong>{{ $sites->firstItem() }}</strong>–<strong>{{ $sites->lastItem() }}</strong>
        of <strong>{{ number_format($sites->total()) }}</strong> {{ Str::plural('site', $sites->total()) }}
      @else
        No results
      @endif
    </div>

    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <label class="per-page">
        Rows:
        <select onchange="window.location = this.value">
          @foreach ($perPageOptions as $opt)
            <option value="{{ $sites->url(1).'&per_page='.$opt }}" @selected($perPage === $opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </label>

      @if ($sites->hasPages())
        <nav class="pg-nav">
          @if ($sites->onFirstPage()) <span class="off">‹</span>
          @else <a href="{{ $sites->previousPageUrl() }}">‹</a> @endif

          @php
            $cur=$sites->currentPage(); $last=$sites->lastPage();
            $show=collect(range(1,$last))->filter(fn($p)=>$p===1||$p===$last||abs($p-$cur)<=1)->values();
          @endphp
          @foreach ($show as $i => $p)
            @if ($i>0 && $p-$show[$i-1]>1) <span>…</span> @endif
            @if ($p===$cur) <span class="on">{{ $p }}</span>
            @else <a href="{{ $sites->url($p) }}">{{ $p }}</a> @endif
          @endforeach

          @if ($sites->hasMorePages()) <a href="{{ $sites->nextPageUrl() }}">›</a>
          @else <span class="off">›</span> @endif
        </nav>
      @endif
    </div>
  </div>
</div>

<script>
(function () {
  const form=document.getElementById('site-filter');
  const search=document.getElementById('site-search');
  const spin=document.getElementById('site-spin');
  let timer=null;
  search.addEventListener('input',()=>{clearTimeout(timer);spin.style.display='block';timer=setTimeout(()=>form.submit(),400)});
  search.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();clearTimeout(timer);form.submit()}});
  document.querySelectorAll('.auto-submit').forEach(s=>s.addEventListener('change',()=>form.submit()));
})();
</script>
@endsection