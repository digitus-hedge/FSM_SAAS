@extends('layouts.technician')
@section('title', 'Today')
@section('content')
@php
    $needsActionCount = (auth()->user()->role?->code === 'team_lead')
        ? \App\Models\ServiceRequest::where('team_lead_user_id', auth()->id())->whereIn('status', ['pending','quoted','approved'])->count()
        : 0;

    $totalToday = $nowJobs->count() + $today->count() + $soon->count();
    $allEmpty   = $nowJobs->isEmpty() && $today->isEmpty() && $soon->isEmpty() && $review->isEmpty();
@endphp

<div class="r1" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
    <span class="muted" style="font-size:14px">Updated {{ now()->format('H:i') }}</span>
    <button type="button" class="btn sm" onclick="location.reload()">@include('technician._icon', ['name'=>'refresh','size'=>17]) Refresh</button>
</div>

@unless ($allEmpty)
<div class="muted" style="font-size:13.5px;margin-bottom:16px">
    {{ $totalToday }} job{{ $totalToday === 1 ? '' : 's' }} today
    @if ($review->isNotEmpty()) &middot; {{ $review->count() }} waiting for review @endif
</div>
@endunless

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

@if ($allEmpty)
    <div class="card center" style="padding:40px 20px;margin-top:8px">
        <div style="width:54px;height:54px;border-radius:999px;background:var(--tint);color:var(--accent);display:grid;place-items:center;margin:0 auto 14px">
            @include('technician._icon', ['name'=>'check','size'=>26])
        </div>
        <h3 style="margin-bottom:6px">You're all caught up</h3>
        <p class="sub" style="margin:0 0 18px">No jobs assigned to you today. New assignments will show up here as soon as they're scheduled.</p>
        <a href="{{ route('technician.jobs.all') }}" class="btn sm" style="display:inline-flex">View all my jobs</a>
    </div>
@else
    @if ($nowJobs->isNotEmpty())
        <div class="sec">Now</div>
        @foreach ($nowJobs as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
    @endif

    @if ($today->isNotEmpty())
        <div class="sec">Today</div>
        @foreach ($today as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
    @endif

    @if ($soon->isNotEmpty())
        <div class="sec">Coming up</div>
        @foreach ($soon as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
    @endif

    @if ($review->isNotEmpty())
        <div class="sec">Waiting for review</div>
        @foreach ($review as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
    @endif
@endif
@endsection