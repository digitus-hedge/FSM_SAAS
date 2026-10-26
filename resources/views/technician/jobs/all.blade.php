@extends('layouts.technician')
@section('title', 'All my jobs')

@section('content')
<form method="GET" style="margin-bottom:14px">
  <input type="text" name="q" value="{{ $search }}" placeholder="Search by number, title, client or site"
         style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid var(--line);font-family:'DM Sans';font-size:14px;outline:none"
         onchange="this.form.submit()">
  <input type="hidden" name="tab" value="{{ $tab }}">
</form>

<div style="display:flex;background:var(--soft);border-radius:12px;padding:4px;margin-bottom:14px">
  @foreach ([
    'active' => 'Active ('.$counts['active'].')',
    'review' => 'Review ('.$counts['review'].')',
    'done'   => 'Done',
  ] as $k => $label)
    <a href="?tab={{ $k }}{{ $search ? '&q='.urlencode($search) : '' }}"
       style="flex:1;padding:11px;text-align:center;border-radius:9px;font-size:14px;font-weight:800;color:{{ $tab===$k ? 'var(--ink)' : 'var(--muted)' }};background:{{ $tab===$k ? '#fff' : 'transparent' }};box-shadow:{{ $tab===$k ? '0 1px 3px rgba(0,0,0,.06)' : 'none' }};text-decoration:none">{{ $label }}</a>
  @endforeach
</div>

@if ($tab === 'done')<div style="color:var(--muted);font-size:13px;margin-bottom:10px">Jobs completed or cancelled in the last 14 days.</div>@endif

@forelse ($jobs as $job)
  @include('technician.jobs._card', ['job' => $job])
@empty
  <div class="card" style="text-align:center;padding:40px;color:var(--muted)">No jobs here yet.</div>
@endforelse
@endsection