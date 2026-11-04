@extends('layouts.technician')
@section('title', 'Today')
@section('content')
@php
    $u = auth()->user();
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $initials = collect(preg_split('/\s+/', trim($u->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');

    $needsActionCount = ($u->role?->code === 'team_lead')
        ? \App\Models\ServiceRequest::where('team_lead_user_id', $u->id)->whereIn('status', ['pending', 'quoted', 'approved'])->count()
        : 0;

    $nowOnes = $todayJobs->where('status', 'in_progress');
    $restOnes = $todayJobs->where('status', '!=', 'in_progress');
@endphp

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
        <div class="muted" style="font-size:13.5px">{{ $greeting }}</div>
        <div style="font-family:var(--font-display);font-weight:700;font-size:21px">{{ $u->name }}</div>
    </div>
    <div style="width:42px;height:42px;border-radius:999px;background:var(--ink);color:var(--bg);display:grid;place-items:center;font-weight:700;font-size:14px;position:relative;flex:0 0 auto">
        {{ strtoupper($initials ?: 'U') }}
        <span style="position:absolute;top:-2px;right:-2px;width:11px;height:11px;border-radius:50%;background:var(--green);border:2px solid var(--bg)"></span>
    </div>
</div>

<div style="background:var(--ink);color:#fff;border-radius:20px;padding:18px 18px 18px 20px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:14px">
    <div style="min-width:0">
        <div style="font-family:var(--font-display);font-weight:700;font-size:26px;line-height:1">{{ $total }} job{{ $total === 1 ? '' : 's' }}</div>
        <div style="opacity:.68;font-size:13px;margin-top:6px;white-space:nowrap">{{ $done }} done &middot; {{ $active }} active &middot; {{ $upcomingCount }} upcoming</div>
    </div>
    <div style="width:58px;height:58px;border-radius:50%;background:conic-gradient(var(--accent) {{ $progressPct * 3.6 }}deg, rgba(255,255,255,.16) 0deg);display:grid;place-items:center;flex:0 0 auto">
        <div style="width:46px;height:46px;border-radius:50%;background:var(--ink);display:grid;place-items:center;font-weight:700;font-size:13px">{{ $progressPct }}%</div>
    </div>
</div>

@if ($needsActionCount)
<a href="{{ route('technician.team.index') }}" class="card tap" style="display:block;text-decoration:none;color:inherit;border-color:color-mix(in srgb,var(--accent) 45%,var(--line));background:var(--tint);margin-bottom:14px">
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

<div class="tabs" role="tablist">
    <a href="#" id="tabbtn-today" data-tab="today" aria-selected="true" onclick="return techTab(event,'today')">Today</a>
    <a href="#" id="tabbtn-upcoming" data-tab="upcoming" aria-selected="false" onclick="return techTab(event,'upcoming')">Upcoming</a>
    <a href="#" id="tabbtn-history" data-tab="history" aria-selected="false" onclick="return techTab(event,'history')">History</a>
</div>

<div id="tab-today">
    @if ($todayJobs->isEmpty() && $review->isEmpty())
        <div class="card center" style="padding:40px 20px;margin-top:8px">
            <div style="width:54px;height:54px;border-radius:999px;background:var(--tint);color:var(--accent);display:grid;place-items:center;margin:0 auto 14px">
                @include('technician._icon', ['name'=>'check','size'=>26])
            </div>
            <h3 style="margin-bottom:6px">You're all caught up</h3>
            <p class="sub" style="margin:0 0 18px">No jobs assigned to you today. New assignments will show up here as soon as they're scheduled.</p>
            <a href="{{ route('technician.jobs.all') }}" class="btn sm" style="display:inline-flex">View all my jobs</a>
        </div>
    @else
        @if ($nowOnes->isNotEmpty())
            <div class="sec">Now</div>
            @foreach ($nowOnes as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
        @endif
        @if ($restOnes->isNotEmpty())
            <div class="sec">Today</div>
            @foreach ($restOnes as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
        @endif
        @if ($review->isNotEmpty())
            <div class="sec">Waiting for review</div>
            @foreach ($review as $job)@include('technician.jobs._card', ['job' => $job, 'linkable' => false])@endforeach
        @endif
    @endif
</div>

<div id="tab-upcoming" style="display:none">
    @if ($upcoming->isEmpty())
        <div class="card center" style="padding:32px 20px;margin-top:8px">
            <p class="sub" style="margin:0">No upcoming jobs scheduled yet.</p>
        </div>
    @else
        @foreach ($upcoming as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
    @endif
</div>

<div id="tab-history" style="display:none">
    @if ($history->isEmpty())
        <div class="card center" style="padding:32px 20px;margin-top:8px">
            <p class="sub" style="margin:0">No completed or cancelled jobs yet.</p>
        </div>
    @else
        @foreach ($history as $job)@include('technician.jobs._card', ['job' => $job])@endforeach
    @endif
</div>

<script>
function techTab(e, name) {
    e.preventDefault();
    ['today', 'upcoming', 'history'].forEach(function (t) {
        document.getElementById('tab-' + t).style.display = (t === name) ? '' : 'none';
        document.getElementById('tabbtn-' + t).setAttribute('aria-selected', t === name ? 'true' : 'false');
    });
    return false;
}
</script>
@endsection