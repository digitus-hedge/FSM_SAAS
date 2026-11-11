@extends('layouts.admin')

@php
    $assets         = $assets         ?? collect();
    $sites          = $sites          ?? collect();
    $categories     = $categories     ?? collect();
    $perPage        = $perPage        ?? 10;
    $perPageOptions = $perPageOptions ?? [10, 25, 50, 100];
    $statusLabels   = ['active' => 'Active', 'under_repair' => 'Under repair', 'inactive' => 'Inactive', 'decommissioned' => 'Decommissioned'];
    $statusClass    = ['active' => 'status-ok', 'under_repair' => 'status-warn', 'inactive' => 'status-off', 'decommissioned' => 'status-off'];
    $hasFilter      = request()->filled('q') || request()->filled('site_id') || request()->filled('category_id')
                   || request()->filled('status') || request()->filled('warranty');
@endphp

@section('title', $plural)

@section('content')
<style>
  .page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:22px}
  .page-head h1{font-family:var(--font-display),sans-serif;font-size:34px;font-weight:800;line-height:1.08;letter-spacing:-.02em;margin:0;color:#0F0F17}
  .page-head p{color:#5F5D6E;font-size:14px;margin-top:6px}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:var(--font-body),sans-serif;font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:all .12s;white-space:nowrap}
  .btn-primary{background:var(--amber);color:#fff}
  .btn-primary:hover{background:color-mix(in srgb, var(--amber) 85%, black 15%)}
  .toolbar{display:flex;align-items:stretch;margin-bottom:16px;background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:4px;overflow:hidden;flex-wrap:wrap}
  .tb-search{flex:1;min-width:220px;position:relative;display:flex;align-items:center}
  .tb-search input{width:100%;border:0;outline:none;background:transparent;font-family:var(--font-body);font-size:14px;color:#0F0F17;padding:12px 16px}
  .tb-search input::placeholder{color:#9A98A7}
  .tb-spin{position:absolute;right:14px;width:12px;height:12px;border:2px solid #E5E4EC;border-top-color:var(--amber);border-radius:50%;display:none;animation:sspin .8s linear infinite}
  @keyframes sspin{to{transform:rotate(360deg)}}
  .tb-sel{border:0;background:transparent;font-family:var(--font-body);font-size:14px;font-weight:600;color:#0F0F17;padding:10px 32px 10px 16px;outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;border-left:1px solid #E5E4EC;min-width:130px}
  .tb-clear{display:inline-flex;align-items:center;gap:4px;font-size:12.5px;font-weight:700;color:#B33636;text-decoration:none;padding:0 16px;border-left:1px solid #E5E4EC}
  .tb-clear:hover{background:#FFF5F5}
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
  .status{display:inline-flex;align-items:center;font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:999px;letter-spacing:.01em;white-space:nowrap}
  .status-ok{background:#E6F7EC;color:#1D7A3A}
  .status-off{background:#F2F1F6;color:#5F5D6E}
  .status-warn{background:#FEF3C7;color:#92400E}
  .status-bad{background:#FDECE7;color:#C2381B}
  .empty{padding:70px 20px;text-align:center;color:#5F5D6E;font-size:14px}
  .empty h4{font-family:var(--font-display);font-size:18px;font-weight:700;color:#0F0F17;margin:0 0 4px}
  .empty a{color:var(--amber);text-decoration:none;font-weight:700}
  .foot{padding:16px 20px;background:#FCFBFF;border-top:1px solid #EEEDF3;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;font-size:13px;color:#5F5D6E}
  .foot strong{color:#0F0F17}
  .per-page{display:flex;align-items:center;gap:8px;font-size:12.5px;color:#5F5D6E}
  .per-page select{font-family:var(--font-body);font-size:12.5px;padding:7px 24px 7px 12px;border-radius:999px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;outline:none;cursor:pointer}
  .pg-nav{display:flex;gap:4px}
  .pg-nav a,.pg-nav span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 10px;font-size:12.5px;font-weight:700;color:#0F0F17;background:#fff;border:1px solid #E5E4EC;border-radius:8px;text-decoration:none}
  .pg-nav a:hover{background:#F2F1F6}
  .pg-nav .on{background:var(--amber);border-color:var(--amber);color:#fff}
  .pg-nav .off{color:#9A98A7;background:#F2F1F6;cursor:not-allowed;pointer-events:none}
  .alert-err{background:#FDECE7;border:1px solid #F2C2C2;color:#C2381B;padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
  .btn-ghost{background:#fff;color:#0F0F17;border-color:#E5E4EC}
  .btn-ghost:hover{border-color:#0F0F17}
</style>

<div class="page-head">
  <div>
    <h1>{{ $plural }}</h1>
    <p>Equipment at your clients' sites.</p>
  </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      @if (auth()->user()?->canDo('asset.view') && \App\Models\Tenant::current()?->hasFeature('assets_full'))
        <a href="{{ route('admin.assets.labels', request()->query()) }}" class="btn btn-ghost" target="_blank">Print labels</a>
      @endif
    @if (auth()->user()?->canDo('asset.import') && \App\Models\Tenant::current()?->hasFeature('assets_full'))
      <a href="{{ route('admin.assets.import') }}" class="btn btn-ghost">Import CSV</a>
    @endif
    @if (auth()->user()->canDo('asset.manage'))
      <a href="{{ route('admin.assets.create') }}" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        New {{ strtolower($label) }}
      </a>
    @endif
  </div>
</div>

@if (session('error'))
  <div class="alert-err">{{ session('error') }}</div>
@endif

<form method="GET" class="toolbar" id="asset-filter">
  <div class="tb-search">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by code, name, serial number or make" autocomplete="off" id="asset-search">
    <span class="tb-spin" id="asset-spin"></span>
  </div>
  <select name="site_id" class="tb-sel auto-submit">
    <option value="">All sites</option>
    @foreach ($sites as $s)
      <option value="{{ $s->id }}" @selected((string) request('site_id') === (string) $s->id)>{{ $s->name }}</option>
    @endforeach
  </select>
  <select name="category_id" class="tb-sel auto-submit">
    <option value="">All categories</option>
    @foreach ($categories as $c)
      <option value="{{ $c->id }}" @selected((string) request('category_id') === (string) $c->id)>{{ $c->name }}</option>
    @endforeach
  </select>
  <select name="status" class="tb-sel auto-submit">
    <option value="">All status</option>
    @foreach ($statusLabels as $k => $v)
      <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
    @endforeach
  </select>
  <select name="warranty" class="tb-sel auto-submit">
    <option value="">Any warranty</option>
    <option value="in_warranty" @selected(request('warranty') === 'in_warranty')>In warranty</option>
    <option value="expired"     @selected(request('warranty') === 'expired')>Expired</option>
    <option value="none"        @selected(request('warranty') === 'none')>No warranty date</option>
  </select>
  @if ($hasFilter)
    <a href="{{ route('admin.assets.index') }}" class="tb-clear">× Clear</a>
  @endif
  <input type="hidden" name="per_page" value="{{ $perPage }}">
</form>

<div class="card">
  <div class="tblwrap">
    <table>
      <thead>
        <tr>
          <th>Code</th><th>Name</th><th>Client</th><th>Site</th><th>Category</th><th>Serial no.</th><th>Warranty</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($assets as $a)
          <tr onclick="window.location='{{ \App\Models\Tenant::current()?->hasFeature('assets_full') ? route('admin.assets.show', $a) : route('admin.assets.edit', $a) }}'">
            <td><span class="code-pill">{{ $a->asset_code }}</span></td>
            <td class="cell-name">{{ $a->name }}</td>
            <td class="cell-muted">{{ $a->client?->name ?? '—' }}</td>
            <td class="cell-muted">{{ $a->site?->name ?? '—' }}</td>
            <td class="cell-muted">{{ $a->category?->name ?? '—' }}</td>
            <td class="cell-muted">{{ $a->serial_number ?: '—' }}</td>
            <td>
              @if ($a->status === 'decommissioned')
                <span style="color:#9A98A7">—</span>
              @elseif ($a->warranty_status === 'in_warranty')
                <span class="status status-ok">In warranty · {{ $a->warranty_expiry_date->format('d M Y') }}</span>
              @elseif ($a->warranty_status === 'expired')
                <span class="status status-bad">Expired · {{ $a->warranty_expiry_date->format('d M Y') }}</span>
              @else
                <span style="color:#9A98A7">—</span>
              @endif
            </td>
            <td><span class="status {{ $statusClass[$a->status] ?? 'status-off' }}">{{ $statusLabels[$a->status] ?? $a->status }}</span></td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="empty">
              @if ($hasFilter)
                <h4>No {{ strtolower($plural) }} match your search</h4>
                <p>Try different words or <a href="{{ route('admin.assets.index') }}">clear all filters</a>.</p>
              @else
                <h4>No {{ strtolower($plural) }} yet</h4>
                @if (auth()->user()->canDo('asset.manage'))
                  <p><a href="{{ route('admin.assets.create') }}">Add your first {{ strtolower($label) }}</a> to start tracking equipment.</p>
                @endif
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="foot">
    <div>
      @if ($assets->total() > 0)
        Showing <strong>{{ $assets->firstItem() }}</strong>–<strong>{{ $assets->lastItem() }}</strong>
        of <strong>{{ number_format($assets->total()) }}</strong>
      @else
        No results
      @endif
    </div>
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <label class="per-page">
        Rows:
        <select onchange="window.location = this.value">
          @foreach ($perPageOptions as $opt)
            <option value="{{ request()->fullUrlWithQuery(['per_page' => $opt, 'page' => 1]) }}" @selected($perPage === $opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </label>

      @if ($assets->hasPages())
        <nav class="pg-nav">
          @if ($assets->onFirstPage()) <span class="off">‹</span>
          @else <a href="{{ $assets->previousPageUrl() }}">‹</a> @endif
          @php
            $cur = $assets->currentPage(); $last = $assets->lastPage();
            $show = collect(range(1, $last))->filter(fn ($p) => $p === 1 || $p === $last || abs($p - $cur) <= 1)->values();
          @endphp
          @foreach ($show as $i => $p)
            @if ($i > 0 && $p - $show[$i - 1] > 1) <span>…</span> @endif
            @if ($p === $cur) <span class="on">{{ $p }}</span>
            @else <a href="{{ $assets->url($p) }}">{{ $p }}</a> @endif
          @endforeach
          @if ($assets->hasMorePages()) <a href="{{ $assets->nextPageUrl() }}">›</a>
          @else <span class="off">›</span> @endif
        </nav>
      @endif
    </div>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('asset-filter');
  const search = document.getElementById('asset-search');
  const spin = document.getElementById('asset-spin');
  let timer = null;
  search.addEventListener('input', () => { clearTimeout(timer); spin.style.display = 'block'; timer = setTimeout(() => form.submit(), 400); });
  search.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); form.submit(); } });
  document.querySelectorAll('.auto-submit').forEach(s => s.addEventListener('change', () => form.submit()));
})();
</script>
@endsection