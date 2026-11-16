@extends('layouts.admin')
@section('title', 'Technician workload')

@section('content')
<style>
  .dsp-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:16px}
  .dsp-head h1{margin:0 0 4px;font-size:22px}
  .dsp-head p{margin:0;color:var(--muted);font-size:13px}
  .dsp-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  .dsp-table{width:100%;border-collapse:collapse;font-size:13px}
  .dsp-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:10px 14px;border-bottom:1px solid var(--line);background:#FAFAFB}
  .dsp-table td{padding:12px 14px;border-bottom:1px solid var(--line);vertical-align:middle}
  .dsp-table tr:last-child td{border-bottom:0}
  .dsp-bar{height:8px;border-radius:999px;background:#EEEEF2;overflow:hidden;min-width:120px}
  .dsp-bar > i{display:block;height:100%;border-radius:999px}
  .dsp-green > i{background:var(--green)} .dsp-amber > i{background:#D99A00} .dsp-red > i{background:var(--red)}
  .dsp-pill{display:inline-block;font-size:11px;font-weight:600;padding:2px 10px;border-radius:999px}
  .dsp-pill.green{background:var(--green-soft);color:var(--green)}
  .dsp-pill.amber{background:var(--yellow-soft);color:var(--amber)}
  .dsp-pill.red{background:var(--red-soft);color:var(--red)}
  .dsp-lead{font-size:11px;color:var(--muted);margin-left:6px}
  .dsp-key{font-size:12px;color:var(--muted);margin-top:12px}
  .dsp-empty{padding:28px;text-align:center;color:var(--muted);font-size:13px}
  .dsp-link{color:var(--amber);font-weight:600;text-decoration:none;border-bottom:1px solid var(--yellow);font-size:13px}
</style>

<div class="dsp-head">
  <div>
    <h1>Technician workload</h1>
    <p>Scheduled work over the next 7 days against each person's capacity. Each scheduled job counts {{ $settings->job_minutes }} minutes.</p>
  </div>
  @if (auth()->user()->role?->code === 'company_admin')
    <a class="dsp-link" href="{{ route('dispatch.settings') }}">Dispatch settings</a>
  @endif
</div>

<div class="dsp-card">
  @if ($rows->isEmpty())
    <div class="dsp-empty">No active technicians or team leads yet.</div>
  @else
  <table class="dsp-table">
    <thead>
      <tr><th>Technician</th><th>Jobs (7 days)</th><th>Load</th><th>Next job</th><th>Location basis</th></tr>
    </thead>
    <tbody>
      @foreach ($rows as $r)
      <tr>
        <td><strong>{{ $r['name'] }}</strong>@if ($r['is_lead'])<span class="dsp-lead">Team lead</span>@endif</td>
        <td>{{ $r['jobs'] }}</td>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="dsp-bar dsp-{{ $r['band'] }}" style="flex:1"><i style="width:{{ min(100, $r['pct']) }}%"></i></div>
            <span class="dsp-pill {{ $r['band'] }}">{{ rtrim(rtrim(number_format($r['pct'], 1), '0'), '.') }}%</span>
          </div>
          <div style="font-size:11px;color:var(--muted);margin-top:3px">{{ $r['minutes'] }} of {{ $r['capacity'] }} minutes</div>
        </td>
        <td>{{ $r['next'] ? $r['next']->format('D d M, H:i') : 'None scheduled' }}</td>
        <td>{{ $r['location'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</div>
<div class="dsp-key">Green below 60 percent, amber 60 to 90, red above 90. Capacity is daily capacity times 7.</div>
@endsection
