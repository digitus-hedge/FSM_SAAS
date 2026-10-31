@extends('layouts.technician')
@section('title', 'Expenses')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')
<a href="{{ route('technician.jobs.expenses.create', $serviceRequest) }}" class="btn block" style="margin-bottom:14px">Log expense</a>
@if ($expenses->isEmpty())
    <div class="empty">No expenses logged.</div>
@else
    @foreach ($expenses as $expense)
        <div class="card">
            <div style="display:flex;justify-content:space-between">
                <div style="font-weight:700;font-size:13.5px">{{ $expense->category?->name }}</div>
                <div style="font-weight:700;font-size:13.5px">{{ number_format($expense->total_amount, 2) }}</div>
            </div>
            <div style="color:var(--muted);font-size:12.5px;margin-top:3px">{{ $expense->description }} · {{ $expense->quantity }} × {{ number_format($expense->unit_rate, 2) }}</div>
            <span class="chip {{ $expense->approval_status === 'approved' ? 'green' : ($expense->approval_status === 'rejected' ? 'red' : 'amber') }}" style="margin-top:8px">{{ ucfirst($expense->approval_status) }}</span>
        </div>
    @endforeach
@endif
@endsection