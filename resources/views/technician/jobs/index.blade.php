@extends('layouts.technician')
@section('title', 'Today')

@section('content')
@php
  $today    = $jobs->filter(fn($j) => $j->scheduled_eta_at && \Carbon\Carbon::parse($j->scheduled_eta_at)->isToday());
  $upcoming = $jobs->filter(fn($j) => $j->scheduled_eta_at && \Carbon\Carbon::parse($j->scheduled_eta_at)->isFuture() && ! \Carbon\Carbon::parse($j->scheduled_eta_at)->isToday());
@endphp

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
  <div style="font-size:13px;color:var(--muted)">Updated {{ now()->format('H:i') }}</div>
  <a href="{{ route('technician.jobs.index') }}" class="btn btn-ghost" style="width:auto;padding:8px 16px;font-size:13px;gap:6px">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
    Refresh
  </a>
</div>

@if ($today->count())
  <div style="font-size:11.5px;font-weight:800;color:var(--subtle);letter-spacing:.1em;margin:4px 2px 10px;text-transform:uppercase">Today</div>
  @foreach ($today as $job) @include('technician.jobs._card', ['job' => $job]) @endforeach
@endif

@if ($upcoming->count())
  <div style="font-size:11.5px;font-weight:800;color:var(--subtle);letter-spacing:.1em;margin:18px 2px 10px;text-transform:uppercase">Coming up</div>
  @foreach ($upcoming as $job) @include('technician.jobs._card', ['job' => $job]) @endforeach
@endif

@if ($today->isEmpty() && $upcoming->isEmpty())
  <div class="card" style="text-align:center;padding:50px 20px;color:var(--muted)">
    <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--subtle);margin-bottom:10px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <div style="font-family:'Figtree';font-size:17px;font-weight:700;color:var(--ink);margin-bottom:4px">All clear</div>
    <div style="font-size:13.5px">No jobs for you right now.</div>
  </div>
@endif
@endsection