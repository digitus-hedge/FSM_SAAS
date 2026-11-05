@extends('layouts.technician-web')
@section('title', 'Requests')
@section('crumb', 'Requests')
@section('content')

<div class="pagehead">
    <div>
        <h1>Requests</h1>
        <p>Pending, quoted or approved jobs waiting on you.</p>
    </div>
    <a href="{{ route('technician.team.desktop.requests.create') }}" class="btn dark">+ New request</a>
</div>

<div class="card">
    @if ($needsAction->isEmpty())
        <div class="empty">Nothing needs your approval right now.</div>
    @else
        <div class="tblwrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Client / site</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($needsAction as $job)
                        @php $isPending = in_array($job->status, ['pending', 'quoted']); @endphp
                        <tr>
                            <td><b>{{ $job->sr_number }}</b><br><span class="muted" style="font-size:12.5px">{{ $job->title }}</span></td>
                            <td class="muted">{{ $job->client?->name }}@if ($job->site?->name), {{ $job->site->name }}@endif</td>
                            <td>
                                @if ($job->status === 'quoted')
                                    <span class="chip" style="--c:var(--amber)">Waiting for quote</span>
                                @else
                                    <span class="chip" style="--c:var(--{{ $isPending ? 'amber' : 'blue' }})">{{ $isPending ? ucfirst($job->status) : 'Needs assigning' }}</span>
                                @endif
                            </td>
                            <td style="text-align:right">
                                @if ($job->status === 'pending')
                                    <div style="display:inline-flex;gap:6px">
                                        <form method="POST" action="{{ route('technician.team.reject', $job) }}" class="reason-reject-form" style="display:inline">
                                            @csrf
                                            <input type="hidden" name="reason" class="reason-field">
                                            <button type="submit" class="btn ghost sm">Decline</button>
                                        </form>
                                        <form method="POST" action="{{ route('technician.team.approve', $job) }}" style="display:inline-flex;gap:6px">
                                            @csrf
                                            <select name="coverage_type" class="frm-select" required style="height:34px;padding:0 8px;font-size:12.5px">
                                                <option value="">Coverage…</option>
                                                <option value="in_warranty">In warranty</option>
                                                <option value="out_of_coverage">Out of coverage</option>
                                            </select>
                                            <button type="submit" class="btn dark sm">Approve</button>
                                        </form>
                                    </div>
                                @elseif ($job->status === 'quoted')
                                    <span class="muted" style="font-size:12.5px">Sent to the office — the client needs to accept the quote before this can be assigned.</span>
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
</script>
@endsection