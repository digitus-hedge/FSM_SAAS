@extends('layouts.technician')
@section('title', 'Notifications')

@section('content')
<div class="card" style="background:var(--accent-soft);border-color:#D4DBFC;color:var(--accent-ink);font-size:13.5px;display:flex;gap:10px;align-items:flex-start">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <div>Preview. The notifications inbox is built in the Notifications module. It is shown here so you can see how it connects to the Field View.</div>
</div>

@foreach ($notifications as $n)
  <div class="card" style="display:flex;gap:12px;align-items:flex-start">
    <div style="width:38px;height:38px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;{{ $n['type'] === 'emergency' ? 'background:var(--accent);color:#fff' : 'background:var(--soft);color:var(--muted)' }}">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-family:'Figtree';font-size:15px;font-weight:700;color:var(--ink);line-height:1.3">{{ $n['title'] }}</div>
      <div style="font-size:13.5px;color:var(--muted);margin-top:3px;line-height:1.4">{{ $n['body'] }}</div>
    </div>
    <div style="font-size:12px;color:var(--muted);white-space:nowrap">{{ $n['time']->diffForHumans(null, true) }} ago</div>
  </div>
@endforeach

<form method="POST" action="#" style="margin-top:8px">@csrf
  <button type="button" class="btn btn-ghost">Mark all as read</button>
</form>
@endsection