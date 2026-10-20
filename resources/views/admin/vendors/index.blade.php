@extends('layouts.admin')
@section('title', 'Vendors')

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

  .vendor-cell{display:flex;align-items:center;gap:12px}
  .avatar{
    display:inline-flex;align-items:center;justify-content:center;
    width:36px;height:36px;border-radius:10px;background:var(--yellow-soft);color:var(--amber);
    font-family:'Figtree';font-size:14px;font-weight:700;flex-shrink:0;letter-spacing:-.02em;
  }
  .cell-name{font-weight:600;color:var(--ink);line-height:1.3}
  .cell-sub{font-size:11px;color:var(--muted);margin-top:1px}

  .contact-line{font-size:12px;color:var(--ink);line-height:1.5}
  .contact-line .sub{color:var(--muted);font-size:11px}
  .mono{font-family:'DM Sans';font-size:11px;color:var(--muted);letter-spacing:.03em}

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
    <h1>Vendors</h1>
    <p>Subcontractors and suppliers you work with — OEMs, part suppliers, specialized service providers.</p>
  </div>
  <a href="{{ route('admin.vendors.create') }}" class="btn btn-primary">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
    New vendor
  </a>
</div>

<form method="GET" class="toolbar">
  <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name, contact, email, phone or tax ID…" class="input input-grow" />

  <select name="status" class="input input-select" style="width:170px">
    <option value="">All status</option>
    <option value="active"    @selected(request('status')==='active')>Active only</option>
    <option value="suspended" @selected(request('status')==='suspended')>Suspended only</option>
  </select>

  <input type="hidden" name="per_page" value="{{ $perPage }}" />
  <button class="btn btn-ghost">Filter</button>
</form>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>Vendor</th>
        <th>Contact</th>
        <th>Tax ID</th>
        <th>Status</th>
        <th style="text-align:right;width:140px">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($vendors as $v)
        @php
          $initials = collect(preg_split('/\s+/', trim($v->name)))
            ->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
        @endphp
        <tr>
          <td>
            <div class="vendor-cell">
              <span class="avatar">{{ $initials ?: '•' }}</span>
              <div>
                <div class="cell-name">{{ $v->name }}</div>
                @if ($v->contact_name)
                  <div class="cell-sub">{{ $v->contact_name }}</div>
                @endif
              </div>
            </div>
          </td>
          <td>
            @if ($v->email || $v->phone)
              <div class="contact-line">
                @if ($v->email)<div>{{ $v->email }}</div>@endif
                @if ($v->phone)<div class="sub">{{ $v->phone }}</div>@endif
              </div>
            @else
              <span class="cell-sub">—</span>
            @endif
          </td>
          <td>
            @if ($v->tax_id)
              <span class="mono">{{ $v->tax_id }}</span>
            @else
              <span class="cell-sub">—</span>
            @endif
          </td>
          <td>
            @if ($v->status === 'active')
              <span class="pill pill-ok"><span class="pill-dot"></span>Active</span>
            @else
              <span class="pill pill-amber"><span class="pill-dot"></span>Suspended</span>
            @endif
          </td>
          <td>
            <div class="row-actions">

              <a href="{{ route('admin.vendors.edit', $v) }}"
                 class="icon-btn edit" data-tip="Edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </a>

              @if ($v->status === 'active')
                <form method="POST" action="{{ route('admin.vendors.suspend', $v) }}" style="margin:0"
                      class="needs-confirm"
                      data-confirm-title="Suspend {{ $v->name }}?"
                      data-confirm-text="This vendor will stop appearing in the vendor picker on new service requests and POs."
                      data-confirm-ok="Yes, suspend"
                      data-confirm-ok-color="amber">
                  @csrf
                  <button type="submit" class="icon-btn suspend" data-tip="Suspend">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>
                    </svg>
                  </button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.vendors.reactivate', $v) }}" style="margin:0"
                      class="needs-confirm"
                      data-confirm-title="Reactivate {{ $v->name }}?"
                      data-confirm-text="This vendor will become available again."
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

              <form method="POST" action="{{ route('admin.vendors.destroy', $v) }}" style="margin:0"
                    class="needs-confirm"
                    data-confirm-title="Delete {{ $v->name }}?"
                    data-confirm-text="This vendor will be removed. Existing service requests and POs keep their reference, but no new ones can use it. You can restore it from the archive later."
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
            No vendors yet. <a href="{{ route('admin.vendors.create') }}">Add your first vendor</a>.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager">
  <div class="pager-info">
    @if ($vendors->total() > 0)
      Showing <strong>{{ $vendors->firstItem() }}</strong>–<strong>{{ $vendors->lastItem() }}</strong>
      of <strong>{{ number_format($vendors->total()) }}</strong> {{ Str::plural('vendor', $vendors->total()) }}
    @else
      No results
    @endif
  </div>
  <div class="pager-right">
    <label class="per-page">
      Rows:
      <select onchange="window.location = this.value">
        @foreach ($perPageOptions as $opt)
          <option value="{{ $vendors->url(1).'&per_page='.$opt }}" @selected($perPage === $opt)>{{ $opt }}</option>
        @endforeach
      </select>
    </label>

    @if ($vendors->hasPages())
      <nav class="pagination-nav">
        @if ($vendors->onFirstPage())
          <span class="is-disabled">‹</span>
        @else
          <a href="{{ $vendors->previousPageUrl() }}">‹</a>
        @endif

        @php
          $current = $vendors->currentPage();
          $last    = $vendors->lastPage();
          $show    = collect(range(1, $last))
            ->filter(fn ($p) => $p === 1 || $p === $last || abs($p - $current) <= 1)
            ->values();
        @endphp
        @foreach ($show as $i => $page)
          @if ($i > 0 && $page - $show[$i-1] > 1) <span class="ellipsis">…</span> @endif
          @if ($page === $current)
            <span class="is-active">{{ $page }}</span>
          @else
            <a href="{{ $vendors->url($page) }}">{{ $page }}</a>
          @endif
        @endforeach

        @if ($vendors->hasMorePages())
          <a href="{{ $vendors->nextPageUrl() }}">›</a>
        @else
          <span class="is-disabled">›</span>
        @endif
      </nav>
    @endif
  </div>
</div>
@endsection