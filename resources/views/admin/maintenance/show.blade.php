@extends('layouts.admin')
@section('title', $schedule->name)

@section('content')
@include('admin.partials._page')

<div class="page-head">
  <div>
    <h1>{{ $schedule->name }}</h1>
    <p>{{ $schedule->site?->name }} · {{ $schedule->category?->name }} · {{ $schedule->frequency_label }}
      <span class="pill {{ $schedule->status === 'active' ? 'pill-ok' : ($schedule->status === 'paused' ? 'pill-amber' : '') }}">{{ ucfirst($schedule->status) }}</span></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="{{ route('admin.maintenance.index') }}" class="btn btn-ghost">Back</a>
    @if ($canManage && $schedule->status !== 'ended')
      <a href="{{ route('admin.maintenance.edit', $schedule) }}" class="btn btn-ghost">Edit</a>
      @if ($schedule->status === 'active')
        <form method="POST" action="{{ route('admin.maintenance.pause', $schedule) }}">@csrf<button class="btn btn-ghost">Pause</button></form>
      @else
        <form method="POST" action="{{ route('admin.maintenance.resume', $schedule) }}">@csrf<button class="btn btn-ghost">Resume</button></form>
      @endif
      <form method="POST" action="{{ route('admin.maintenance.end', $schedule) }}" onsubmit="return confirm('End this schedule?')">@csrf<button class="btn btn-danger">End</button></form>
    @endif
  </div>
</div>

<div class="card pad" style="margin-bottom:14px">
  <table style="width:auto">
    <tr><td class="hint">Next due</td><td>{{ $schedule->next_due_date?->format('j M Y') }} (request created {{ $schedule->lead_days }} days before)</td></tr>
    <tr><td class="hint">Default technician</td><td>{{ $schedule->technician?->name ?? '—' }}</td></tr>
    <tr><td class="hint">Checklist</td><td>{{ $schedule->template?->name ?? '—' }}</td></tr>
    <tr><td class="hint">Pinned contract</td><td>{{ $schedule->contract?->contract_number ?? '—' }}</td></tr>
    <tr><td class="hint">Skip if open</td><td>{{ $schedule->skip_if_open ? 'Yes' : 'No' }}</td></tr>
    <tr><td class="hint">Assets</td><td>{{ $schedule->assetRows->map(fn ($r) => $r->asset?->name)->filter()->implode(', ') }}</td></tr>
    @if ($schedule->end_date)<tr><td class="hint">Ends</td><td>{{ $schedule->end_date->format('j M Y') }}</td></tr>@endif
  </table>
</div>

@if (count($upcoming))
<div class="card pad" style="margin-bottom:14px"><strong>Next due dates</strong>
  <div class="hint" style="margin-top:6px">{{ collect($upcoming)->take(6)->map(fn ($d) => $d->format('j M Y'))->implode(', ') }}</div></div>
@endif

<div class="card" style="margin-bottom:14px">
  <table>
    <thead><tr><th>Due date</th><th>Result</th><th>Request</th><th>Note</th></tr></thead>
    <tbody>
    @forelse ($schedule->runs as $run)
      <tr>
        <td>{{ $run->due_date->format('j M Y') }}</td>
        <td>{{ ucfirst(str_replace('_', ' ', $run->result)) }}</td>
        <td>@if ($run->serviceRequest)<a href="{{ route('service-requests.show', $run->serviceRequest) }}">{{ $run->serviceRequest->sr_number }}</a>@endif</td>
        <td>{{ $run->note }}</td>
      </tr>
    @empty
      <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--muted)">Nothing has been created yet.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
