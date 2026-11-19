@extends('layouts.admin')
@section('title', 'Maintenance calendar')

@section('content')
@include('admin.partials._page')
@php
    $weeks = [];
    $cursor = $from->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    while ($cursor->lte($to)) { $weeks[] = $cursor->copy(); $cursor->addWeek(); }
@endphp

<div class="page-head">
  <div><h1>Maintenance calendar</h1><p>Requests already made (filled) and due dates still to come (outlined).</p></div>
  <a href="{{ route('admin.maintenance.index') }}" class="btn btn-ghost">Schedules</a>
</div>

<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <input type="date" name="from" class="inp" value="{{ $from->toDateString() }}">
  <input type="date" name="to" class="inp" value="{{ $to->toDateString() }}">
  <select name="site" class="sel"><option value="">All sites</option>@foreach ($sites as $s)<option value="{{ $s->id }}" @selected(request('site') == $s->id)>{{ $s->name }}</option>@endforeach</select>
  <select name="category" class="sel"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select>
  <select name="technician" class="sel"><option value="">Any technician</option>@foreach ($technicians as $t)<option value="{{ $t->id }}" @selected(request('technician') == $t->id)>{{ $t->name }}</option>@endforeach</select>
  <button class="btn btn-ghost">Show</button>
</form>

<div class="card" style="overflow:auto">
  <table style="table-layout:fixed;min-width:860px">
    <thead><tr>@foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)<th>{{ $d }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach ($weeks as $w)
      <tr>
      @for ($i = 0; $i < 7; $i++)
        @php($day = $w->copy()->addDays($i))
        <td style="vertical-align:top;height:90px;{{ $day->lt($from) || $day->gt($to) ? 'background:#FCFBF8;color:var(--subtle)' : '' }}">
          <div class="hint">{{ $day->format('j M') }}</div>
          @foreach ($items[$day->toDateString()] ?? [] as $it)
            <a href="{{ $it['url'] }}" data-kind="{{ $it['kind'] }}" style="display:block;font-size:11px;margin-top:3px;padding:2px 6px;border-radius:6px;text-decoration:none;color:var(--ink);{{ $it['kind'] === 'request' ? 'background:var(--yellow-soft)' : 'border:1px dashed var(--line)' }}">{{ $it['label'] }}</a>
          @endforeach
        </td>
      @endfor
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endsection
