@extends('layouts.admin')
@section('title', 'Service Requests')

@section('content')
<style>
  .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}
  .page-head h1{font-family:var(--font-display);font-size:26px;font-weight:700;letter-spacing:-.3px}
  .page-head p{color:var(--muted);font-size:13px;margin-top:3px}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-body);font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:color-mix(in srgb, var(--yellow) 85%, black 15%)}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
  .input{font-family:var(--font-body);font-size:13px;padding:10px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s}
  .input:focus{border-color:var(--yellow)}
  .input-grow{flex:1;min-width:220px}
  .input-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}

  .card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  table{width:100%;border-collapse:collapse;font-size:13px}
  thead th{text-align:left;padding:12px 16px;font-weight:600;font-size:11px;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;background:#FCFBF8;border-bottom:1px solid var(--line)}
  tbody td{padding:14px 16px;border-top:1px solid var(--line);vertical-align:middle}
  tbody tr:first-child td{border-top:0}
  tbody tr:hover{background:#FDFCF9}

  .sr-cell{display:flex;flex-direction:column;gap:2px;min-width:0}
  .sr-num{font-family:var(--font-body);font-size:11px;font-weight:600;color:var(--amber);letter-spacing:.04em}
  .sr-title{font-weight:600;color:var(--ink);line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:380px}
  .cell-sub{color:var(--muted);font-size:12px;line-height:1.4}
  .cell-sub small{color:var(--subtle);font-size:10px;text-transform:uppercase;letter-spacing:.06em;display:block;margin-top:1px}

  .pill{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px}
  .pill-dot{width:5px;height:5px;border-radius:50%;background:currentColor}
  .pill-gray{background:var(--soft);color:var(--muted)}
  .pill-amber{background:var(--yellow-soft);color:var(--amber)}
  .pill-orange{background:#FFEDD5;color:#C2410C}
  .pill-red{background:var(--red-soft);color:var(--red)}
  .pill-green{background:var(--green-soft);color:var(--green)}
  .pill-blue{background:#DBEAFE;color:#1E40AF}

  .row-actions{display:flex;gap:4px;justify-content:flex-end;align-items:center}
  .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid transparent;background:transparent;cursor:pointer;color:var(--muted);transition:all .12s;text-decoration:none;padding:0;position:relative}
  .icon-btn:hover{background:var(--soft);border-color:var(--line);color:var(--ink)}
  .icon-btn svg{width:16px;height:16px}

  .icon-btn.view:hover{background:#EEF2FF;border-color:#6366F1;color:#4338CA}
  .icon-btn.edit:hover{background:#FFFCF2;border-color:var(--yellow);color:var(--amber)}
  .icon-btn.delete:hover{background:var(--red-soft);border-color:var(--red);color:var(--red)}
  .icon-btn::after{content:attr(data-tip);position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:var(--ink);color:#fff;font-size:10px;font-weight:600;padding:4px 8px;border-radius:6px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .12s;z-index:10}
  .icon-btn:hover::after{opacity:1}

  .empty{padding:60px 20px;text-align:center;color:var(--muted);font-size:13px}
  .empty a{color:var(--amber);text-decoration:none;font-weight:600;border-bottom:1px solid var(--yellow)}

  .pager{display:flex;align-items:center;justify-content:space-between;margin-top:18px;flex-wrap:wrap;gap:12px}
  .pager-info{font-size:12px;color:var(--muted)}
  .pager-info strong{color:var(--ink);font-weight:600}
  .pager-right{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .per-page{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted)}
  .per-page select{font-family:var(--font-body);font-size:12px;padding:6px 24px 6px 10px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='5' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center}
  .pagination-nav{display:flex;align-items:center;gap:4px}
  .pagination-nav a,.pagination-nav span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 10px;font-size:12px;font-weight:600;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:8px;text-decoration:none;transition:all .12s}
  .pagination-nav a:hover{background:var(--soft)}
  .pagination-nav .is-active{background:var(--yellow);border-color:var(--yellow);cursor:default}
  .pagination-nav .is-disabled{color:var(--subtle);background:#FCFBF8;pointer-events:none}
</style>

@php
  // Map SR status → pill color
  $statusPill = [
    'pending'          => 'gray',
    'waiting_for_quote'=> 'blue',
    'quoted'           => 'blue',
    'approved'         => 'green',
    'assigned'         => 'blue',
    'in_progress'      => 'amber',
    'pending_review'   => 'orange',
    'awaiting_invoice' => 'orange',
    'rework'           => 'red',
    'completed'        => 'green',
    'cancelled'        => 'gray',
  ];
@endphp

<div class="page-head">
  <div>
    <h1>Service Requests</h1>
    <p>All service requests from clients — the core workflow for your field team.</p>
  </div>
  <a href="{{ route('service-requests.create') }}" class="btn btn-primary">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
    New Requests
  </a>
</div>

@php
    $stageTabs = [
        'needs'     => ['label' => 'Needs approval',   'status' => 'pending'],
        'quote'     => ['label' => 'Waiting for quote','status' => 'waiting_for_quote'],
        'quoted'    => ['label' => 'Quoted',           'status' => 'quoted'],
        'assign'    => ['label' => 'To assign',        'status' => 'approved'],
        'assigned'  => ['label' => 'Assigned',         'status' => 'assigned'],
        'progress'  => ['label' => 'In progress',      'status' => 'in_progress'],
        'review'    => ['label' => 'To review',        'status' => 'pending_review'],
        'invoice'   => ['label' => 'Awaiting invoice', 'status' => 'awaiting_invoice'],
        'rework'    => ['label' => 'Rework',           'status' => 'rework'],
        'done'      => ['label' => 'Completed',        'status' => 'completed'],
        'cancelled' => ['label' => 'Cancelled',        'status' => 'cancelled'],
        'all'       => ['label' => 'All',              'status' => ''],
    ];

    $activeStage = request('stage', 'all');
    $activeStage = array_key_exists($activeStage, $stageTabs)
        ? $activeStage
        : 'all';
@endphp

<style>
    .stage-tabs {
        display: flex;
        gap: 9px;
        overflow-x: auto;
        padding: 0 0 12px;
        margin-bottom: 10px;
        scrollbar-width: thin;
    }

    .stage-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
        white-space: nowrap;
        padding: 10px 16px;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: #fff;
        color: var(--ink);
        text-decoration: none;
        font-family: var(--font-body);
        font-size: 13px;
        font-weight: 600;
        transition: all .15s;
    }

    .stage-tab:hover {
        background: var(--soft);
    }

    .stage-tab.active {
        background: var(--amber);
        color: #fff;
        border-color: var(--amber);
    }

    .stage-count {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        min-width: 21px;
        height: 21px;
        padding: 0 6px;
        border-radius: 999px;
        background: var(--soft);
        color: var(--ink);
        font-size: 11px;
    }

    .stage-tab.active .stage-count {
        background: rgba(255,255,255,.22);
        color: #fff;
    }
</style>

<div class="stage-tabs" role="group" aria-label="Request stages">
    @foreach ($stageTabs as $key => $tab)
        <a
            href="{{ route('service-requests.index', array_merge(request()->except('page', 'stage'), ['stage' => $key])) }}"
            class="stage-tab {{ $activeStage === $key ? 'active' : '' }}"
            aria-pressed="{{ $activeStage === $key ? 'true' : 'false' }}"
        >
            {{ $tab['label'] }}

            <span class="stage-count">
                {{ $stageCounts[$key] ?? 0 }}
            </span>
        </a>
    @endforeach
</div>

@php
  $hasFilter = request()->filled('q')
            || request()->filled('status')
            || request()->filled('priority')
            || request()->filled('client_id');
@endphp

<form method="GET" class="toolbar" id="sr-filter">
  <div class="input-grow" style="position:relative;flex:1;min-width:220px">
    <input type="text" name="q" value="{{ request('q') }}"
           placeholder="Search by SR #, title or PO ref…"
           class="input" style="width:100%;padding-right:36px"
           autocomplete="off" id="sr-search">
    <svg style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--subtle);pointer-events:none"
         width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" id="sr-search-ico">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <span id="sr-search-spin" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);width:12px;height:12px;border:2px solid var(--line);border-top-color:var(--amber);border-radius:50%;display:none;animation:srspin .8s linear infinite"></span>
  </div>

  <select name="status" class="input input-select auto-submit" style="width:180px">
    <option value="">All statuses</option>
    @foreach ($statuses as $key => $label)
      <option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>
    @endforeach
  </select>

  <select name="priority" class="input input-select auto-submit" style="width:160px">
    <option value="">All priorities</option>
    @foreach ($priorities as $key => $label)
      <option value="{{ $key }}" @selected(request('priority')===$key)>{{ $label }}</option>
    @endforeach
  </select>

  <select name="client_id" class="input input-select auto-submit" style="width:200px">
    <option value="">All clients</option>
    @foreach ($clients as $c)
      <option value="{{ $c->id }}" @selected((int) request('client_id') === $c->id)>{{ $c->name }}</option>
    @endforeach
  </select>

  <input type="hidden" name="per_page" value="{{ $perPage }}" />

  @if ($hasFilter)
    <a href="{{ route('service-requests.index') }}"
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
        <th>Request</th>
        <th>Client / Site</th>
        <th>Category</th>
        <th>Priority</th>
        <th>Status</th>
        <th>Created</th>
        <th style="text-align:right;width:100px">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($requests as $sr)
        <tr>
          <td>
            <a href="{{ route('service-requests.show', $sr) }}" style="text-decoration:none;color:inherit;display:block">
              <div class="sr-cell">
                <div class="sr-num">{{ $sr->sr_number }}</div>
                <div class="sr-title" title="{{ $sr->title }}">{{ $sr->title }}</div>
              </div>
            </a>
          </td>

          <td>
            <a href="{{ route('service-requests.show', $sr) }}" style="text-decoration:none;color:inherit;display:block">
              <div class="cell-sub">
                {{ $sr->client->name ?? '—' }}
                <small>{{ $sr->site->name ?? '—' }}</small>
              </div>
            </a>
          </td>

          <td class="cell-sub">
            <a href="{{ route('service-requests.show', $sr) }}" style="text-decoration:none;color:inherit;display:block">
              {{ $sr->category->name ?? '—' }}
            </a>
          </td>
          <td>
            <a href="{{ route('service-requests.show', $sr) }}" style="text-decoration:none;color:inherit;display:block">
              <span class="pill pill-{{ $sr->priorityColor() }}">
                <span class="pill-dot"></span>{{ $sr->priorityLabel() }}
              </span>
            </a>
          </td>

          <td>
            <a href="{{ route('service-requests.show', $sr) }}" style="text-decoration:none;color:inherit;display:block">
              <span class="pill pill-{{ $statusPill[$sr->status] ?? 'gray' }}">
                <span class="pill-dot"></span>{{ $sr->statusLabel() }}
              </span>
            </a>
          </td>
          <td class="cell-sub">
            {{ $sr->created_at->format('d M Y') }}
            <small>{{ $sr->created_at->format('H:i') }}</small>
          </td>
          <td>
            <div class="row-actions">
              <a href="{{ route('service-requests.show', $sr) }}" class="icon-btn view" data-tip="View">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </a>

              <a href="{{ route('service-requests.edit', $sr) }}" class="icon-btn edit" data-tip="Edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </a>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="empty">
          No requests yet. <a href="{{ route('service-requests.create') }}">Log your first request</a>.
        </td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager">
  <div class="pager-info">
    @if ($requests->total() > 0)
      Showing <strong>{{ $requests->firstItem() }}</strong>–<strong>{{ $requests->lastItem() }}</strong>
      of <strong>{{ number_format($requests->total()) }}</strong> {{ Str::plural('request', $requests->total()) }}
    @else
      No results
    @endif
  </div>
  <div class="pager-right">
    <label class="per-page">Rows:
      <select onchange="window.location = this.value">
        @foreach ($perPageOptions as $opt)
          <option value="{{ $requests->url(1).'&per_page='.$opt }}" @selected($perPage === $opt)>{{ $opt }}</option>
        @endforeach
      </select>
    </label>
    @if ($requests->hasPages())
      <nav class="pagination-nav">
        @if ($requests->onFirstPage())
          <span class="is-disabled">‹</span>
        @else
          <a href="{{ $requests->previousPageUrl() }}">‹</a>
        @endif
        @foreach (range(1, $requests->lastPage()) as $page)
          @if ($page === $requests->currentPage())
            <span class="is-active">{{ $page }}</span>
          @else
            <a href="{{ $requests->url($page) }}">{{ $page }}</a>
          @endif
        @endforeach
        @if ($requests->hasMorePages())
          <a href="{{ $requests->nextPageUrl() }}">›</a>
        @else
          <span class="is-disabled">›</span>
        @endif
      </nav>
    @endif
  </div>
</div>

<style>
  @keyframes srspin { to { transform: translateY(-50%) rotate(360deg); } }
</style>

<script>
(function () {
  const form   = document.getElementById('sr-filter');
  const search = document.getElementById('sr-search');
  const ico    = document.getElementById('sr-search-ico');
  const spin   = document.getElementById('sr-search-spin');
  let timer = null;

  // Debounced search submit
  search.addEventListener('input', function () {
    clearTimeout(timer);
    ico.style.display = 'none';
    spin.style.display = 'block';
    timer = setTimeout(() => form.submit(), 400);
  });

  // Submit on Enter without waiting for the debounce
  search.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(timer);
      form.submit();
    }
  });

  // Auto-submit when any dropdown changes
  document.querySelectorAll('.auto-submit').forEach(sel => {
    sel.addEventListener('change', () => form.submit());
  });
})();
</script>
@endsection