@extends('layouts.technician')
@section('title', 'Expenses')

@section('appbar')
<div class="appbar">
  <button type="button" class="back" onclick="history.back()"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
  <h1>Expenses</h1>
  <span class="sent-chip">All sent</span>
</div>
@endsection

@section('content')
@forelse ($expenses as $e)
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
      <div style="flex:1;min-width:0">
        <div style="font-family:'Figtree';font-weight:800;font-size:15px;color:var(--ink)">{{ $e->category?->name ?? 'Expense' }}</div>
        <div style="font-size:12.5px;color:var(--muted);margin-top:4px">
          {{ $e->description ?: '—' }} · {{ rtrim(rtrim(number_format($e->quantity, 2), '0'), '.') }} × {{ number_format($e->unit_rate, 2) }} AED
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-family:'Figtree';font-weight:800;font-size:15px">{{ number_format($e->total_amount, 2) }} AED</div>
        <span class="chip chip-ok" style="margin-top:4px">Sent</span>
      </div>
    </div>

    @if ($e->receipt)
      <a href="{{ asset('storage/'.$e->receipt->storage_path) }}" target="_blank"
         style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;font-size:12.5px;color:var(--accent);text-decoration:none;font-weight:700">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        View receipt
      </a>
    @endif
  </div>
@empty
  <div class="card" style="color:var(--muted);text-align:center">No expenses on this job yet.</div>
@endforelse

<a href="{{ route('technician.jobs.expenses.create', $job) }}" class="btn btn-primary" style="margin-top:4px">+ Add expense</a>
<div style="font-size:12.5px;color:var(--muted);margin-top:10px">You see only the expenses you logged. The office decides what is billed.</div>
@endsection