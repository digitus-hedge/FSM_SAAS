@extends('layouts.admin')
@section('title', 'Hold Reasons')

@section('content')
<style>
  .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}
  .page-head h1{font-family:'Figtree';font-size:26px;font-weight:700;letter-spacing:-.3px}
  .page-head p{color:var(--muted);font-size:13px;margin-top:3px}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:#F0B700}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
  .input{font-family:'DM Sans';font-size:13px;padding:10px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s}
  .input:focus{border-color:var(--yellow)}
  .input-grow{flex:1;min-width:200px}
  .input-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}

  .card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  table{width:100%;border-collapse:collapse;font-size:13px}
  thead th{text-align:left;padding:12px 16px;font-weight:600;font-size:11px;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;background:#FCFBF8;border-bottom:1px solid var(--line)}
  tbody td{padding:14px 16px;border-top:1px solid var(--line);vertical-align:middle}
  tbody tr:first-child td{border-top:0}
  tbody tr:hover{background:#FDFCF9}
  .cell-name{font-weight:600;color:var(--ink)}

  .pill{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px}
  .pill-ok{background:var(--green-soft);color:var(--green)}
  .pill-off{background:var(--soft);color:var(--muted)}
  .pill-amber{background:var(--yellow-soft);color:var(--amber)}
  .pill-dot{width:5px;height:5px;border-radius:50%;background:currentColor}

  .row-actions{display:flex;gap:4px;justify-content:flex-end;align-items:center}
  .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid transparent;background:transparent;cursor:pointer;color:var(--muted);transition:all .12s;text-decoration:none;padding:0;position:relative}
  .icon-btn:hover{background:var(--soft);border-color:var(--line);color:var(--ink)}
  .icon-btn svg{width:16px;height:16px}
  .icon-btn.edit:hover{background:#FFFCF2;border-color:var(--yellow);color:var(--amber)}
  .icon-btn.suspend:hover{background:#FEF3C7;border-color:#FBBF24;color:#92400E}
  .icon-btn.reactivate:hover{background:var(--green-soft);border-color:var(--green);color:var(--green)}
  .icon-btn.delete:hover{background:var(--red-soft);border-color:var(--red);color:var(--red)}

  .icon-btn::after{
    content:attr(data-tip);
    position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);
    background:var(--ink);color:#fff;font-size:10px;font-weight:600;padding:4px 8px;border-radius:6px;
    white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .12s;z-index:10;
  }
  .icon-btn:hover::after{opacity:1}

  .empty{padding:60px 20px;text-align:center;color:var(--muted);font-size:13px}
  .empty a{color:var(--amber);text-decoration:none;font-weight:600;border-bottom:1px solid var(--yellow)}

  .pager{display:flex;align-items:center;justify-content:space-between;margin-top:18px;flex-wrap:wrap;gap:12px}
  .pager-info{font-size:12px;color:var(--muted)}
  .pager-info strong{color:var(--ink);font-weight:600}
  .pager-right{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .per-page{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted)}
  .per-page select{font-family:'DM Sans';font-size:12px;padding:6px 24px 6px 10px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='5' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
  .pagination-nav{display:flex;align-items:center;gap:4px}
  .pagination-nav a,.pagination-nav span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 10px;font-size:12px;font-weight:600;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:8px;text-decoration:none;transition:all .12s}
  .pagination-nav a:hover{background:var(--soft)}
  .pagination-nav .is-active{background:var(--yellow);border-color:var(--yellow);cursor:default}
  .pagination-nav .is-disabled{color:var(--subtle);background:#FCFBF8;pointer-events:none}
  .pagination-nav .ellipsis{border:0;background:transparent;color:var(--subtle);min-width:20px}
</style>

<div class="page-head">
  <div>
    <h1>Hold Reasons</h1>
    <p>Why work on a service request stops — e.g. "Waiting for parts", "Client unavailable". Controls whether the SLA clock pauses.</p>
  </div>
  <a href="{{ route('admin.hold-reasons.create') }}" class="btn btn-primary">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
    New reason
  </a>
</div>

@php
  $hasFilter = request()->filled('q') || request()->filled('status');
@endphp

<form method="GET" class="toolbar" id="hr-filter">
  <div style="position:relative;flex:1;min-width:200px">
    <input type="text" name="q" value="{{ request('q') }}"
           placeholder="Search by label…"
           class="input" style="width:100%;padding-right:36px"
           autocomplete="off" id="hr-search">
    <svg id="hr-search-ico"
         style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--subtle);pointer-events:none"
         width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <span id="hr-search-spin"
          style="position:absolute;right:14px;top:50%;transform:translateY(-50%);width:12px;height:12px;border:2px solid var(--line);border-top-color:var(--amber);border-radius:50%;display:none;animation:hrspin .8s linear infinite"></span>
  </div>

  <select name="status" class="input input-select auto-submit" style="width:170px">
    <option value="">All status</option>
    <option value="active"   @selected(request('status')==='active')>Active only</option>
    <option value="inactive" @selected(request('status')==='inactive')>Inactive only</option>
  </select>

  <input type="hidden" name="per_page" value="{{ $perPage }}" />

  @if ($hasFilter)
    <a href="{{ route('admin.hold-reasons.index') }}"
       style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--muted);text-decoration:none;padding:9px 14px;border-radius:999px;border:1px dashed var(--line);transition:all .12s"
       onmouseover="this.style.borderColor='var(--red)';this.style.color='var(--red)';this.style.background='var(--red-soft)'"
       onmouseout="this.style.borderColor='var(--line)';this.style.color='var(--muted)';this.style.background='transparent'">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      Clear
    </a>
  @endif
</form>


<div class="card">
  <table>
    <thead>
      <tr>
        <th>Label</th>
        <th>Pauses SLA</th>
        <th>Requires Note</th>
        <th>Status</th>
        <th style="text-align:right;width:140px">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($reasons as $reason)
        <tr>
          <td class="cell-name">{{ $reason->label }}</td>
          <td>
            @if ($reason->pauses_sla)
              <span class="pill pill-amber"><span class="pill-dot"></span>Pauses</span>
            @else
              <span class="pill pill-off"><span class="pill-dot"></span>Continues</span>
            @endif
          </td>
          <td>
            @if ($reason->requires_note)
              <span class="pill pill-amber"><span class="pill-dot"></span>Required</span>
            @else
              <span class="pill pill-off"><span class="pill-dot"></span>Optional</span>
            @endif
          </td>
          <td>
            @if ($reason->is_active)
              <span class="pill pill-ok"><span class="pill-dot"></span>Active</span>
            @else
              <span class="pill pill-off"><span class="pill-dot"></span>Inactive</span>
            @endif
          </td>
          <td>
            <div class="row-actions">

              <a href="{{ route('admin.hold-reasons.edit', $reason) }}"
                 class="icon-btn edit" data-tip="Edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </a>

              @if ($reason->is_active)
                <form method="POST" action="{{ route('admin.hold-reasons.suspend', $reason) }}" style="margin:0"
                      class="needs-confirm"
                      data-confirm-title="Deactivate {{ $reason->label }}?"
                      data-confirm-text="This reason will stop appearing in the hold-reason picker on service requests."
                      data-confirm-ok="Yes, deactivate"
                      data-confirm-ok-color="amber">
                  @csrf
                  <button type="submit" class="icon-btn suspend" data-tip="Deactivate">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
                    </svg>
                  </button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.hold-reasons.reactivate', $reason) }}" style="margin:0"
                      class="needs-confirm"
                      data-confirm-title="Reactivate {{ $reason->label }}?"
                      data-confirm-text="This reason will become available again."
                      data-confirm-ok="Yes, reactivate"
                      data-confirm-ok-color="green">
                  @csrf
                  <button type="submit" class="icon-btn reactivate" data-tip="Reactivate">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                  </button>
                </form>
              @endif

              <form method="POST" action="{{ route('admin.hold-reasons.destroy', $reason) }}" style="margin:0"
                    class="needs-confirm"
                    data-confirm-title="Delete {{ $reason->label }}?"
                    data-confirm-text="This reason will be removed. Past hold records keep their reference, but no new holds can use it. You can restore it from the archive later."
                    data-confirm-ok="Yes, delete"
                    data-confirm-ok-color="red">
                @csrf @method('DELETE')
                <button type="submit" class="icon-btn delete" data-tip="Delete">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    <path d="M10 11v6M14 11v6"/>
                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                  </svg>
                </button>
              </form>

            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="empty">
            No hold reasons yet. <a href="{{ route('admin.hold-reasons.create') }}">Add your first reason</a>.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager">
  <div class="pager-info">
    @if ($reasons->total() > 0)
      Showing <strong>{{ $reasons->firstItem() }}</strong>–<strong>{{ $reasons->lastItem() }}</strong>
      of <strong>{{ number_format($reasons->total()) }}</strong> {{ Str::plural('reason', $reasons->total()) }}
    @else
      No results
    @endif
  </div>
  <div class="pager-right">
    <label class="per-page">
      Rows:
      <select onchange="window.location = this.value">
        @foreach ($perPageOptions as $opt)
          <option value="{{ $reasons->url(1).'&per_page='.$opt }}" @selected($perPage === $opt)>{{ $opt }}</option>
        @endforeach
      </select>
    </label>

    @if ($reasons->hasPages())
      <nav class="pagination-nav">
        @if ($reasons->onFirstPage())
          <span class="is-disabled">‹</span>
        @else
          <a href="{{ $reasons->previousPageUrl() }}">‹</a>
        @endif

        @php
          $current = $reasons->currentPage();
          $last    = $reasons->lastPage();
          $show    = collect(range(1, $last))
            ->filter(fn ($p) => $p === 1 || $p === $last || abs($p - $current) <= 1)
            ->values();
        @endphp
        @foreach ($show as $i => $page)
          @if ($i > 0 && $page - $show[$i-1] > 1) <span class="ellipsis">…</span> @endif
          @if ($page === $current)
            <span class="is-active">{{ $page }}</span>
          @else
            <a href="{{ $reasons->url($page) }}">{{ $page }}</a>
          @endif
        @endforeach

        @if ($reasons->hasMorePages())
          <a href="{{ $reasons->nextPageUrl() }}">›</a>
        @else
          <span class="is-disabled">›</span>
        @endif
      </nav>
    @endif
  </div>
</div>


<style>
  @keyframes hrspin { to { transform: translateY(-50%) rotate(360deg); } }
</style>


<script>
(function () {
  const form   = document.getElementById('hr-filter');
  const search = document.getElementById('hr-search');
  const ico    = document.getElementById('hr-search-ico');
  const spin   = document.getElementById('hr-search-spin');
  let timer = null;

  // Debounced search (400ms after last keystroke)
  search.addEventListener('input', function () {
    clearTimeout(timer);
    ico.style.display = 'none';
    spin.style.display = 'block';
    timer = setTimeout(() => form.submit(), 400);
  });

  // Enter submits immediately
  search.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(timer);
      form.submit();
    }
  });

  // Auto-submit when the status dropdown changes
  document.querySelectorAll('.auto-submit').forEach(sel => {
    sel.addEventListener('change', () => form.submit());
  });
})();
</script>


@endsection