@extends('layouts.technician')
@section('title', 'Punch in')

@section('appbar')
<div class="appbar">
  <button type="button" class="back" onclick="history.back()"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
  <h1>Punch in</h1>
  <span class="sent-chip">All sent</span>
</div>
@endsection

@section('content')
<div style="text-align:center;padding:40px 20px 60px">
  <div style="width:72px;height:72px;border-radius:50%;background:var(--green);color:#fff;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px">
    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <div style="font-family:'Figtree';font-size:22px;font-weight:800;margin-bottom:6px">You are punched in.</div>
  <div style="color:var(--muted);font-size:14px" id="loc-text">Finding your location…</div>
</div>

<a href="{{ route('technician.jobs.show', $job) }}" class="btn btn-primary" style="margin-top:auto">Continue to the job</a>

<script>
if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(
    p => document.getElementById('loc-text').textContent = `Location found. Accuracy about ${Math.round(p.coords.accuracy)} metres.`,
    () => document.getElementById('loc-text').textContent = 'Location not available.',
    { timeout: 6000 }
  );
}
</script>
@endsection