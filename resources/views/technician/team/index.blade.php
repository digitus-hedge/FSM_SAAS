@extends('layouts.technician')
@section('title', 'Team')
@section('content')

@php
    $initials = collect(preg_split('/\s+/', trim(auth()->user()->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $pendingCount = $needsAction->count() + $overrides->count() + $conflicts->count();
    $statusMap = [
        'on_track' => ['label' => 'On track', 'c' => 'green'],
        'at_risk'  => ['label' => 'At risk',  'c' => 'red'],
        'off_duty' => ['label' => 'Off duty', 'c' => 'slate'],
    ];
    $avatarPalette = ['blue', 'green', 'violet', 'amber', 'slate'];
@endphp

<div class="card" style="background:var(--ink);color:#fff;padding:20px;border-radius:18px;margin-bottom:14px;border:none">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
            <div style="font-size:12.5px;opacity:.6;font-weight:600">Team Lead</div>
            <div style="font-family:var(--font-display);font-size:21px;font-weight:700;margin-top:2px">{{ auth()->user()->name }}</div>
        </div>
        <a href="{{ route('technician.notifications') }}" style="position:relative;width:38px;height:38px;border-radius:999px;background:rgba(255,255,255,.14);display:grid;place-items:center;color:#fff;text-decoration:none;flex-shrink:0">
            @include('technician._icon', ['name' => 'bell', 'size' => 18])
            @if ($pendingCount > 0)
                <span style="position:absolute;top:5px;right:6px;width:9px;height:9px;border-radius:999px;background:var(--red);border:2px solid var(--ink)"></span>
            @endif
        </a>
    </div>
</div>

<div class="grid3" style="grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:18px">
    <div class="card center" style="padding:16px 6px">
        <div style="font-family:var(--font-display);font-size:22px;font-weight:800">{{ $teamMembers->count() }}</div>
        <div class="muted" style="font-size:11.5px;margin-top:2px">Crew</div>
    </div>
    <div class="card center" style="padding:16px 6px;background:var(--ink);color:#fff;border:none">
        <div style="font-family:var(--font-display);font-size:22px;font-weight:800">{{ $jobsTodayCount }}</div>
        <div style="font-size:11.5px;margin-top:2px;opacity:.65">Jobs today</div>
    </div>
    <div class="card center" style="padding:16px 6px">
        <div style="font-family:var(--font-display);font-size:22px;font-weight:800;color:var(--red)">{{ $atRiskCount }}</div>
        <div class="muted" style="font-size:11.5px;margin-top:2px">At risk</div>
    </div>
</div>

<div class="sec">Crew status</div>
@forelse ($teamMembers as $i => $m)
    @php
        $mi = collect(preg_split('/\s+/', trim($m->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
        $color = $avatarPalette[$i % count($avatarPalette)];
        $st = $statusMap[$m->crew_status];
    @endphp
    <div class="card" style="display:flex;align-items:center;gap:12px;padding:12px 14px;margin-bottom:8px{{ $m->crew_status === 'at_risk' ? ';border-color:var(--red)' : '' }}">
        <div style="width:42px;height:42px;border-radius:999px;background:var(--{{ $color }});color:#fff;display:grid;place-items:center;font-weight:700;font-size:14px;flex-shrink:0">
            {{ strtoupper($mi ?: '?') }}
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-weight:700;font-size:14.5px">
                {{ $m->name }}
                @if ($m->id === auth()->id())<span class="muted" style="font-weight:500">&nbsp;(you)</span>@endif
            </div>
            <div class="muted" style="font-size:12.5px;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $m->crew_line }}</div>
        </div>
        <span style="font-size:12.5px;font-weight:700;color:var(--{{ $st['c'] }});white-space:nowrap;flex-shrink:0">{{ $st['label'] }}</span>
    </div>
@empty
    <div class="card muted">No crew members assigned to you.</div>
@endforelse

<div class="sec" style="margin-top:18px">Needs approval</div>

@if ($pendingCount === 0)
    <div class="card muted">Nothing needs your approval right now.</div>
@endif

@foreach ($needsAction as $job)
    @php $isPending = in_array($job->status, ['pending', 'quoted']); @endphp
    <div class="card" style="padding:14px;margin-bottom:10px">
        <div style="font-weight:700;font-size:14.5px">{{ $isPending ? 'Approval needed' : 'Needs assigning' }} — {{ $job->sr_number }}</div>
        <div class="muted" style="font-size:13px;margin-top:2px">{{ $job->title }} · {{ $job->client?->name }}@if ($job->site?->name), {{ $job->site->name }}@endif</div>

        @if ($isPending)
            <div class="row2" style="margin-top:10px">
                <form method="POST" action="{{ route('technician.team.reject', $job) }}" class="reason-reject-form">
                    @csrf
                    <input type="hidden" name="reason" class="reason-field">
                    <button type="submit" class="btn sm block">Decline</button>
                </form>
                <form method="POST" action="{{ route('technician.team.approve', $job) }}">
                    @csrf
                    <button type="submit" class="btn dark sm block">Approve</button>
                </form>
            </div>
        @else
            <form method="POST" action="{{ route('technician.team.assign', $job) }}" style="margin-top:10px">
                @csrf
                <select name="assigned_tech_user_id" class="sel" required style="margin-bottom:8px">
                    <option value="">Assign to…</option>
                    @foreach ($teamMembers as $tm)<option value="{{ $tm->id }}">{{ $tm->name }}</option>@endforeach
                </select>
                <button type="submit" class="btn dark block">Assign</button>
            </form>
        @endif
    </div>
@endforeach

@foreach ($overrides as $o)
    <div class="card" style="padding:14px;margin-bottom:10px">
        <div style="font-weight:700;font-size:14.5px">Geofence override — {{ $o->technician?->name }}</div>
        <div class="muted" style="font-size:13px;margin-top:2px">{{ $o->serviceRequest?->sr_number }} · checked in {{ $o->site_distance_meters }}m from site</div>
        @if ($o->reason)<div style="font-size:13.5px;margin-top:6px">{{ $o->reason }}</div>@endif
        <div class="row2" style="margin-top:10px">
            <form method="POST" action="{{ route('technician.team.overrides.reject', $o) }}" class="note-reject-form">
                @csrf
                <input type="hidden" name="decision_note" class="note-field">
                <button type="submit" class="btn sm block">Decline</button>
            </form>
            <form method="POST" action="{{ route('technician.team.overrides.approve', $o) }}">
                @csrf
                <button type="submit" class="btn dark sm block">Approve</button>
            </form>
        </div>
    </div>
@endforeach

@foreach ($conflicts as $c)
    <div class="card" style="padding:14px;margin-bottom:10px">
        <div style="font-weight:700;font-size:14.5px">Conflict — {{ $c->user?->name }}</div>
        <div class="muted" style="font-size:13px;margin-top:2px">{{ $c->serviceRequest?->sr_number }} · {{ $c->conflict_code }}</div>
        @if ($c->conflict_note)<div style="font-size:13.5px;margin-top:6px">{{ $c->conflict_note }}</div>@endif
        <form method="POST" action="{{ route('technician.team.conflicts.reviewed', $c) }}" style="margin-top:10px">
            @csrf
            <button type="submit" class="btn dark block">Mark reviewed</button>
        </form>
    </div>
@endforeach

<script>
document.querySelectorAll('.reason-reject-form').forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const reason = prompt('Reason for declining this?', '');
        if (reason === null) return;
        if (! reason.trim()) { alert('Please give a reason.'); return; }
        form.querySelector('.reason-field').value = reason.trim();
        form.submit();
    });
});
document.querySelectorAll('.note-reject-form').forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const note = prompt('Note for this decline (optional):', '');
        if (note === null) return;
        form.querySelector('.note-field').value = note.trim();
        form.submit();
    });
});
</script>
@endsection