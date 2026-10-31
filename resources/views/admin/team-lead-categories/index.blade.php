@extends('layouts.admin')
@section('title', 'Team Lead categories')

@section('content')
<style>
  .crumb{color:var(--muted);font-size:13px;margin-bottom:4px}
  .crumb a{color:var(--ink);text-decoration:underline}
  .page-h1{font-family:var(--font-display);font-size:26px;font-weight:800;margin-bottom:6px}
  .sub-txt{color:var(--muted);font-size:13.5px;line-height:1.5;margin-bottom:24px;max-width:560px}

  .tlc-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:14px}
  .tlc-head{display:flex;align-items:center;gap:12px;margin-bottom:16px}
  .tlc-avatar{width:38px;height:38px;border-radius:50%;background:var(--yellow-soft);color:var(--amber);font-weight:700;font-size:13.5px;font-family:var(--font-display);display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .tlc-name{font-family:var(--font-display);font-weight:800;font-size:15.5px;line-height:1.2}
  .tlc-sub{color:var(--muted);font-size:12.5px;margin-top:1px}
  .tlc-count{margin-left:auto;font-size:12px;color:var(--muted);white-space:nowrap}

  .tlc-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
  .tlc-chip{position:relative}
  .tlc-chip input{position:absolute;opacity:0;inset:0;margin:0;cursor:pointer}
  .tlc-chip span{display:inline-flex;align-items:center;padding:7px 14px;border-radius:999px;border:1px solid var(--line);background:var(--soft);color:var(--ink);font-size:13px;font-weight:500;transition:background .12s,border-color .12s,color .12s}
  .tlc-chip input:checked + span{background:var(--yellow-soft);border-color:#F0D98C;color:var(--amber);font-weight:700}
  .tlc-chip input:focus-visible + span{outline:2px solid var(--amber);outline-offset:1px}

  .btn-primary{display:inline-block;padding:9px 18px;border-radius:12px;background:var(--amber);color:#fff;font-weight:700;font-size:13px;border:0;cursor:pointer;font-family:var(--font-body)}
  .btn-primary:hover{background:color-mix(in srgb, var(--amber) 85%, black 15%)}

  .tlc-empty{background:#fff;border:1px dashed var(--line);border-radius:16px;padding:36px 24px;text-align:center;color:var(--muted);font-size:13.5px}
</style>

<div class="crumb"><a href="{{ route('admin.dashboard') }}">Master data</a> / Team Lead categories</div>
<h1 class="page-h1">Team Lead categories</h1>
<p class="sub-txt">Pick which service categories each Team Lead covers. A new request in that category is routed straight to them for approval.</p>

@forelse ($teamLeads as $teamLead)
  @php $checkedCount = $teamLead->coveredCategories->count(); @endphp
  <div class="tlc-card">
    <div class="tlc-head">
      <span class="tlc-avatar">{{ strtoupper(substr($teamLead->name, 0, 2)) }}</span>
      <div>
        <div class="tlc-name">{{ $teamLead->name }}</div>
        <div class="tlc-sub">Team Lead</div>
      </div>
      <span class="tlc-count">{{ $checkedCount }} of {{ $categories->count() }} covered</span>
    </div>

    <form method="POST" action="{{ route('admin.team-lead-categories.update', $teamLead) }}">
      @csrf
      <div class="tlc-chips">
        @foreach ($categories as $cat)
          <label class="tlc-chip">
            <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}"
              {{ $teamLead->coveredCategories->contains('id', $cat->id) ? 'checked' : '' }}>
            <span>{{ $cat->name }}</span>
          </label>
        @endforeach
      </div>
      <button type="submit" class="btn-primary">Save</button>
    </form>
  </div>
@empty
  <div class="tlc-empty">No Team Leads found for this company yet. Add one under People &amp; access → Users.</div>
@endforelse
@endsection