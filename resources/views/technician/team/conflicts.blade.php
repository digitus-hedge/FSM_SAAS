@extends('layouts.technician-web')
@section('title', 'Field conflicts')
@section('crumb', 'Field conflicts')
@section('content')

<div class="pagehead">
    <div>
        <h1>Field conflicts</h1>
        <p>Flagged events that need your review.</p>
    </div>
</div>

<div class="card">
    @if ($conflicts->isEmpty())
        <div class="empty">No open conflicts.</div>
    @else
        <div class="tblwrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Technician</th>
                        <th>What was sent</th>
                        <th>What happened</th>
                        <th>When</th>
                        <th>Evidence</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($conflicts as $c)
                        <tr>
                            <td><b>{{ $c->serviceRequest?->sr_number }}</b><br><span class="muted" style="font-size:12.5px">{{ $c->serviceRequest?->title }}</span></td>
                            <td>{{ $c->user?->name }}</td>
                            <td class="muted">{{ \Illuminate\Support\Str::of($c->event_type ?? '—')->replace('_', ' ')->title() }}</td>
                            <td><span class="chip" style="--c:var(--red)">{{ $c->conflict_note ?: ($c->conflict_code ?: 'Conflict') }}</span></td>
                            <td class="muted">{{ $c->created_at?->diffForHumans() }}</td>
                            <td class="muted">Kept on file</td>
                            <td style="text-align:right">
                                <form method="POST" action="{{ route('technician.team.conflicts.reviewed', $c) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn dark sm">Mark reviewed</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection