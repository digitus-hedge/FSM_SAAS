@extends('layouts.technician-web')
@section('title', 'Overrides')
@section('crumb', 'Overrides')
@section('content')

<div class="pagehead">
    <div>
        <h1>Overrides</h1>
        <p>Geofence override requests from your crew.</p>
    </div>
</div>

<div class="card">
    @if ($overrides->isEmpty())
        <div class="empty">No pending override requests.</div>
    @else
        <div class="tblwrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Request</th>
                        <th>Technician</th>
                        <th>Reason</th>
                        <th>Distance from site</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($overrides as $o)
                        <tr>
                            <td><b>{{ $o->serviceRequest?->sr_number }}</b><br><span class="muted" style="font-size:12.5px">{{ $o->serviceRequest?->title }}</span></td>
                            <td>{{ $o->technician?->name }}</td>
                            <td class="muted">{{ $o->reason ?: '—' }}</td>
                            <td class="muted">{{ $o->site_distance_meters ? $o->site_distance_meters . 'm' : '—' }}</td>
                            <td style="text-align:right">
                                <div style="display:inline-flex;gap:6px">
                                    <form method="POST" action="{{ route('technician.team.overrides.reject', $o) }}" class="note-reject-form" style="display:inline">
                                        @csrf
                                        <input type="hidden" name="decision_note" class="note-field">
                                        <button type="submit" class="btn ghost sm">Decline</button>
                                    </form>
                                    <form method="POST" action="{{ route('technician.team.overrides.approve', $o) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn dark sm">Approve</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
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