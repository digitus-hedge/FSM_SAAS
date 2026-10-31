@extends('layouts.technician')
@section('title', 'Team')
@section('content')
<div class="tabs">
    <a href="{{ route('technician.team.index', ['tab'=>'jobs']) }}" aria-selected="{{ $tab==='jobs'?'true':'false' }}">Jobs</a>
    <a href="{{ route('technician.team.index', ['tab'=>'action']) }}" aria-selected="{{ $tab==='action'?'true':'false' }}">Needs action{{ $needsActionCount ? " ($needsActionCount)" : '' }}</a>
    <a href="{{ route('technician.team.index', ['tab'=>'ovr']) }}" aria-selected="{{ $tab==='ovr'?'true':'false' }}">Overrides{{ $overrideCount ? " ($overrideCount)" : '' }}</a>
    <a href="{{ route('technician.team.index', ['tab'=>'conf']) }}" aria-selected="{{ $tab==='conf'?'true':'false' }}">Conflicts{{ $conflictCount ? " ($conflictCount)" : '' }}</a>
</div>

@if ($tab === 'jobs')
    @forelse ($jobsByTech as $techName => $jobs)
        <div class="muted" style="font-weight:700;margin:12px 2px 6px">{{ $techName }}</div>
        @foreach ($jobs as $job)@include('technician.jobs._card', ['job' => $job, 'linkable' => false])@endforeach
    @empty
        <div class="card muted">No team jobs right now.</div>
    @endforelse
@endif

@if ($tab === 'action')
    @forelse ($needsAction as $job)
        @php $isPending = in_array($job->status, ['pending', 'quoted']); @endphp
        <div class="card">
            <div class="r1">
                <span>{{ $job->sr_number }}</span>
                <span class="chip sm" style="--c:var(--{{ ['emergency'=>'red','critical'=>'red','high'=>'amber','medium'=>'blue','low'=>'slate'][$job->priority] ?? 'slate' }})">{{ ucfirst($job->priority) }}</span>
                <span class="chip sm" style="--c:var(--{{ $isPending ? 'violet' : 'blue' }})">{{ $isPending ? 'Needs approval' : 'Needs assigning' }}</span>
            </div>
            <h3>{{ $job->title }}</h3>
            <div class="sub">{{ $job->client?->name }}, {{ $job->site?->name }}</div>

            @if ($isPending)
                <div class="row2" style="margin-top:10px">
                    <form method="POST" action="{{ route('technician.team.reject', $job) }}" onsubmit="return confirm('Reject this request?')">
                        @csrf
                        <input type="hidden" name="reason" value="Rejected by team lead">
                        <button type="submit" class="btn danger sm block">Reject</button>
                    </form>
                    <form method="POST" action="{{ route('technician.team.approve', $job) }}">
                        @csrf
                        <button type="submit" class="btn primary sm block">Approve</button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('technician.team.assign', $job) }}" style="margin-top:10px">
                    @csrf
                    <select name="assigned_tech_user_id" class="sel" required style="margin-bottom:8px">
                        <option value="">Assign to…</option>
                        @foreach ($teamMembers as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                    </select>
                    <button type="submit" class="btn primary block">Assign</button>
                </form>
            @endif
        </div>
    @empty
        <div class="card muted">Nothing needs your action right now.</div>
    @endforelse
@endif

@if ($tab === 'ovr')
    @forelse ($overrides as $o)
        <div class="card">
            <div class="r1"><span>{{ $o->serviceRequest?->sr_number }}</span><span class="chip sm" style="--c:var(--amber)">Pending</span></div>
            <h3>{{ $o->serviceRequest?->title }}</h3>
            <div class="sub">{{ $o->technician?->name }} · {{ $o->site_distance_meters }}m from site</div>
            <div style="margin-top:8px;font-size:14.5px">{{ $o->reason }}</div>
            <div class="row2" style="margin-top:10px">
                <form method="POST" action="{{ route('technician.team.overrides.reject', $o) }}">
                    @csrf
                    <button type="submit" class="btn danger sm block">Reject</button>
                </form>
                <form method="POST" action="{{ route('technician.team.overrides.approve', $o) }}">
                    @csrf
                    <button type="submit" class="btn primary sm block">Approve</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card muted">No override requests are waiting.</div>
    @endforelse
@endif

@if ($tab === 'conf')
    @forelse ($conflicts as $c)
        <div class="card">
            <div class="r1"><span>{{ $c->serviceRequest?->sr_number }}</span><span class="chip sm" style="--c:var(--red)">Conflict</span></div>
            <h3>{{ $c->serviceRequest?->title }}</h3>
            <div class="sub">{{ $c->user?->name }} · {{ $c->conflict_code }}</div>
            @if ($c->conflict_note)<div style="margin-top:8px;font-size:14.5px">{{ $c->conflict_note }}</div>@endif
            <form method="POST" action="{{ route('technician.team.conflicts.reviewed', $c) }}" style="margin-top:10px">
                @csrf
                <button type="submit" class="btn block">Mark reviewed</button>
            </form>
        </div>
    @empty
        <div class="card muted">No conflicts waiting for review.</div>
    @endforelse
@endif
@endsection