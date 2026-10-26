@extends('layouts.technician')
@section('title', 'Sync')

@section('content')
<div class="card" style="display:flex;align-items:center;gap:12px">
  <div style="flex:1">
    <div style="font-family:'Figtree';font-size:20px;font-weight:800">All sent</div>
    <div style="font-size:13px;color:var(--muted);margin-top:3px">Last sent {{ \Carbon\Carbon::parse($lastSent)->format('H:i') }}</div>
  </div>
  <button type="button" class="btn btn-primary" style="width:auto;padding:10px 16px;opacity:.5;cursor:default">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    Send now
  </button>
</div>

<div style="font-size:11.5px;font-weight:800;color:var(--subtle);letter-spacing:.1em;margin:14px 2px 8px;text-transform:uppercase">Waiting</div>
<div class="card" style="color:var(--muted);font-size:14px">Nothing is waiting.</div>

<div style="font-size:11.5px;font-weight:800;color:var(--subtle);letter-spacing:.1em;margin:14px 2px 8px;text-transform:uppercase">Conflicts</div>
<div class="card" style="color:var(--muted);font-size:14px">No conflicts.</div>

<div style="font-size:11.5px;font-weight:800;color:var(--subtle);letter-spacing:.1em;margin:14px 2px 8px;text-transform:uppercase">Storage</div>
<div class="card">
  <div style="height:8px;background:var(--soft);border-radius:999px;overflow:hidden;margin-bottom:8px"><div style="width:28%;background:var(--accent);height:100%;border-radius:999px"></div></div>
  <div style="font-size:13px;color:var(--muted)">2400 MB free on this phone</div>
</div>

<div style="font-size:11.5px;font-weight:800;color:var(--subtle);letter-spacing:.1em;margin:14px 2px 8px;text-transform:uppercase">This phone</div>
<div class="card">
  <div style="font-size:14px;color:var(--ink);margin-bottom:14px">Signed in until <strong>{{ now()->addDays(30)->format('j M') }}</strong></div>
  <form method="POST" action="{{ route('logout') }}">@csrf
    <button type="submit" class="btn btn-danger-out">Sign out</button>
  </form>
</div>
@endsection