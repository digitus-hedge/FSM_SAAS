@extends('layouts.technician')
@section('title', $row->asset?->name ?? 'Asset')
@section('back', route('technician.jobs.batch', $serviceRequest))
@section('content')

<form method="POST" action="{{ route('technician.jobs.batch.asset.save', [$serviceRequest, $row]) }}" enctype="multipart/form-data">
    @csrf
    <div class="card" style="padding:14px;margin-bottom:12px">
        <div style="font-weight:700">{{ $row->asset?->name }}</div>
        <div class="sub">{{ $row->asset?->asset_code }}</div>
    </div>

    @if ($version)
        @if ($result)<span class="chip sm" style="--c:var(--{{ $result === 'ok' ? 'green' : 'red' }});margin-bottom:8px">{{ $result === 'ok' ? 'All good' : 'Issues found' }}</span>@endif
        @include('technician.jobs._checklist_form', ['draftKey' => 'checklist-' . $serviceRequest->id . '-' . $row->id])
    @endif

    <div class="sec">Result for this asset</div>
    <div class="card" style="padding:14px;margin-bottom:10px">
        <div class="field">
            <label>Status</label>
            <select name="inspection_status" class="sel" @disabled(!$editable)>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($row->inspection_status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label>Meter reading</label>
            <input type="number" step="any" min="0" name="recorded_meter_reading" class="inp" value="{{ $row->recorded_meter_reading }}" @disabled(!$editable)>
        </div>
        <div class="field">
            <label>Remarks</label>
            <textarea name="technician_remarks" class="txt" rows="2" maxlength="2000" @disabled(!$editable)>{{ $row->technician_remarks }}</textarea>
        </div>
    </div>

    @if ($editable)
        @unless ($version)<input type="hidden" name="client_event_uuid" value="">@endunless
        <button type="submit" class="btn dark block">Save asset</button>
    @endif
</form>
@endsection
