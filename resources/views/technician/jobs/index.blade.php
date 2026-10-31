@extends('layouts.technician')
@section('title', 'Today')
@section('content')
@php
    $needsActionCount = (auth()->user()->role?->code === 'team_lead')
        ? \App\Models\ServiceRequest::where('team_lead_user_id', auth()->id())->whereIn('status', ['pending','quoted','approved'])->count()
        : 0;
@endphp

<div class="r1" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <span class="muted" style="font-size:14px">Updated {{ now()->format('H:i') }}</span>
    <button type="button" class="btn sm" onclick="location.reload()">@include('technician._icon', ['name'=>'refresh','size'=>17]) Refresh</button>
</div>

@if ($needsActionCount)
<a href="{{ route('technician.team.index') }}" class="card tap" style="display:block;text-decoration:none;color:inherit;border-color:color-mix(in srgb,var(--accent) 45%,var(--line));background:var(--tint)">
    <div style="display:flex;align-items:center;gap:12px">
        <span class="tick ok" style="background:var(--accent);color:var(--accent-ink)">@include('technician._icon', ['name'=>'send','size'=>15])</span>
        <div style="flex:1">
            <div style="font-weight:700">{{ $needsActionCount }} request{{ $needsActionCount === 1 ? '' : 's' }} need your action</div>
            <div class="sub">Approve, reject or assign them to your team</div>
        </div>
        @include('technician._icon', ['name'=>'right','size'=>20])
    </div>
</a>
@endif

@if ($nowJobs->isNotEmpty())
    <div class="sec">Now</div>
    @foreach ($nowJobs as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
@endif

<div class="sec">Today</div>
@forelse ($today as $job)
    @include('technician.jobs._card', ['job' => $job])
@empty
    <div class="card muted">No jobs assigned to you today.</div>
@endforelse

@if ($soon->isNotEmpty())
    <div class="sec">Coming up</div>
    @foreach ($soon as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
@endif

@if ($review->isNotEmpty())
    <div class="sec">Waiting for review</div>
    @foreach ($review as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
@endif
@endsection