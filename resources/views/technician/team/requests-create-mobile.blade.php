@extends('layouts.technician')
@section('title', 'New request')
@section('content')

<div class="sec">Raise a request</div>

<form method="POST" action="{{ route('technician.team.requests.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="card" style="padding:14px;margin-bottom:10px">
        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Client</label>
        <select name="client_id" id="client_id" class="sel" required style="margin-bottom:14px">
            <option value="">Choose a client</option>
            @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Site</label>
        <select name="project_site_id" id="project_site_id" class="sel" required disabled style="margin-bottom:14px">
            <option value="">Choose a client first</option>
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Category</label>
        <select name="service_category_id" class="sel" required style="margin-bottom:14px">
            <option value="">Choose a category</option>
            @foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Priority</label>
        <select name="priority" class="sel" required style="margin-bottom:14px">
            @foreach (\App\Models\ServiceRequest::PRIORITIES as $value => $label)
                <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
            @endforeach
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Title</label>
        <input type="text" name="title" class="sel" maxlength="190" required style="margin-bottom:14px" placeholder="e.g. AC not cooling">

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Description (optional)</label>
        <textarea name="description" class="sel" maxlength="2000" rows="4" style="margin-bottom:14px;resize:vertical"></textarea>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">PO reference (optional)</label>
        <input type="text" name="client_po_reference" class="sel" maxlength="100" style="margin-bottom:14px">

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Attachments (optional)</label>
        <input type="file" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx">
    </div>

    <button type="submit" class="btn dark block">Create request</button>
</form>

<script>
document.getElementById('client_id')?.addEventListener('change', function () {
    const siteSel = document.getElementById('project_site_id');
    const clientId = this.value;
    if (! clientId) { siteSel.innerHTML = '<option value="">Choose a client first</option>'; siteSel.disabled = true; return; }
    siteSel.innerHTML = '<option value="">Loading…</option>';
    siteSel.disabled = true;
    fetch("{{ route('technician.team.sites-for-client') }}?client_id=" + encodeURIComponent(clientId))
        .then(r => r.json())
        .then(sites => {
            if (! sites.length) { siteSel.innerHTML = '<option value="">No sites for this client</option>'; return; }
            siteSel.innerHTML = '<option value="">Choose a site</option>' + sites.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
            siteSel.disabled = false;
        })
        .catch(() => { siteSel.innerHTML = '<option value="">Could not load sites</option>'; });
});
</script>
@endsection