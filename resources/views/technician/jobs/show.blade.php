@extends('layouts.technician')
@section('title', $serviceRequest->sr_number)
@section('back', route('technician.jobs.index'))
@section('content')
<div class="card">
    <div style="font-weight:800;font-size:16px">{{ $serviceRequest->title }}</div>
    <div style="color:var(--muted);font-size:13px;margin-top:4px">{{ $serviceRequest->client?->name }} · {{ $serviceRequest->site?->name }}</div>
    <div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap">
        <span class="chip {{ $serviceRequest->priorityColor() }}">{{ $serviceRequest->priorityLabel() }}</span>
        <span class="chip {{ $serviceRequest->is_on_hold ? 'amber' : '' }}">{{ $serviceRequest->is_on_hold ? 'On hold' : $serviceRequest->statusLabel() }}</span>
        @if ($serviceRequest->enforce_geofence)<span class="chip">Geofence enforced</span>@endif
    </div>
    @if ($serviceRequest->description)<p style="margin-top:12px;font-size:13.5px">{{ $serviceRequest->description }}</p>@endif
    <div style="margin-top:12px;font-size:12.5px;color:var(--muted)">Scheduled: {{ $serviceRequest->scheduled_eta_at?->format('D, j M g:ia') ?? '—' }}</div>
</div>

@if (! $serviceRequest->tech_accepted_at)
<form method="POST" action="{{ route('technician.jobs.accept', $serviceRequest) }}">
    @csrf
    <button type="submit" class="btn block">Accept job</button>
</form>
@endif

<div class="card">
    <div style="font-weight:700;font-size:13.5px;margin-bottom:10px">Change time</div>
    <form method="POST" action="{{ route('technician.jobs.change-time', $serviceRequest) }}">
        @csrf
        <label class="field">New time<input type="datetime-local" name="new_eta_at" required></label>
        <label class="field">Reason<input type="text" name="reason" maxlength="255"></label>
        <button type="submit" class="btn secondary block">Update time</button>
    </form>
</div>

<div class="card">
    <div style="font-weight:700;font-size:13.5px;margin-bottom:10px">Punch</div>
    @if ($serviceRequest->status !== 'in_progress' && $serviceRequest->status !== 'pending_review')
        <form method="POST" action="{{ route('technician.jobs.punch-in', $serviceRequest) }}">
            @csrf
            <input type="hidden" name="client_event_uuid" class="uuid-field">
            <input type="hidden" name="gps_lat"><input type="hidden" name="gps_lng"><input type="hidden" name="gps_accuracy">
            <button type="submit" class="btn block gps-submit">Punch in</button>
        </form>
    @elseif ($serviceRequest->is_on_hold)
        <form method="POST" action="{{ route('technician.jobs.resume', $serviceRequest) }}">
            @csrf
            <input type="hidden" name="client_event_uuid" value="{{ Str::uuid() }}">
            <button type="submit" class="btn block">Resume</button>
        </form>
    @elseif ($serviceRequest->status === 'in_progress')
        <details style="margin-bottom:10px">
            <summary class="btn secondary block" style="list-style:none;cursor:pointer">Put on hold</summary>
            <form method="POST" action="{{ route('technician.jobs.hold', $serviceRequest) }}" style="margin-top:10px">
                @csrf
                <input type="hidden" name="client_event_uuid" value="{{ Str::uuid() }}">
                <label class="field">Reason
                    <select name="hold_reason_id" required>
                        @foreach ($holdReasons as $r)<option value="{{ $r->id }}">{{ $r->label }}</option>@endforeach
                    </select>
                </label>
                <label class="field">Note<input type="text" name="note" maxlength="255"></label>
                <button type="submit" class="btn block">Confirm hold</button>
            </form>
        </details>
        <form method="POST" action="{{ route('technician.jobs.punch-out', $serviceRequest) }}">
            @csrf
            <input type="hidden" name="client_event_uuid" class="uuid-field">
            <input type="hidden" name="gps_lat"><input type="hidden" name="gps_lng"><input type="hidden" name="gps_accuracy">
            <button type="submit" class="btn danger block gps-submit">Punch out</button>
        </form>
    @else
        <div style="color:var(--muted);font-size:13px">Sent for review on {{ $serviceRequest->last_punch_out_at?->format('j M, g:ia') }}.</div>
    @endif
</div>

@if ($pendingOverride && $pendingOverride->status === 'pending')
    <div class="card"><span class="chip amber">Geofence override requested — waiting on your manager.</span></div>
@endif

<details class="card">
    <summary style="font-weight:700;font-size:13.5px;cursor:pointer;list-style:none">Request geofence override</summary>
    <form method="POST" action="{{ route('technician.jobs.geofence-override', $serviceRequest) }}" enctype="multipart/form-data" style="margin-top:10px">
        @csrf
        <input type="hidden" name="client_event_uuid" value="{{ Str::uuid() }}">
        <input type="hidden" name="gps_lat"><input type="hidden" name="gps_lng"><input type="hidden" name="gps_accuracy">
        <label class="field">Reason<input type="text" name="reason" required maxlength="255"></label>
        <label class="field">Photo (optional)<input type="file" name="photo" accept="image/*" capture="environment"></label>
        <button type="submit" class="btn secondary block gps-submit">Send request</button>
    </form>
</details>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:4px">
    <a href="{{ route('technician.jobs.photos', $serviceRequest) }}" class="btn secondary">Photos</a>
    <a href="{{ route('technician.jobs.notes', $serviceRequest) }}" class="btn secondary">Notes</a>
    <a href="{{ route('technician.jobs.expenses', $serviceRequest) }}" class="btn secondary">Expenses</a>
    <a href="{{ route('technician.jobs.signature', $serviceRequest) }}" class="btn secondary">Signature</a>
</div>

<script>
document.querySelectorAll('.uuid-field').forEach(el => el.value = crypto.randomUUID());
document.querySelectorAll('.gps-submit').forEach(btn => {
    btn.addEventListener('click', function (e) {
        const form = btn.closest('form');
        if (form.dataset.gpsReady) return;
        e.preventDefault();
        if (! navigator.geolocation) { form.submit(); return; }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                form.querySelector('[name=gps_lat]').value = pos.coords.latitude;
                form.querySelector('[name=gps_lng]').value = pos.coords.longitude;
                form.querySelector('[name=gps_accuracy]').value = Math.round(pos.coords.accuracy);
                form.dataset.gpsReady = '1'; form.submit();
            },
            () => { form.dataset.gpsReady = '1'; form.submit(); },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    });
});
</script>
@endsection