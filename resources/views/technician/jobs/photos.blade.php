@extends('layouts.technician')
@section('title', 'Photos')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')
<form method="POST" action="{{ route('technician.jobs.photos.store', $serviceRequest) }}" enctype="multipart/form-data" class="card">
    @csrf
    <label class="field">Category
        <select name="category" required>
            <option value="before_photo">Before</option>
            <option value="after_photo">After</option>
        </select>
    </label>
    <label class="field">Photo<input type="file" name="photo" accept="image/*" capture="environment" required></label>
    <button type="submit" class="btn block">Upload</button>
</form>
@if ($photos->isEmpty())
    <div class="empty">No photos yet.</div>
@else
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    @foreach ($photos as $photo)
        <div class="card" style="padding:8px">
            <img src="{{ $photo->url }}" style="width:100%;border-radius:8px;display:block">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
                <span class="chip">{{ $photo->kind === 'before_photo' ? 'Before' : 'After' }}</span>
                <form method="POST" action="{{ route('technician.jobs.photos.delete', [$serviceRequest, $photo]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn danger" style="padding:4px 10px;font-size:12px">Remove</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection