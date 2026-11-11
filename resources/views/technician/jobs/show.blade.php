@extends('layouts.technician')
@section('title', $serviceRequest->sr_number)
@section('back', route('technician.jobs.index'))
@section('content')
@php
    $stateColorMap = ['assigned'=>'blue','in_progress'=>'accent','rework'=>'amber','pending_review'=>'violet','completed'=>'green','cancelled'=>'slate'];
    $stateColor   = $stateColorMap[$serviceRequest->status] ?? 'slate';
    $canAccept    = ! $serviceRequest->tech_accepted_at;
    $isDone       = in_array($serviceRequest->status, ['pending_review', 'completed', 'cancelled']);
    $isActive     = $serviceRequest->status === 'in_progress';
    $notStarted   = ! $isActive && ! $isDone;
    $showOverride = $serviceRequest->enforce_geofence || ($pendingOverride && $pendingOverride->status === 'pending');
@endphp

<div class="card" style="background:var(--ink);color:#fff;border:0;padding:20px;margin-bottom:14px">
    <div style="opacity:.6;font-size:12.5px;font-weight:700;letter-spacing:.03em">{{ $serviceRequest->sr_number }}</div>
    <h2 style="font-size:19px;margin:6px 0 4px;color:#fff">{{ $serviceRequest->title }}</h2>
    <div style="opacity:.72;font-size:13.5px">{{ $serviceRequest->client?->name }} &middot; {{ $serviceRequest->site?->name }}</div>

    <div style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap">
        <span class="chip sm" style="--c:var(--{{ $serviceRequest->priorityColor() }})">{{ $serviceRequest->priorityLabel() }}</span>
        <span class="chip sm" style="--c:var(--{{ $serviceRequest->is_on_hold ? 'amber' : $stateColor }})">{{ $serviceRequest->is_on_hold ? 'On hold' : $serviceRequest->statusLabel() }}</span>
        @if ($serviceRequest->enforce_geofence)<span class="chip sm" style="--c:var(--blue)">Geofence</span>@endif
    </div>

    <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.14);display:flex;align-items:center;gap:7px;font-size:13.5px;opacity:.85">
        @include('technician._icon', ['name'=>'clock','size'=>15])
        {{ $serviceRequest->scheduled_eta_at?->format('D, j M \a\t g:ia') ?? 'Not scheduled' }}
    </div>

    @if ($serviceRequest->description)
        <p style="margin:12px 0 0;font-size:13.5px;opacity:.85;line-height:1.55">{{ $serviceRequest->description }}</p>
    @endif
</div>

@if ($canAccept)
<div class="card" style="border-color:color-mix(in srgb,var(--accent) 45%,var(--line));background:var(--tint);margin-bottom:14px">
    <div style="font-weight:700">Accept this job</div>
    <div class="sub" style="margin-top:2px">Confirm you're taking this job before you start work on site.</div>
    <form method="POST" action="{{ route('technician.jobs.accept', $serviceRequest) }}" style="margin-top:12px">
        @csrf
        <button type="submit" class="btn primary block">Accept job</button>
    </form>
</div>
@endif

@if ($isDone)
<div class="card" style="margin-bottom:14px">
    <div style="display:flex;align-items:center;gap:12px">
        <span class="tick {{ $serviceRequest->status === 'completed' ? 'ok' : ($serviceRequest->status === 'cancelled' ? 'no' : '') }}">
            @include('technician._icon', ['name'=>'check','size'=>15])
        </span>
        <div>
            <div style="font-weight:700;font-size:14px">
                @if ($serviceRequest->status === 'pending_review') Sent for review
                @elseif ($serviceRequest->status === 'completed') Completed
                @else Cancelled
                @endif
            </div>
            @if ($serviceRequest->last_punch_out_at)
                <div class="sub">{{ $serviceRequest->last_punch_out_at->format('j M, g:ia') }}</div>
            @endif
        </div>
    </div>
</div>
@endif

@if ($pendingOverride && $pendingOverride->status === 'pending')
<div class="card" style="margin-bottom:14px">
    <div style="display:flex;align-items:center;gap:10px">
        <span class="chip sm" style="--c:var(--amber)">Pending</span>
        <div class="sub" style="flex:1">Geofence override requested &mdash; waiting on your manager.</div>
    </div>
</div>
@endif
@unless ($isDone)
<details class="card acc" style="margin-bottom:10px;padding:16px">
    <summary style="display:flex;align-items:center;gap:12px;cursor:pointer">
        <span style="width:36px;height:36px;border-radius:11px;background:var(--tint);color:var(--accent);display:grid;place-items:center;flex:0 0 auto">
            @include('technician._icon', ['name'=>'clock','size'=>17])
        </span>
        <span style="flex:1;font-weight:700;font-size:14.5px">Reschedule</span>
        <span class="acc-chev">@include('technician._icon', ['name'=>'right','size'=>16])</span>
    </summary>
    <form method="POST" action="{{ route('technician.jobs.change-time', $serviceRequest) }}" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line)">
        @csrf
        <div class="field">
            <label>New time</label>
            <input type="datetime-local" name="new_eta_at" class="inp" required>
        </div>
        <div class="field">
            <label>Reason</label>
            <input type="text" name="reason" class="inp" maxlength="255" placeholder="Optional">
        </div>
        <button type="submit" class="btn dark block">Update time</button>
    </form>
</details>
@endunless

@if ($showOverride)
<details class="card acc" style="margin-bottom:10px;padding:16px">
    <summary style="display:flex;align-items:center;gap:12px;cursor:pointer">
        <span style="width:36px;height:36px;border-radius:11px;background:var(--tint);color:var(--accent);display:grid;place-items:center;flex:0 0 auto">
            @include('technician._icon', ['name'=>'send','size'=>16])
        </span>
        <span style="flex:1;font-weight:700;font-size:14.5px">Request geofence override</span>
        <span class="acc-chev">@include('technician._icon', ['name'=>'right','size'=>16])</span>
    </summary>
    <form method="POST" action="{{ route('technician.jobs.geofence-override', $serviceRequest) }}" enctype="multipart/form-data" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line)">
    @csrf
    <input type="hidden" name="client_event_uuid" class="uuid-field">
    @php $lastGps = session('last_gps'); @endphp
    <input type="hidden" name="gps_lat" value="{{ $lastGps['lat'] ?? '' }}">
    <input type="hidden" name="gps_lng" value="{{ $lastGps['lng'] ?? '' }}">
    <input type="hidden" name="gps_accuracy" value="{{ $lastGps['accuracy'] ?? '' }}">
        <div class="field">
            <label>Reason</label>
            <input type="text" name="reason" class="inp" required maxlength="255">
        </div>
        <div class="field">
            <label>Photo (optional)</label>
            <input type="file" name="photo" class="inp" style="padding:10px 14px" accept="image/*" capture="environment">
        </div>
        <button type="submit" class="btn block gps-submit">Send request</button>
    </form>
</details>
@endif

<div class="sec">Job tools</div>
<div class="grid3" style="grid-template-columns:repeat(2,1fr);margin-bottom:16px">
    <a href="{{ route('technician.jobs.photos', $serviceRequest) }}" class="card tap tile">
        <span class="tile-ic">@include('technician._icon', ['name'=>'camera','size'=>21])</span>
        <span class="tile-lb">Photos</span>
    </a>
    <a href="{{ route('technician.jobs.notes', $serviceRequest) }}" class="card tap tile">
        <span class="tile-ic">@include('technician._icon', ['name'=>'doc','size'=>21])</span>
        <span class="tile-lb">Notes</span>
    </a>
    <a href="{{ route('technician.jobs.expenses', $serviceRequest) }}" class="card tap tile">
        <span class="tile-ic">@include('technician._icon', ['name'=>'wallet','size'=>21])</span>
        <span class="tile-lb">Expenses</span>
    </a>
    <a href="{{ route('technician.jobs.signature', $serviceRequest) }}" class="card tap tile">
        <span class="tile-ic">@include('technician._icon', ['name'=>'pen','size'=>21])</span>
        <span class="tile-lb">Signature</span>
    </a>
</div>

@if ($isActive && \App\Models\Tenant::current()?->hasFeature('assets_full'))
<a href="{{ route('technician.scan.index') }}" class="card tap" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;margin-bottom:16px">
    <span class="tile-ic">@include('technician._icon', ['name'=>'camera','size'=>21])</span>
    <span style="flex:1;font-weight:700">Scan asset</span>
    @include('technician._icon', ['name'=>'right','size'=>18])
</a>
@endif

@unless ($isDone)
<input type="checkbox" id="hold-toggle" style="display:none">
<div class="sheetwrap" id="hold-sheet-wrap" style="display:none;align-items:flex-end;justify-content:center">
    <div class="sheet">
        <div class="grab"></div>
        <h2>Put job on hold</h2>
        <p class="sub" style="margin:0 0 16px">The clock keeps the time already logged and stops counting until you resume.</p>
        <form method="POST" action="{{ route('technician.jobs.hold', $serviceRequest) }}">
            @csrf
            <input type="hidden" name="client_event_uuid" class="uuid-field">
            <div class="field">
                <label>Reason</label>
                <select name="hold_reason_id" class="sel" required>
                    <option value="" disabled selected>Choose a reason</option>
                    @foreach ($holdReasons as $r)<option value="{{ $r->id }}">{{ $r->label }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>Note</label>
                <input type="text" name="note" class="inp" maxlength="255" placeholder="Optional">
            </div>
            <div class="acts">
                <button type="button" class="btn" id="hold-cancel-btn">Cancel</button>
                <button type="submit" class="btn dark">Confirm hold</button>
            </div>
        </form>
    </div>
</div>

<div class="card" style="padding:14px">
    @if ($canAccept)
        <div class="sub" style="text-align:center">Accept the job above to start work</div>
    @elseif ($notStarted)
        <form method="POST" action="{{ route('technician.jobs.punch-in', $serviceRequest) }}">
            @csrf
            <input type="hidden" name="client_event_uuid" class="uuid-field">
            <input type="hidden" name="gps_lat"><input type="hidden" name="gps_lng"><input type="hidden" name="gps_accuracy">
            <button type="submit" class="btn dark block gps-submit">Punch in</button>
        </form>
    @elseif ($isActive && $serviceRequest->is_on_hold)
        <form method="POST" action="{{ route('technician.jobs.resume', $serviceRequest) }}">
            @csrf
            <input type="hidden" name="client_event_uuid" class="uuid-field">
            <button type="submit" class="btn dark block">Resume job</button>
        </form>
    @elseif ($isActive)
        <div style="display:flex;gap:8px">
            <label for="hold-toggle" class="btn" style="cursor:pointer">Put on hold</label>
            @if (!empty($missingProof))
                <div style="flex:1">
                    <button type="button" class="btn danger block" disabled style="opacity:.5;cursor:not-allowed">Punch out</button>
                    <div style="margin-top:6px;font-size:12px;color:var(--red)">Add {{ implode(' and ', $missingProof) }} to punch out.</div>
                </div>
            @else
                <form method="POST" action="{{ route('technician.jobs.punch-out', $serviceRequest) }}" style="flex:1">
                    @csrf
                    <input type="hidden" name="client_event_uuid" class="uuid-field">
                    @php $lastGps = session('last_gps'); @endphp
                        <input type="hidden" name="gps_lat" value="{{ $lastGps['lat'] ?? '' }}">
                        <input type="hidden" name="gps_lng" value="{{ $lastGps['lng'] ?? '' }}">
                        <input type="hidden" name="gps_accuracy" value="{{ $lastGps['accuracy'] ?? '' }}">
                    <button type="submit" class="btn danger block gps-submit">Punch out</button>
                </form>
            @endif
        </div>
    @endif
</div>
@endunless

<script>
document.addEventListener('DOMContentLoaded', function () {
    function makeUuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }
    document.querySelectorAll('.uuid-field').forEach(el => el.value = makeUuid());
    document.querySelectorAll('.gps-submit').forEach(btn => {
        btn.addEventListener('click', function (e) {
            const form = btn.closest('form');
            if (form.dataset.gpsReady) return;
            e.preventDefault();

            btn.disabled = true;
            btn.dataset.originalText = btn.textContent;
            btn.textContent = 'Getting location…';

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
    const holdToggle = document.getElementById('hold-toggle');
    const holdWrap = document.getElementById('hold-sheet-wrap');
    const cancelBtn = document.getElementById('hold-cancel-btn');
    if (! holdToggle || ! holdWrap) return;
    holdToggle.addEventListener('change', () => { holdWrap.style.display = holdToggle.checked ? 'flex' : 'none'; });
    holdWrap.addEventListener('click', (e) => { if (e.target === holdWrap) { holdToggle.checked = false; holdWrap.style.display = 'none'; } });
    if (cancelBtn) cancelBtn.addEventListener('click', () => { holdToggle.checked = false; holdWrap.style.display = 'none'; });
});
</script>
@endsection