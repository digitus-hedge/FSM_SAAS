@extends('layouts.technician')
@section('title', 'Punched out')

@section('appbar')
<div class="appbar">
  <button type="button" class="back" onclick="history.back()">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  </button>
  <h1>Punched out</h1>
  <span class="sent-chip">All sent</span>
</div>
@endsection

@section('content')
<style>
  .done-wrap{display:flex;flex-direction:column;align-items:center;padding:30px 0 20px}
  .done-tick{width:84px;height:84px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;margin-bottom:18px}
  .done-tick svg{width:40px;height:40px;color:#fff}
  .done-title{font-family:'Figtree';font-size:24px;font-weight:800;color:var(--ink);margin:0 0 24px}

  .sum-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:4px 0;overflow:hidden;margin-bottom:14px}
  .sum-row{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid #F2F1F6;font-size:15px}
  .sum-row:last-child{border-bottom:0}
  .sum-label{font-family:'Figtree';font-weight:800;color:var(--ink)}
  .sum-value{color:var(--ink);font-weight:600}

  .back-actions{position:sticky;bottom:0;background:var(--bg);border-top:1px solid var(--line);padding:12px 16px;margin:16px -16px -20px}
  .back-actions .btn{width:100%;padding:16px;font-size:16px;font-weight:800;border-radius:14px;background:var(--accent);color:#fff;border:0;text-align:center;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
</style>

{{-- ===== BIG GREEN TICK + HEADING ===== --}}
<div class="done-wrap">
  <div class="done-tick">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
  </div>
  <h2 class="done-title">Sent for review.</h2>
</div>

{{-- ===== SUMMARY CARD ===== --}}
<div class="sum-card">
  <div class="sum-row">
    <div class="sum-label">Work time</div>
    <div class="sum-value">{{ $summary['work_minutes'] }} min</div>
  </div>
  <div class="sum-row">
    <div class="sum-label">Time on hold</div>
    <div class="sum-value">{{ $summary['hold_minutes'] }} min</div>
  </div>
  <div class="sum-row">
    <div class="sum-label">Photos</div>
    <div class="sum-value">{{ $summary['photos'] }}</div>
  </div>
  <div class="sum-row">
    <div class="sum-label">Expenses</div>
    <div class="sum-value">{{ $summary['expenses'] }}</div>
  </div>
</div>

{{-- ===== STICKY ACTION ===== --}}
<div class="back-actions">
  <a href="{{ route('technician.jobs.index') }}" class="btn">Back to today</a>
</div>

@endsection