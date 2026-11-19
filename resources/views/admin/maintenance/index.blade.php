@extends('layouts.admin')
@section('title', 'Maintenance')

@section('content')
@include('admin.partials._page')

<div class="page-head">
  <div>
    <h1>Maintenance schedules</h1>
    <p>Routine jobs the system creates by itself, at 06:00 company time.</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="{{ route('admin.maintenance.calendar') }}" class="btn btn-ghost">Calendar</a>
    @if ($canManage)<a href="{{ route('admin.maintenance.create') }}" class="btn btn-primary">New schedule</a>@endif
  </div>
</div>

<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <select name="site" class="sel"><option value="">All sites</option>@foreach ($sites as $s)<option value="{{ $s->id }}" @selected(request('site') == $s->id)>{{ $s->name }}</option>@endforeach</select>
  <select name="category" class="sel"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select>
  <select name="technician" class="sel"><option value="">Any technician</option>@foreach ($technicians as $t)<option value="{{ $t->id }}" @selected(request('technician') == $t->id)>{{ $t->name }}</option>@endforeach</select>
  <select name="status" class="sel"><option value="">Any status</option>@foreach (['active', 'paused', 'ended'] as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>@endforeach</select>
  <button class="btn btn-ghost">Filter</button>
</form>

<div class="card">
  <table>
    <thead><tr><th>Name</th><th>Site</th><th>Assets</th><th>Category</th><th>Frequency</th><th>Next due</th><th>Default technician</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($schedules as $s)
      <tr>
        <td><a href="{{ route('admin.maintenance.show', $s) }}" style="font-weight:600;color:var(--ink)">{{ $s->name }}</a></td>
        <td>{{ $s->site?->name }}</td>
        <td>{{ $s->asset_rows_count }}</td>
        <td>{{ $s->category?->name }}</td>
        <td>{{ $s->frequency_label }}</td>
        <td>{{ $s->next_due_date?->format('j M Y') }}</td>
        <td>{{ $s->technician?->name ?? '—' }}</td>
        <td><span class="pill {{ $s->status === 'active' ? 'pill-ok' : ($s->status === 'paused' ? 'pill-amber' : '') }}">{{ ucfirst($s->status) }}</span></td>
      </tr>
    @empty
      <tr><td colspan="8" style="text-align:center;padding:50px;color:var(--muted)">No schedules yet.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
<div style="margin-top:14px">{{ $schedules->links() }}</div>
@endsection
