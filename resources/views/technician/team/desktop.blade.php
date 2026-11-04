@extends('layouts.technician-web')
@section('title', 'My Jobs')
@section('crumb', 'My Jobs')
@section('content')

@php
    $statusPill = [
        'assigned'       => 'blue',
        'in_progress'    => 'amber',
        'rework'         => 'red',
        'pending_review' => 'orange',
        'completed'      => 'green',
        'cancelled'      => 'slate',
    ];
@endphp

<div class="pagehead">
    <div>
        <h1>My jobs</h1>
        <p>Your own field work — punching in, photos and proof still happen on your phone.</p>
    </div>
</div>

<div class="kpis">
    <div class="kpi">
        <span>Today</span>
        <b>{{ $total }}</b>
    </div>
    <div class="kpi dark">
        <span>In progress</span>
        <b>{{ $active }}</b>
    </div>
    <div class="kpi">
        <span>Upcoming</span>
        <b>{{ $upcomingCount }}</b>
    </div>
    <div class="kpi">
        <span>Waiting for review</span>
        <b>{{ $review->count() }}</b>
    </div>
</div>

<div class="card">
    <div class="sech">
        <h3>Today</h3>
    </div>
    @if ($todayJobs->isEmpty())
        <div class="empty">Nothing scheduled for today.</div>
    @else
        <div class="tblwrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Client / site</th>
                        <th>Arrival</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($todayJobs as $job)
                        <tr>
                            <td><b>{{ $job->sr_number }}</b><br><span class="muted" style="font-size:12.5px">{{ $job->title }}</span></td>
                            <td class="muted">{{ $job->client?->name }}@if ($job->site?->name), {{ $job->site->name }}@endif</td>
                            <td class="muted">{{ $job->scheduled_eta_at?->format('d M, H:i') ?? '—' }}</td>
                            <td><span class="chip" style="--c:var(--{{ $statusPill[$job->status] ?? 'slate' }})">{{ ucwords(str_replace('_', ' ', $job->status)) }}</span></td>
                            <td style="text-align:right"><a href="{{ route('technician.jobs.show', $job) }}" class="btn ghost sm">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <div class="sech">
        <h3>Waiting for review</h3>
    </div>
    @if ($review->isEmpty())
        <div class="empty">Nothing waiting on review.</div>
    @else
        <div class="tblwrap">
            <table class="tbl">
                <thead>
                    <tr><th>Request</th><th>Client / site</th><th>Punched out</th><th style="text-align:right">Actions</th></tr>
                </thead>
                <tbody>
                    @foreach ($review as $job)
                        <tr>
                            <td><b>{{ $job->sr_number }}</b><br><span class="muted" style="font-size:12.5px">{{ $job->title }}</span></td>
                            <td class="muted">{{ $job->client?->name }}@if ($job->site?->name), {{ $job->site->name }}@endif</td>
                            <td class="muted">{{ $job->last_punch_out_at?->diffForHumans() }}</td>
                            <td style="text-align:right"><a href="{{ route('technician.jobs.show', $job) }}" class="btn ghost sm">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <div class="sech">
        <h3>Upcoming</h3>
    </div>
    @if ($upcoming->isEmpty())
        <div class="empty">Nothing booked beyond today.</div>
    @else
        <div class="tblwrap">
            <table class="tbl">
                <thead>
                    <tr><th>Request</th><th>Client / site</th><th>Arrival</th></tr>
                </thead>
                <tbody>
                    @foreach ($upcoming as $job)
                        <tr>
                            <td><a href="{{ route('technician.jobs.show', $job) }}" style="text-decoration:none;color:inherit"><b>{{ $job->sr_number }}</b><br><span class="muted" style="font-size:12.5px">{{ $job->title }}</span></a></td>
                            <td class="muted">{{ $job->client?->name }}@if ($job->site?->name), {{ $job->site->name }}@endif</td>
                            <td class="muted">{{ $job->scheduled_eta_at?->format('d M, H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection