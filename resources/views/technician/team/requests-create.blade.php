@extends('layouts.technician-web')
@section('title', 'New request')
@section('crumb', 'Requests')
@section('content')

<style>
.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media (max-width:700px){.field-grid{grid-template-columns:1fr}}
.field-group{display:flex;flex-direction:column;min-width:0}
.field-lbl{display:block;font-size:12.5px;font-weight:700;margin-bottom:7px;color:var(--ink)}
.field-lbl .req{color:var(--red);margin-left:2px}
.field-lbl .hint{color:var(--muted);font-weight:500}
.frm-select,.frm-input,.frm-area{box-sizing:border-box;width:100%;font-family:inherit;font-size:13.5px;padding:10px 13px;border-radius:10px;border:1px solid var(--line);background:var(--surface);color:var(--ink);outline:none}
.frm-select:focus,.frm-input:focus,.frm-area:focus{border-color:var(--accent)}
.frm-area{min-height:100px;resize:vertical}

/* step headers */
.stephead{display:flex;gap:12px;align-items:flex-start;margin-bottom:18px}
.stepnum{width:26px;height:26px;border-radius:999px;background:var(--yellow);color:var(--ink);font-weight:700;font-size:13px;display:grid;place-items:center;flex-shrink:0}
.stephead h3{margin:0;font-size:15.5px;font-weight:700}
.stephead p{margin:3px 0 0;color:var(--muted);font-size:13px}

/* priority pills */
.pri-group{display:flex;gap:8px;flex-wrap:wrap}
.pri-opt{position:absolute;opacity:0;width:0;height:0}
.pri-lbl{display:inline-flex;align-items:center;gap:7px;padding:9px 14px;border:1px solid var(--line);border-radius:999px;font-size:13px;font-weight:700;cursor:pointer;background:var(--surface);color:var(--ink)}
.pri-dot{width:9px;height:9px;border-radius:50%;border:2px solid var(--muted);flex-shrink:0}
.pri-opt:checked + .pri-lbl{border-color:var(--yellow);background:var(--yellow-soft)}
.pri-opt:checked + .pri-lbl .pri-dot{border-color:var(--yellow);background:var(--yellow)}
.pri-opt:focus-visible + .pri-lbl{outline:2px solid var(--accent);outline-offset:2px}

/* attachments dropzone */
.dropzone{box-sizing:border-box;border:1.5px dashed var(--line);border-radius:14px;padding:26px 16px;text-align:center;cursor:pointer;background:var(--soft)}
.dropzone.drag{border-color:var(--accent);background:var(--tint)}
.dz-icon{width:38px;height:38px;border-radius:999px;background:var(--yellow-soft);color:var(--ink);display:grid;place-items:center;margin:0 auto 10px;font-size:16px}
.dz-link{color:var(--ink);font-weight:700;text-decoration:underline;text-underline-offset:2px}
.dz-sub{color:var(--muted);font-size:12.5px;margin-top:4px}
.filelist{margin-top:10px;display:flex;flex-direction:column;gap:6px}
.filerow{box-sizing:border-box;display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:13px;padding:8px 12px;border:1px solid var(--line);border-radius:10px;background:var(--surface)}
.filerow .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.filerow .sz{color:var(--muted);font-size:12px;flex-shrink:0}
.filerow button{border:0;background:none;color:var(--red);cursor:pointer;font-weight:700;font-size:12px;flex-shrink:0}
</style>

<div class="pagehead">
    <div>
        <h1>New request</h1>
        <p>Raised by you — it lands in your own Requests queue to approve or assign.</p>
    </div>
</div>

<form method="POST" action="{{ route('technician.team.desktop.requests.store') }}" enctype="multipart/form-data" id="newReqForm">
    @csrf

    <div class="card">
        <div class="stephead">
            <div class="stepnum">1</div>
            <div>
                <h3>Who &amp; where</h3>
                <p>Pick the client, site, category and priority for this job.</p>
            </div>
        </div>

        <div class="field-grid">
            <div class="field-group">
                <label class="field-lbl">Client <span class="req">*</span></label>
                <select name="client_id" id="client_id" class="frm-select" required>
                    <option value="">Choose a client</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="field-group">
                <label class="field-lbl">Site <span class="req">*</span></label>
                <select name="project_site_id" id="project_site_id" class="frm-select" required disabled>
                    <option value="">Choose a client first</option>
                </select>
            </div>
            <div class="field-group">
                <label class="field-lbl">Category <span class="req">*</span></label>
                <select name="service_category_id" class="frm-select" required>
                    <option value="">Choose a category</option>
                    @foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                </select>
            </div>
            <div class="field-group">
                <label class="field-lbl">Priority <span class="req">*</span></label>
                <div class="pri-group">
                    @foreach (\App\Models\ServiceRequest::PRIORITIES as $value => $label)
                        <input type="radio" name="priority" id="pri-{{ $value }}" value="{{ $value }}" class="pri-opt" @checked($value === 'medium')>
                        <label for="pri-{{ $value }}" class="pri-lbl"><span class="pri-dot"></span>{{ $label }}</label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="stephead">
            <div class="stepnum">2</div>
            <div>
                <h3>Details &amp; attachments</h3>
                <p>Describe the problem and attach anything that helps the technician.</p>
            </div>
        </div>

        <div class="field-group">
            <label class="field-lbl">Title <span class="req">*</span></label>
            <input type="text" name="title" class="frm-input" maxlength="190" required placeholder="For example: AC not cooling in the kitchen">
        </div>

        <div class="field-group" style="margin-top:16px">
            <label class="field-lbl">Description <span class="hint">(optional)</span></label>
            <textarea name="description" class="frm-area" maxlength="2000" placeholder="What's wrong, when did it start, who's on site today?"></textarea>
        </div>

        <div class="field-group" style="margin-top:16px;max-width:320px">
            <label class="field-lbl">PO reference <span class="hint">(optional)</span></label>
            <input type="text" name="client_po_reference" class="frm-input" maxlength="100" placeholder="e.g. PO-2026-1142">
        </div>

        <div class="field-group" style="margin-top:16px">
            <label class="field-lbl">Attachments <span class="hint">(optional)</span></label>
            <div class="dropzone" id="dropzone">
                <div class="dz-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>
                </div>
                <div><span class="dz-link">Click to browse</span> or drag &amp; drop files here</div>
                <div class="dz-sub">Photos, PDFs — up to 10MB each</div>
            </div>
            <input type="file" name="attachments[]" id="attachments" multiple hidden accept="image/*,.pdf,.doc,.docx">
            <div class="filelist" id="fileList"></div>
        </div>
    </div>

    <div style="margin-top:4px;display:flex;gap:10px;justify-content:flex-end">
        <a href="{{ route('technician.team.desktop.requests') }}" class="btn ghost">Cancel</a>
        <button type="submit" class="btn dark">Create request</button>
    </div>
</form>

<script>
(function () {
    const clientSel = document.getElementById('client_id');
    const siteSel = document.getElementById('project_site_id');

    clientSel.addEventListener('change', function () {
        const clientId = this.value;
        if (! clientId) {
            siteSel.innerHTML = '<option value="">Choose a client first</option>';
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
                siteSel.innerHTML = '<option value="">Choose a site</option>' +
                    sites.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
                siteSel.disabled = false;
            })
            .catch(() => { siteSel.innerHTML = '<option value="">Could not load sites</option>'; });
    });
})();

(function () {
    const dz = document.getElementById('dropzone');
    const input = document.getElementById('attachments');
    const list = document.getElementById('fileList');
    let files = [];

    function render() {
        list.innerHTML = '';
        files.forEach((f, i) => {
            const row = document.createElement('div');
            row.className = 'filerow';
            const kb = (f.size / 1024).toFixed(0);
            row.innerHTML = `<span class="nm">${f.name}</span><span class="sz">${kb} KB</span>`;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = 'Remove';
            btn.addEventListener('click', () => { files.splice(i, 1); sync(); });
            row.appendChild(btn);
            list.appendChild(row);
        });
    }

    function sync() {
        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        input.files = dt.files;
        render();
    }

    function addFiles(fileListLike) {
        Array.from(fileListLike).forEach(f => files.push(f));
        sync();
    }

    dz.addEventListener('click', () => input.click());
    input.addEventListener('change', () => addFiles(input.files));

    ['dragenter', 'dragover'].forEach(evt => dz.addEventListener(evt, e => {
        e.preventDefault();
        dz.classList.add('drag');
    }));
    ['dragleave', 'drop'].forEach(evt => dz.addEventListener(evt, e => {
        e.preventDefault();
        dz.classList.remove('drag');
    }));
    dz.addEventListener('drop', e => {
        if (e.dataTransfer?.files?.length) addFiles(e.dataTransfer.files);
    });
})();
</script>
@endsection