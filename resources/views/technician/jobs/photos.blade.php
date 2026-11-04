@extends('layouts.technician')
@section('title', 'Photos')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')

<form method="POST" action="{{ route('technician.jobs.photos.store', $serviceRequest) }}" enctype="multipart/form-data" class="card" style="padding:16px;margin-bottom:16px">
    @csrf
    <div class="field">
        <label>Category</label>
        <div class="cat-toggle">
            <label><input type="radio" name="category" value="before_photo" checked><span>Before</span></label>
            <label><input type="radio" name="category" value="after_photo"><span>After</span></label>
        </div>
    </div>
    <style>
    .cat-toggle{display:flex;gap:6px;background:var(--soft);padding:4px;border-radius:14px}
    .cat-toggle label{flex:1;position:relative;cursor:pointer;margin:0}
    .cat-toggle input{position:absolute;inset:0;opacity:0;margin:0;cursor:pointer}
    .cat-toggle span{display:block;text-align:center;border-radius:11px;min-height:42px;line-height:42px;font-weight:700;color:var(--muted)}
    .cat-toggle input:checked + span{background:var(--surface);color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.1)}
    </style>
    <div class="field" style="margin-bottom:0">
        <label>Photo</label>
        <label class="inp" style="display:flex;align-items:center;gap:8px;cursor:pointer">
            @include('technician._icon', ['name'=>'camera','size'=>18])
            <span id="photo-file-label" style="flex:1">Choose photo</span>
            <input type="file" name="photo" accept="image/*" capture="environment" required style="display:none" onchange="document.getElementById('photo-file-label').textContent = this.files[0] ? this.files[0].name : 'Choose photo'">
        </label>
    </div>
    <button type="submit" class="btn dark block" style="margin-top:14px">Upload</button>
</form>

@if ($photos->isEmpty())
    <div class="card center" style="padding:36px 20px">
        <div style="width:50px;height:50px;border-radius:999px;background:var(--tint);color:var(--accent);display:grid;place-items:center;margin:0 auto 12px">
            @include('technician._icon', ['name'=>'camera','size'=>22])
        </div>
        <h3 style="margin-bottom:4px;font-size:15px">No photos yet</h3>
        <p class="sub" style="margin:0">Before/after photos you upload will show up here.</p>
    </div>
@else
    <div class="sec">{{ $photos->count() }} photo{{ $photos->count() === 1 ? '' : 's' }}</div>
    <div class="grid3" style="grid-template-columns:repeat(2,1fr)">
        @foreach ($photos as $photo)
            <div class="thumb">
                <img src="{{ $photo->url }}" alt="">
                <span class="chip sm st" style="--c:var({{ $photo->kind === 'before_photo' ? '--blue' : '--green' }})">{{ $photo->kind === 'before_photo' ? 'Before' : 'After' }}</span>
                <form method="POST" action="{{ route('technician.jobs.photos.delete', [$serviceRequest, $photo]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="x" aria-label="Remove photo">@include('technician._icon', ['name'=>'x','size'=>15])</button>
                </form>
            </div>
        @endforeach
    </div>
@endif
@endsection