@extends('layouts.technician')
@section('title', 'Assets')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')

<div class="card" style="padding:14px;margin-bottom:12px">
    <div style="font-weight:700">{{ $done }} of {{ $rows->count() }} done</div>
    <div class="sub">{{ $line }}</div>
</div>

@foreach ($rows as $row)
    <a href="{{ route('technician.jobs.batch.asset', [$serviceRequest, $row]) }}" class="card tap" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;margin-bottom:8px">
        <div style="flex:1;min-width:0">
            <div style="font-weight:700">{{ $row->asset?->name ?? 'Asset' }}</div>
            <div class="sub">{{ $row->asset?->asset_code }}</div>
        </div>
        <span class="chip sm" style="--c:var(--{{ $row->inspection_status === 'pending' ? 'slate' : ($row->inspection_status === 'failed_unsafe' ? 'red' : 'green') }})">{{ str_replace('_', ' ', $row->inspection_status) }}</span>
        @if ($row->checklist_result)<span class="chip sm" style="--c:var(--{{ $row->checklist_result === 'ok' ? 'green' : 'red' }})">{{ $row->checklist_result }}</span>@endif
    </a>
@endforeach
@endsection
