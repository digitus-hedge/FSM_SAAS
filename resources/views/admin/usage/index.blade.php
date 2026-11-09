@extends('layouts.admin')

@section('content')
<div class="page-header">
    <h1>Usage</h1>
    <p class="text-muted">{{ $periodLabel }}</p>
</div>

@foreach ($limits as $key => $row)
    @if ($row['warn_100'])
        <div class="alert alert-danger">
            You've reached 100% of your plan's {{ strtolower($row['label']) }} limit.
            Nothing is blocked and work in progress continues — contact Aiywah to upgrade.
        </div>
    @elseif ($row['warn_80'])
        <div class="alert alert-warning">
            You're at {{ $row['percent'] }}% of your plan's {{ strtolower($row['label']) }} limit.
        </div>
    @endif
@endforeach

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Limit</th>
                <th>Used</th>
                <th>Plan limit</th>
                <th>% used</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($limits as $key => $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td>{{ $row['used'] }}{{ $key === 'storage' ? ' GB' : '' }}</td>
                    <td>{{ $row['limit'] !== null ? $row['limit'] . ($key === 'storage' ? ' GB' : '') : 'No limit' }}</td>
                    <td>
                        @if ($row['percent'] === null)
                            —
                        @else
                            <div class="progress">
                                <div class="progress-bar {{ $row['percent'] >= 100 ? 'bg-danger' : ($row['percent'] >= 80 ? 'bg-warning' : 'bg-success') }}"
                                     style="width: {{ min($row['percent'], 100) }}%"></div>
                            </div>
                            {{ $row['percent'] }}%
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <h3>Messages this month</h3>
    <p class="text-muted">Not counted against any plan limit — shown for visibility only.</p>
    <table class="table">
        <tbody>
            <tr><td>WhatsApp messages sent</td><td>{{ $whatsappCount }}</td></tr>
            <tr><td>Email messages sent</td><td>{{ $emailCount }}</td></tr>
        </tbody>
    </table>
</div>
@endsection