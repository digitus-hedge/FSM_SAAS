@extends('layouts.admin')
@section('title', 'SLA Policies')

@section('content')
<style>
  .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}
  .page-head h1{font-family:'Figtree';font-size:26px;font-weight:700;letter-spacing:-.3px}
  .page-head p{color:var(--muted);font-size:13px;margin-top:3px;}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:#F0B700}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .toolbar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap}
  .input{font-family:'DM Sans';font-size:13px;padding:10px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none}
  .input:focus{border-color:var(--yellow)}
  .input-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}

  .card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  table{width:100%;border-collapse:collapse;font-size:13px}
  thead th{text-align:left;padding:12px 16px;font-weight:600;font-size:11px;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;background:#FCFBF8;border-bottom:1px solid var(--line)}
  tbody td{padding:14px 16px;border-top:1px solid var(--line);vertical-align:middle}
  tbody tr:first-child td{border-top:0}
  tbody tr:hover{background:#FDFCF9}

  .pill{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px}
  .pill-dot{width:5px;height:5px;border-radius:50%;background:currentColor}
  .prio-low      {background:#E8F0FE;color:#1E40AF}
  .prio-medium   {background:var(--soft);color:var(--ink)}
  .prio-high     {background:#FEF3C7;color:#92400E}
  .prio-critical {background:#FFE4E6;color:#9F1239}
  .prio-emergency{background:#FEE2E2;color:#991B1B}
  .pill-default  {background:var(--yellow-soft);color:var(--amber)}
  .pill-biz      {background:var(--green-soft);color:var(--green);font-size:10px;padding:2px 7px}

  .cell-cat{font-weight:600;color:var(--ink)}
  .cell-cat .code{font-size:11px;color:var(--muted);font-weight:400;margin-left:4px}
  .cell-sub{color:var(--muted);font-size:12px}
  .cell-dur strong{font-size:14px;color:var(--ink)}
  .cell-dur .mins{color:var(--muted);font-size:11px;margin-left:4px}

  /* Icon action buttons */
  .row-actions{display:flex;gap:4px;justify-content:flex-end;align-items:center}
  .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid transparent;background:transparent;cursor:pointer;color:var(--muted);transition:all .12s;text-decoration:none;padding:0;position:relative}
  .icon-btn:hover{background:var(--soft);border-color:var(--line);color:var(--ink)}
  .icon-btn svg{width:16px;height:16px}
  .icon-btn.edit:hover{background:#FFFCF2;border-color:var(--yellow);color:var(--amber)}
  .icon-btn.delete:hover{background:var(--red-soft);border-color:var(--red);color:var(--red)}

  /* Tooltip */
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
  .pagination-nav a,.pagination-nav span{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 10px;font-size:12px;font-weight:600;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:8px;text-decoration:none}
  .pagination-nav a:hover{background:var(--soft)}
  .pagination-nav .is-active{background:var(--yellow);border-color:var(--yellow)}
  .pagination-nav .is-disabled{color:var(--subtle);background:#FCFBF8;pointer-events:none}
</style>

<div class="page-head">
  <div>
    <h1>SLA Policies</h1>
    <p>Response and resolution time commitments by priority. Set per category, or leave category empty for a tenant-wide default.</p>
  </div>
  <a href="{{ route('admin.sla-policies.create') }}" class="btn btn-primary">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
    New policy
  </a>
</div>


@php
  $hasFilter = request()->filled('category_id') || request()->filled('priority');
@endphp

<form method="GET" class="toolbar" id="sla-filter">
  <select name="category_id" class="input input-select auto-submit" style="width:260px">
    <option value="">All categories</option>
    <!-- <option value="default" @selected(request('category_id')==='default')>— Tenant default (no category) —</option> -->
    @foreach ($categories as $cat)
      <option value="{{ $cat->id }}" @selected((string) request('category_id') === (string) $cat->id)>
        {{ $cat->name }}
      </option>
    @endforeach
  </select>

  <select name="priority" class="input input-select auto-submit" style="width:170px">
    <option value="">All priorities</option>
    @foreach ($priorities as $value => $label)
      <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>
    @endforeach
  </select>

  <input type="hidden" name="per_page" value="{{ $perPage }}" />

  @if ($hasFilter)
    <a href="{{ route('admin.sla-policies.index') }}"
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
        <th>Category</th>
        <th>Priority</th>
        <th>Response</th>
        <th>Resolution</th>
        <th>Clock</th>
        <th style="text-align:right;width:100px">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($policies as $p)
        <tr>
          <td>
            @if ($p->category)
              <div class="cell-cat">{{ $p->category->name }}<span class="code">· {{ $p->category->code }}</span></div>
            @else
              <span class="pill pill-default"><span class="pill-dot"></span>Tenant default</span>
              <div class="cell-sub">Applies when no category policy matches.</div>
            @endif
          </td>
          <td>
            <span class="pill prio-{{ $p->priority }}">{{ $priorities[$p->priority] ?? $p->priority }}</span>
          </td>
          <td class="cell-dur">
            <strong>{{ $p->response_human }}</strong>
            <span class="mins">({{ number_format($p->response_minutes) }} min)</span>
          </td>
          <td class="cell-dur">
            <strong>{{ $p->resolution_human }}</strong>
            <span class="mins">({{ number_format($p->resolution_minutes) }} min)</span>
          </td>
          <td>
            @if ($p->uses_business_hours)
              <span class="pill pill-biz">Business hours only</span>
            @else
              <span class="cell-sub">24 × 7</span>
            @endif
          </td>
          <td>
            <div class="row-actions">

              {{-- EDIT --}}
              <a href="{{ route('admin.sla-policies.edit', $p) }}"
                 class="icon-btn edit" data-tip="Edit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </a>

              {{-- DELETE --}}
              <form method="POST" action="{{ route('admin.sla-policies.destroy', $p) }}" style="margin:0"
                    class="needs-confirm"
                    data-confirm-title="Delete this SLA policy?"
                    data-confirm-text="SRs created from now on will fall back to the next policy in the resolution order."
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
          <td colspan="6" class="empty">
            No SLA policies yet. <a href="{{ route('admin.sla-policies.create') }}">Add your first policy</a>.
            <br><span style="font-size:11px">Tip: start with 5 tenant-default rows (one per priority) — these catch SRs when no per-category policy exists.</span>
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager">
  <div class="pager-info">
    @if ($policies->total() > 0)
      Showing <strong>{{ $policies->firstItem() }}</strong>–<strong>{{ $policies->lastItem() }}</strong>
      of <strong>{{ number_format($policies->total()) }}</strong> {{ Str::plural('policy', $policies->total()) }}
    @else
      No results
    @endif
  </div>
  <div class="pager-right">
    <label class="per-page">
      Rows:
      <select onchange="window.location = this.value">
        @foreach ($perPageOptions as $opt)
          <option value="{{ $policies->url(1).'&per_page='.$opt }}" @selected($perPage === $opt)>{{ $opt }}</option>
        @endforeach
      </select>
    </label>

    @if ($policies->hasPages())
      <nav class="pagination-nav">
        @if ($policies->onFirstPage())
          <span class="is-disabled">‹</span>
        @else
          <a href="{{ $policies->previousPageUrl() }}">‹</a>
        @endif
        @foreach (range(1, $policies->lastPage()) as $page)
          @if ($page === $policies->currentPage())
            <span class="is-active">{{ $page }}</span>
          @else
            <a href="{{ $policies->url($page) }}">{{ $page }}</a>
          @endif
        @endforeach
        @if ($policies->hasMorePages())
          <a href="{{ $policies->nextPageUrl() }}">›</a>
        @else
          <span class="is-disabled">›</span>
        @endif
      </nav>
    @endif
  </div>
</div>


<script>
(function () {
  const form = document.getElementById('sla-filter');
  document.querySelectorAll('.auto-submit').forEach(sel => {
    sel.addEventListener('change', () => form.submit());
  });
})();
</script>


@endsection