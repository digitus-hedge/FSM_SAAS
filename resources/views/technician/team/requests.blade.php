@extends('layouts.technician-web')
@section('title', 'New request')
@section('crumb', 'Requests')
@section('content')

<style>
.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media (max-width:700px){.field-grid{grid-template-columns:1fr}}
.field-group{display:flex;flex-direction:column}
.field-lbl{display:block;font-size:12.5px;font-weight:700;margin-bottom:7px;color:var(--ink)}
.field-lbl .req{color:var(--red);margin-left:2px}
.frm-select,.frm-input,.frm-area{width:100%;font-family:inherit;font-size:13.5px;padding:10px 13px;border-radius:10px;border:1px solid var(--line);background:var(--surface);color:var(--ink);outline:none}
.frm-select:focus,.frm-input:focus,.frm-area:focus{border-color:var(--accent)}
.frm-area{min-height:100px;resize:vertical}
</style>

<div class="pagehead">
    <div>
        <h1>New request</h1>
        <p>Raised by you — it lands in your own Requests queue to approve or assign.</p>
    </div>
</div>

<div class="card">
    <form method="POST" action="{{ route('technician.team.desktop.requests.store') }}">
        @csrf
        <div class="field-grid">
            <div class="field-group">
                <label class="field-lbl">Client <span class="req">*</span></label>
                <select name="client_id" id="client_id" class="frm-select" required>
                    <option value="">Select client</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="field-group">
                <label class="field-lbl">Site <span class="req">*</span></label>
                <select name="project_site_id" id="project_site_id" class="frm-select" required disabled>
                    <option value="">Select client first</option>
                </select>
            </div>
            <div class="field-group">
                <label class="field-lbl">Category <span class="req">*</span></label>
                <select name="service_category_id" class="frm-select" required>
                    <option value="">Select category</option>
                    @foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                </select>
            </div>
            <div class="field-group">
                <label class="field-lbl">Priority <span class="req">*</span></label>
                <select name="priority" class="frm-select" required>
                    @foreach (\App\Models\ServiceRequest::PRIORITIES as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field-group" style="margin-top:16px">
            <label class="field-lbl">Title <span class="req">*</span></label>
            <input type="text" name="title" class="frm-input" maxlength="190" required placeholder="e.g. VFD fault on line 2">
        </div>

        <div class="field-group" style="margin-top:16px">
            <label class="field-lbl">Description</label>
            <textarea name="description" class="frm-area" maxlength="2000"></textarea>
        </div>

        <div class="field-group" style="margin-top:16px;max-width:320px">
            <label class="field-lbl">PO reference</label>
            <input type="text" name="client_po_reference" class="frm-input" maxlength="100">
        </div>

        <div style="margin-top:22px;display:flex;gap:10px;justify-content:flex-end">
            <a href="{{ route('technician.team.desktop.requests') }}" class="btn ghost">Cancel</a>
            <button type="submit" class="btn dark">Create request</button>
        </div>
    </form>
</div>

<script>
(function () {
    const clientSel = document.getElementById('client_id');
    const siteSel = document.getElementById('project_site_id');

    clientSel.addEventListener('change', function () {
        const clientId = this.value;
        if (! clientId) {
            siteSel.innerHTML = '<option value="">Select client first</option>';
            siteSel.disabled = true;
            return;
        }
        siteSel.innerHTML = '<option value="">Loading…</option>';
        siteSel.disabled = true;

        fetch("{{ route('technician.team.sites-for-client') }}?client_id=" + encodeURIComponent(clientId))
            .then(r => r.json())
            .then(sites => {
                if (! sites.length) {
                    siteSel.innerHTML = '<option value="">No sites for this client</option>';
                    return;
                }
                siteSel.innerHTML = '<option value="">Select site</option>' +
                    sites.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
                siteSel.disabled = false;
            })
            .catch(() => { siteSel.innerHTML = '<option value="">Could not load sites</option>'; });
    });
})();
</script>
@endsection