@extends('layouts.technician')
@section('title', 'Photos')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')
<form method="POST" action="{{ route('technician.jobs.photos.store', $serviceRequest) }}" enctype="multipart/form-data" class="card">
    @csrf
    <div class="field">
        <label>Category</label>
        <select name="category" class="sel" required>
            <option value="before_photo">Before</option>
            <option value="after_photo">After</option>
        </select>
    </div>
    <div class="field">
        <label>Photo</label>
        <label class="addph" style="aspect-ratio:auto;min-height:50px;flex-direction:row;gap:8px">
            <span id="photo-file-label">Choose photo</span>
            <input type="file" name="photo" accept="image/*" capture="environment" required style="display:none" onchange="document.getElementById('photo-file-label').textContent = this.files[0] ? this.files[0].name : 'Choose photo'">
        </label>
    </div>
    <button type="submit" class="btn block">Upload</button>
</form>
@if ($photos->isEmpty())
    <div class="card muted" style="text-align:center;padding:24px 14px">No photos yet.</div>
@else
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    @foreach ($photos as $photo)
        <div class="card" style="padding:8px">
            <img src="{{ $photo->url }}" style="width:100%;border-radius:12px;display:block;aspect-ratio:1;object-fit:cover">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
                <span class="chip">{{ $photo->kind === 'before_photo' ? 'Before' : 'After' }}</span>
                <form method="POST" action="{{ route('technician.jobs.photos.delete', [$serviceRequest, $photo]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn danger sm">Remove</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection