@extends('layouts.technician')
@section('title', 'Profile')

@section('content')
<div class="card" style="display:flex;align-items:center;gap:16px">
  <div style="width:60px;height:60px;border-radius:50%;background:var(--soft);color:var(--ink);display:flex;align-items:center;justify-content:center;font-family:'Figtree';font-size:20px;font-weight:800;flex-shrink:0">
    {{ strtoupper(substr($u->name, 0, 2)) }}
  </div>
  <div>
    <div style="font-family:'Figtree';font-size:20px;font-weight:800">{{ $u->name }}</div>
    <div style="font-size:13.5px;color:var(--muted);margin-top:2px">{{ $u->role?->name ?? 'Field Technician' }}@if ($u->tenant), {{ $u->tenant->name }}@endif</div>
  </div>
</div>

<div class="card" style="padding:0">
  <div style="padding:14px 16px;border-bottom:1px solid var(--line)">
    <div style="font-family:'Figtree';font-size:14.5px;font-weight:700">Signed in on this phone until {{ now()->addDays(30)->format('j M') }}</div>
    <div style="font-size:12.5px;color:var(--muted);margin-top:3px">A device stays trusted for 30 days.</div>
  </div>
  <div style="padding:14px 16px;border-bottom:1px solid var(--line)">
    <div style="font-family:'Figtree';font-size:14.5px;font-weight:700">Not installed</div>
    <div style="font-size:12.5px;color:var(--muted);margin-top:3px">Android, running in the browser</div>
  </div>
  <div style="padding:14px 16px">
    <div style="font-family:'Figtree';font-size:14.5px;font-weight:700">Location</div>
    <div style="font-size:12.5px;color:var(--muted);margin-top:3px">Saved at punches and while the app is open. Kept 30 days.</div>
  </div>
</div>

<form method="POST" action="{{ route('logout') }}">
  @csrf
  <button type="submit" class="btn btn-danger-out">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Sign out
  </button>
</form>
@endsection