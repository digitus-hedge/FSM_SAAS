@extends('layouts.technician')
@section('title', 'Expenses')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')
@php
    $total = $expenses->sum('total_amount');
    $pendingCount = $expenses->where('approval_status', 'pending')->count();
@endphp

@if ($expenses->isNotEmpty())
<div style="background:var(--soft);border-radius:14px;padding:14px 16px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center">
    <div>
        <div class="muted" style="font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em">Total logged</div>
        <div style="font-family:var(--font-display);font-weight:700;font-size:22px;margin-top:2px">{{ number_format($total, 2) }}</div>
    </div>
    @if ($pendingCount)
        <span class="chip sm" style="--c:var(--amber)">{{ $pendingCount }} pending</span>
    @endif
</div>
@endif

<a href="{{ route('technician.jobs.expenses.create', $serviceRequest) }}" class="btn dark block" style="margin-bottom:14px">Log expense</a>

@if ($expenses->isEmpty())
    <div class="card center" style="padding:40px 20px;margin-top:8px">
        <div style="width:54px;height:54px;border-radius:999px;background:var(--tint);color:var(--accent);display:grid;place-items:center;margin:0 auto 14px">
            @include('technician._icon', ['name'=>'wallet','size'=>24])
        </div>
        <h3 style="margin-bottom:6px">No expenses yet</h3>
        <p class="sub" style="margin:0">Expenses you log for this job will show up here.</p>
    </div>
@else
    @foreach ($expenses as $expense)
        @php
            $sColor = match ($expense->approval_status) {
                'approved' => 'green',
                'rejected' => 'red',
                default => 'amber',
            };
        @endphp
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
                <div style="font-weight:700;font-size:14.5px">{{ $expense->category?->name }}</div>
                <div style="font-weight:700;font-size:15px;font-family:var(--font-display)">{{ number_format($expense->total_amount, 2) }}</div>
            </div>
            @if ($expense->description)
                <div class="sub" style="margin-top:3px">{{ $expense->description }}</div>
            @endif
            <div class="r3" style="margin-top:9px">
                <span class="chip sm" style="--c:var(--{{ $sColor }})">{{ ucfirst($expense->approval_status) }}</span>
                <span class="muted" style="font-size:12.5px">{{ $expense->quantity }} &times; {{ number_format($expense->unit_rate, 2) }}</span>
            </div>
        </div>
    @endforeach
@endif
@endsection