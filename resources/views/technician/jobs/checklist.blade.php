@extends('layouts.technician')
@section('title', $title ?? 'Checklist')
@section('back', $back)
@section('content')

@if ($result)
    <div class="card" style="padding:12px;margin-bottom:12px">
        <span class="chip sm" style="--c:var(--{{ $result === 'ok' ? 'green' : 'red' }})">{{ $result === 'ok' ? 'All good' : 'Issues found' }}</span>
    </div>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @include('technician.jobs._checklist_form', ['draftKey' => 'checklist-' . $serviceRequest->id])
    @if ($editable)
        <button type="submit" class="btn dark block" style="margin-top:8px">Save checklist</button>
    @else
        <div class="sub" style="margin-top:8px">The checklist can be filled in while the job is in progress.</div>
    @endif
</form>
@endsection
