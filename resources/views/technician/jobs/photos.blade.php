@extends('layouts.technician')
@section('title', 'Photos')

@section('appbar')
<div class="appbar">
  <button type="button" class="back" onclick="history.back()"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
  <h1>Photos</h1>
  <span class="sent-chip">All sent</span>
</div>
@endsection

@section('content')
<style>
  .tabs2{display:flex;background:var(--soft);border-radius:12px;padding:4px;margin-bottom:14px}
  .tabs2 a{flex:1;padding:11px;text-align:center;border-radius:9px;font-size:14px;font-weight:800;color:var(--muted);text-decoration:none}
  .tabs2 a.on{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.06)}
  .photo-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px}
  .photo-tile{aspect-ratio:1;border-radius:12px;overflow:hidden;position:relative;background:var(--soft)}
  .photo-tile img{width:100%;height:100%;object-fit:cover;display:block}
  .photo-tile .sent-tag{position:absolute;bottom:6px;left:6px;background:var(--green-soft);color:var(--green);font-size:10px;font-weight:800;padding:3px 8px;border-radius:999px}
  .add-tile{aspect-ratio:1;border:1.5px dashed #CFCDD9;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;color:var(--muted);cursor:pointer;background:transparent;font-family:inherit;margin:0}
  .add-tile:hover{border-color:var(--accent);color:var(--accent)}
</style>

<div class="tabs2">
  <a href="?kind=before" class="{{ $kind === 'before' ? 'on' : '' }}">Before ({{ $counts['before'] }})</a>
  <a href="?kind=after"  class="{{ $kind === 'after'  ? 'on' : '' }}">After ({{ $counts['after'] }})</a>
</div>

<div style="color:var(--muted);font-size:13px;margin-bottom:10px">
  {{ $photos->count() }} of 10. Each photo is shrunk to a long edge of 1600 pixels.
</div>

<div class="photo-grid">
  @foreach ($photos as $p)
    <form method="POST" action="{{ route('technician.jobs.photos.replace', ['job' => $job, 'photo' => $p->id]) }}"
          enctype="multipart/form-data" id="replace-form-{{ $p->id }}" style="margin:0">
      @csrf
      <label class="photo-tile" style="display:block;cursor:pointer">
        <img src="{{ asset('storage/'.$p->storage_path) }}?v={{ $p->updated_at->timestamp }}" alt="">
        <span class="sent-tag">Sent</span>
        <span style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.65);color:#fff;font-size:10px;font-weight:800;padding:4px 8px;border-radius:999px;display:inline-flex;align-items:center;gap:4px">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Update
        </span>
        <input type="file" name="photo" accept="image/*" capture="environment"
               style="display:none"
               onchange="if(this.files.length){document.getElementById('replace-form-{{ $p->id }}').submit();}">
      </label>
    </form>
  @endforeach

  @if ($photos->count() < 10)
    <form method="POST" action="{{ route('technician.jobs.photos.store', $job) }}" enctype="multipart/form-data" id="photo-form" style="margin:0">
      @csrf
      <input type="hidden" name="kind" value="{{ $kind }}">
      <label class="add-tile">
        <input type="file" name="photo" accept="image/*" capture="environment" required
               style="display:none"
               onchange="if(this.files.length){document.getElementById('photo-form').submit();}">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <span style="font-size:12.5px;font-weight:700">Add photo</span>
      </label>
    </form>
  @endif
</div>

@if ($photos->count())
  @php $last = $photos->last(); @endphp
  <div style="color:var(--muted);font-size:12.5px">
    Last photo about {{ round($last->size_bytes / 1024) }} KB after shrinking.
  </div>
@endif
@endsection