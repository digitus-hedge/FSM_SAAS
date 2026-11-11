@extends('layouts.technician')
@section('title', 'New request')
@section('content')

<a href="{{ route('technician.scan.index') }}" class="sub" style="display:inline-block;margin-bottom:12px;text-decoration:none">&larr; Scan another</a>

<div class="card" style="padding:14px;margin-bottom:12px;background:var(--tint)">
    <div style="font-weight:700">{{ $asset->asset_code }} &middot; {{ $asset->name }}</div>
    <div class="sub" style="margin-top:2px">{{ $asset->client?->name }} &middot; {{ $asset->site?->name }}</div>
    <div class="sub" style="margin-top:2px">Raised while punched in on {{ $job->sr_number }}</div>
</div>

<form method="POST" action="{{ route('technician.scan.store', $asset) }}" enctype="multipart/form-data" id="scan-req-form">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="card" style="padding:14px;margin-bottom:10px">
        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Category</label>
        <select name="service_category_id" class="sel" required style="margin-bottom:14px">
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" @selected((int) old('service_category_id', $asset->service_category_id) === (int) $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Priority</label>
        <select name="priority" class="sel" required style="margin-bottom:14px">
            @foreach (\App\Models\ServiceRequest::PRIORITIES as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Title</label>
        <input type="text" name="title" value="{{ old('title') }}" class="sel" maxlength="190" required style="margin-bottom:14px" placeholder="e.g. Motor overheating">

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Description (optional)</label>
        <textarea name="description" class="sel" maxlength="2000" rows="4" style="margin-bottom:14px;resize:vertical">{{ old('description') }}</textarea>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Photos (optional)</label>
        <input type="file" name="attachments[]" multiple accept="image/*" capture="environment">
    </div>

    <button type="submit" class="btn dark block" id="scan-req-btn">Send request</button>
</form>

<script>
document.getElementById('scan-req-form').addEventListener('submit', function () {
    var b = document.getElementById('scan-req-btn'); b.disabled = true; b.textContent = 'Sending…';
});
</script>
@endsection