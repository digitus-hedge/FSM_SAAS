@extends('layouts.technician-web')
@section('title', 'Home')
@section('crumb', 'Home')
@section('content')

@php
    // "Needs your action" — pending (approve) + approved (assign), emergency first,
    // then by resolution due date, then newest created first. Matches the prototype's
    // cmpReq ordering.
    $priorityRank = ['emergency' => 0, 'critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
    $actionRows = $needsAction
        ->filter(fn ($r) => in_array($r->status, ['pending', 'approved']))
        ->sort(function ($a, $b) use ($priorityRank) {
            $pa = $priorityRank[$a->priority] ?? 9;
            $pb = $priorityRank[$b->priority] ?? 9;
            if ($pa !== $pb) return $pa <=> $pb;
            $da = $a->sla_resolution_due_at;
            $db = $b->sla_resolution_due_at;
            if ($da && $db && ! $da->eq($db)) return $da <=> $db;
            if ($da && ! $db) return -1;
            if ($db && ! $da) return 1;
            return $b->created_at <=> $a->created_at;
        })
        ->take(6);
@endphp

<div class="pagehead">
    <div>
        <h1>Team dashboard</h1>
        <p>Your team: {{ $teamMembers->pluck('name')->join(', ') ?: 'No one assigned to you yet' }}.</p>
    </div>
</div>

<div class="kpis">
    <a href="{{ route('technician.team.desktop.requests') }}" class="kpi" style="text-decoration:none;color:inherit">
        <span>Needs approval</span>
        <b>{{ $needsApprovalCount }}</b>
    </a>
    <a href="{{ route('technician.team.desktop.requests') }}" class="kpi dark" style="text-decoration:none">
        <span>To assign</span>
        <b>{{ $toAssignCount }}</b>
    </a>
    <a href="{{ route('technician.team.desktop') }}" class="kpi" style="text-decoration:none;color:inherit">
        <span>In progress</span>
        <b>{{ $inProgressCount }}</b>
    </a>
    <a href="{{ route('technician.team.desktop') }}" class="kpi" style="text-decoration:none;color:inherit">
        <span>To review</span>
        <b>{{ $toReview->count() }}</b>
    </a>
    <a href="{{ route('technician.team.desktop') }}" class="kpi" style="text-decoration:none;color:inherit">
        <span>Rework</span>
        <b>{{ $reworkCount }}</b>
    </a>
</div>

<div class="card">
    <div class="sech">
        <h3>Needs your action</h3>
        <a href="{{ route('technician.team.desktop.requests') }}" class="btn ghost sm">Open the request list</a>
    </div>
    @if ($actionRows->isEmpty())
        <div class="empty">Nothing needs your action right now.</div>
    @else
        <div class="tblwrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Client / site</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>SLA</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($actionRows as $job)
                        <tr>
                            <td><b>{{ $job->sr_number }}</b><br><span class="muted" style="font-size:12.5px">{{ $job->title }}</span></td>
                            <td class="muted">{{ $job->client?->name }}@if ($job->site?->name), {{ $job->site->name }}@endif</td>
                            <td><span class="chip" style="--c:var(--{{ $job->priorityColor() }})">{{ $job->priorityLabel() }}</span></td>
                            <td><span class="chip" style="--c:var(--blue)">{{ $job->statusLabel() }}</span></td>
                            <td><span class="chip" style="--c:var(--{{ $job->slaStateColor() }})">{{ $job->slaStateLabel() }}</span></td>
                            <td style="text-align:right">
                                @if ($job->status === 'pending')
                                    <form method="POST" action="{{ route('technician.team.approve', $job) }}" style="display:inline-flex;gap:6px">
                                        @csrf
                                        <select name="coverage_type" class="frm-select" required style="height:34px;padding:0 8px;font-size:12.5px">
                                            <option value="">Coverage…</option>
                                            <option value="in_warranty" @selected($job->coverage_type === 'in_warranty')>In warranty</option>
                                            <option value="out_of_coverage" @selected(! $job->coverage_type || $job->coverage_type === 'out_of_coverage')>Out of coverage</option>
                                        </select>
                                        <button type="submit" class="btn dark sm">Approve</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('technician.team.assign', $job) }}" style="display:inline-flex;gap:6px">
                                        @csrf
                                        <select name="assigned_tech_user_id" class="frm-select" required style="height:34px;padding:0 8px;font-size:12.5px">
                                            <option value="">Assign to…</option>
                                            @foreach ($teamMembers as $tm)<option value="{{ $tm->id }}">{{ $tm->name }}</option>@endforeach
                                        </select>
                                        <button type="submit" class="btn dark sm">Assign</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <div class="sech">
        <h3>Team workload today</h3>
    </div>
    @if ($teamMembers->isEmpty())
        <div class="empty">No one is assigned to you yet.</div>
    @else
        <div class="tblwrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Technician</th>
                        <th>Active jobs</th>
                        <th>Load</th>
                        <th>Request numbers</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teamMembers as $member)
                        @php
                            $n = $member->active_jobs->count();
                            $loadColor = $n >= 5 ? 'red' : ($n >= 3 ? 'amber' : 'green');
                        @endphp
                        <tr>
                            <td><b>{{ $member->name }}</b></td>
                            <td>{{ $n }}</td>
                            <td><span class="chip" style="--c:var(--{{ $n ? $loadColor : 'slate' }})">{{ $n ? ucfirst($loadColor) : 'None' }}</span></td>
                            <td class="muted" style="font-size:13px">{{ $member->active_jobs->pluck('sr_number')->join(', ') ?: 'No jobs' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <h3>What you can do here</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:10px">
        <div>
            <b>You can</b>
            <ul style="margin:8px 0 0;padding-left:18px;font-size:13.5px;line-height:1.7">
                <li>See, approve, reject and assign requests for your own team</li>
                <li>Raise new requests and edit details before work starts</li>
                <li>Approve geofence overrides and review field conflicts for your team</li>
                <li>Add notes</li>
            </ul>
        </div>
        <div>
            <b>You cannot</b>
            <ul style="margin:8px 0 0;padding-left:18px;font-size:13.5px;line-height:1.7">
                <li>Cancel or reopen a request — the Operations Manager does that</li>
                <li>Pass the quality check, unless the admin switches it on for you</li>
                <li>See quotes, invoices, expense amounts or contract values</li>
                <li>Manage clients, sites, users or settings</li>
            </ul>
        </div>
    </div>
</div>
@endsection