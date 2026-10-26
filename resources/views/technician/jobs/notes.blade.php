@extends('layouts.technician')
@section('title', 'Notes')

@section('appbar')
<div class="appbar">
  <button type="button" class="back" onclick="history.back()"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
  <h1>Notes</h1>
  <span class="sent-chip">All sent</span>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('technician.jobs.notes.store', $job) }}">@csrf
  <label style="display:block;font-weight:800;font-size:14.5px;margin-bottom:8px">Add a note</label>
  <textarea name="body" rows="4" required placeholder="What did you find or do?"
    style="width:100%;font-family:'DM Sans';font-size:14.5px;padding:14px;border-radius:14px;border:1px solid var(--line);background:#fff;outline:none;box-sizing:border-box;margin-bottom:14px"></textarea>
  <button type="submit" class="btn btn-primary">Save note</button>
</form>

<div style="font-size:11px;font-weight:800;color:var(--subtle);letter-spacing:.14em;margin:20px 2px 10px;text-transform:uppercase">On this job</div>

@forelse ($notes as $n)
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:6px">
      <div style="font-weight:800;font-size:13.5px;color:var(--ink)">{{ $n->user?->name ?? 'Technician' }}</div>
      <div style="display:flex;gap:8px;align-items:center">
        <span style="font-size:12px;color:var(--muted)">{{ $n->created_at->format('H:i') }}</span>
        <span class="chip chip-ok">Sent</span>
      </div>
    </div>
    <div style="font-size:14px;color:var(--ink);line-height:1.5;white-space:pre-wrap">{{ $n->body }}</div>
  </div>
@empty
  <div class="card" style="color:var(--muted);font-size:14px">No notes yet.</div>
@endforelse

<div style="font-size:12.5px;color:var(--muted);margin-top:10px">
  Notes cannot be edited or removed. They are never shown to the client.
</div>
@endsection