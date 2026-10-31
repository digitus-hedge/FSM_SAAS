@extends('layouts.admin')
@section('title', ($mode === 'create' ? 'New' : 'Edit') . ' Request')
@section('hide_layout_errors', true)

@section('content')
<style>
  .breadcrumb{font-size:12px;color:var(--muted);margin-bottom:6px}
  .breadcrumb a{color:var(--ink);text-decoration:none;border-bottom:1px solid var(--line)}
  .breadcrumb a:hover{color:var(--amber);border-color:var(--yellow)}
  .page-head{margin-bottom:22px}
  .page-head h1{font-family:'Figtree';font-size:26px;font-weight:700;letter-spacing:-.3px}
  .page-head p{color:var(--muted);font-size:13px;margin-top:3px}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:#2563EB;color:#fff}
  .btn-primary:hover{background:#1D4ED8}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .form-wrap{display:flex;flex-direction:column;gap:16px}

  /* Section card */
  .section{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  .section-head{padding:18px 24px 14px;background:#FCFBF8;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:12px}
  .section-head .num{
    width:28px;height:28px;border-radius:50%;background:var(--yellow);color:var(--ink);
    display:flex;align-items:center;justify-content:center;font-family:'Figtree';font-size:13px;font-weight:700;flex-shrink:0;
  }
  .section-head h3{font-family:'Figtree';font-size:14px;font-weight:700;color:var(--ink);letter-spacing:.02em;margin:0}
  .section-head .sub{font-size:11px;color:var(--muted);margin-top:2px;display:block}
  .section-body{padding:22px 24px}

  /* 2-column grid */
  .row-2{display:grid;grid-template-columns:1fr 1fr;gap:18px 24px}
  .row-2 .full{grid-column:1 / -1}
  @media (max-width:720px){ .row-2{grid-template-columns:1fr} }

  .field{display:flex;flex-direction:column}
  .field label.lbl{display:block;font-size:12px;font-weight:600;color:var(--ink);margin-bottom:6px;letter-spacing:.02em}
  .field .req{color:var(--red);margin-left:2px}
  .field .opt{color:var(--subtle);font-weight:400;margin-left:4px}
  .field .hint{display:block;font-size:11px;color:var(--muted);margin-top:5px}

  .input, .textarea, .select{
    width:100%;font-family:'DM Sans';font-size:13px;padding:11px 14px;border-radius:10px;
    border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s, box-shadow .15s;
  }
  .input:focus, .textarea:focus, .select:focus{border-color:var(--yellow);box-shadow:0 0 0 3px rgba(255,198,26,.15)}
  .textarea{min-height:110px;resize:vertical;font-family:inherit}
  .select{appearance:none;padding-right:38px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center}
  .select:disabled{background-color:#FCFBF8;color:var(--muted);cursor:not-allowed}

  .field.has-error .input, .field.has-error .textarea, .field.has-error .select{border-color:var(--red);background:#FFF7F7}
  .err-inline{display:block;font-size:11px;color:var(--red);margin-top:5px;font-weight:500}

  /* Priority segmented control */
  .priority-row{display:flex;flex-wrap:wrap;gap:8px}
  .priority-pill{
    display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border:1px solid var(--line);
    border-radius:999px;background:#fff;cursor:pointer;font-size:13px;font-weight:600;color:var(--ink);
    transition:all .12s;position:relative;
  }
  .priority-pill:hover{border-color:#D7D4CB;background:#FDFCF9}
  .priority-pill input{position:absolute;opacity:0;pointer-events:none}
  .priority-pill .dot{width:12px;height:12px;border-radius:50%;border:2px solid var(--line);transition:all .12s;flex-shrink:0}
  .priority-pill.is-selected{border-color:var(--yellow);background:#FFFCF2;color:var(--ink)}
  .priority-pill.is-selected .dot{border-color:var(--amber);background:var(--amber);box-shadow:inset 0 0 0 2px #fff}
  .priority-pill[data-level=emergency].is-selected{border-color:var(--red);background:#FEF2F2}
  .priority-pill[data-level=emergency].is-selected .dot{border-color:var(--red);background:var(--red)}
  .priority-pill[data-level=critical].is-selected{border-color:#F97316;background:#FFF7ED}
  .priority-pill[data-level=critical].is-selected .dot{border-color:#F97316;background:#F97316}

  .emergency-note{font-size:11px;color:var(--amber);margin-top:8px;display:none;background:#FFFCF2;border:1px solid var(--yellow);padding:8px 12px;border-radius:8px}
  .emergency-note.show{display:block}
  .emergency-note strong{color:var(--amber)}

  /* === FILE UPLOAD === */
  .uploader{display:flex;flex-direction:column;gap:14px}
  .drop-zone{
    position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:8px;padding:28px 20px;border:1.5px dashed var(--line);border-radius:12px;background:#FCFBF8;
    cursor:pointer;transition:all .15s;text-align:center;
  }
  .drop-zone:hover, .drop-zone.is-dragover{border-color:var(--yellow);background:#FFFCF2}
  .drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
  .drop-zone .ico{
    width:40px;height:40px;border-radius:12px;background:var(--yellow-soft);color:var(--amber);
    display:flex;align-items:center;justify-content:center;
  }
  .drop-zone .ico svg{width:20px;height:20px}
  .drop-zone .main{font-size:13px;font-weight:600;color:var(--ink)}
  .drop-zone .main span{color:var(--amber);text-decoration:underline}
  .drop-zone .sub{font-size:11px;color:var(--muted)}

  .preview-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(140px, 1fr));gap:12px}
  .preview{
    position:relative;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff;
    display:flex;flex-direction:column;transition:all .12s;
  }
  .preview:hover{border-color:#C7C3BF;box-shadow:0 2px 8px rgba(0,0,0,.04)}
  .preview .thumb{
    aspect-ratio:4/3;background:#FCFBF8;display:flex;align-items:center;justify-content:center;
    overflow:hidden;position:relative;
  }
  .preview .thumb img{width:100%;height:100%;object-fit:cover;display:block}
  .preview .thumb .pdf-ico{
    width:40px;height:48px;background:#FEE2E2;border:1px solid #FCA5A5;border-radius:4px;
    display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#991B1B;letter-spacing:.03em;
  }
  .preview .meta{padding:8px 10px;font-size:11px;line-height:1.3}
  .preview .meta .name{color:var(--ink);font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .preview .meta .size{color:var(--muted);margin-top:2px}
  .preview .rm{
    position:absolute;top:6px;right:6px;width:24px;height:24px;border-radius:50%;
    background:rgba(27,26,36,.72);border:0;color:#fff;cursor:pointer;
    display:flex;align-items:center;justify-content:center;transition:background .12s;
  }
  .preview .rm:hover{background:var(--red)}
  .preview .rm svg{width:12px;height:12px}

  .upload-tally{font-size:11px;color:var(--muted);display:flex;justify-content:space-between}
  .upload-tally strong{color:var(--ink)}

  /* Form actions bar */
  .form-actions{
    display:flex;gap:10px;align-items:center;padding:16px 24px;
    background:#fff;border:1px solid var(--line);border-radius:14px;
    position:sticky;bottom:16px;box-shadow:0 4px 16px rgba(0,0,0,.04);
  }
  .form-actions .spacer{flex:1}

    .section-sublabel{
    font-size:11px;font-weight:600;color:var(--subtle);
    text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;
  }
</style>

<div class="breadcrumb">
  <a href="{{ route('service-requests.index') }}">Requests</a> / {{ $mode === 'create' ? 'New request' : 'Edit request' }}
</div>

<div class="page-head">
  <h1>{{ $mode === 'create' ? 'New request' : 'Edit request' }}</h1>
  <p>{{ $mode === 'create' ? 'Log a request for a client.' : 'Update the details of this service request.' }}</p>
</div>

<form method="POST"
      action="{{ $mode === 'create'
                   ? route('service-requests.store')
                   : route('service-requests.update', $sr) }}"
      enctype="multipart/form-data"
      class="form-wrap">
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  {{-- ============= SECTION 1: Where & what ============= --}}
  <div class="section">
    <div class="section-head">
      <span class="num">1</span>
      <div>
        <h3>Where &amp; what</h3>
        <span class="sub">Who the request is for, where the work happens, and the type of job.</span>
      </div>
    </div>
    <div class="section-body">
      <div class="row-2">

        {{-- CLIENT --}}
        <div class="field {{ $errors->has('client_id') ? 'has-error' : '' }}">
          <label for="client_id" class="lbl">Client <span class="req">*</span></label>
          <select id="client_id" name="client_id" class="select">
            <option value="">Choose a client</option>
            @foreach ($clients as $c)
              <option value="{{ $c->id }}" @selected(old('client_id', $sr->client_id) == $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
          @error('client_id') <small class="err-inline">{{ $message }}</small> @enderror
        </div>

        {{-- SITE (dependent) --}}
        <div class="field {{ $errors->has('project_site_id') ? 'has-error' : '' }}">
          <label for="project_site_id" class="lbl">Site <span class="req">*</span></label>
          <select id="project_site_id" name="project_site_id" class="select"
                  data-selected="{{ old('project_site_id', $sr->project_site_id) }}"
                  @disabled(!old('client_id', $sr->client_id))>
            @if ($sites->count())
              <option value="">Choose a site</option>
              @foreach ($sites as $s)
                <option value="{{ $s->id }}" @selected(old('project_site_id', $sr->project_site_id) == $s->id)>{{ $s->name }}</option>
              @endforeach
            @else
              <option value="">Choose a client first</option>
            @endif
          </select>
          @error('project_site_id') <small class="err-inline">{{ $message }}</small> @enderror
        </div>

        {{-- CATEGORY --}}
        <div class="field {{ $errors->has('service_category_id') ? 'has-error' : '' }}">
          <label for="service_category_id" class="lbl">Category <span class="req">*</span></label>
          <select id="service_category_id" name="service_category_id" class="select">
            <option value="">Choose a category</option>
            @foreach ($categories as $cat)
              <option value="{{ $cat->id }}" @selected(old('service_category_id', $sr->service_category_id) == $cat->id)>{{ $cat->name }}</option>
            @endforeach
          </select>
          @error('service_category_id') <small class="err-inline">{{ $message }}</small> @enderror
        </div>

        {{-- PRIORITY --}}
        <div class="field {{ $errors->has('priority') ? 'has-error' : '' }}">
          <label class="lbl">Priority <span class="req">*</span></label>
          <div class="priority-row" id="priority-row">
            @foreach ($priorities as $key => $label)
              @php $checked = old('priority', $sr->priority ?? 'medium') === $key; @endphp
              <label class="priority-pill {{ $checked ? 'is-selected' : '' }}" data-level="{{ $key }}">
                <input type="radio" name="priority" value="{{ $key }}" {{ $checked ? 'checked' : '' }}>
                <span class="dot"></span>
                <span>{{ $label }}</span>
              </label>
            @endforeach
          </div>
          @error('priority') <small class="err-inline">{{ $message }}</small> @enderror
        </div>

        {{-- Emergency note spans full width --}}
        <div class="full">
          <div class="emergency-note {{ old('priority', $sr->priority ?? 'medium') === 'emergency' ? 'show' : '' }}" id="emergency-note">
            An <strong>Emergency</strong> request starts its SLA clock straight away and alerts the Operations Manager.
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- ============= SECTION 2: Request details ============= --}}
  <div class="section">
    <div class="section-head">
      <span class="num">2</span>
      <div>
        <h3>Details &amp; attachments</h3>
        <span class="sub">Describe the problem and attach anything that helps the technician.</span>
      </div>
    </div>
    <div class="section-body">
      <div class="row-2">

        {{-- TITLE (full width) --}}
        <div class="field full {{ $errors->has('title') ? 'has-error' : '' }}">
          <label for="title" class="lbl">Title <span class="req">*</span></label>
          <input type="text" id="title" name="title" maxlength="255"
                 value="{{ old('title', $sr->title) }}" class="input"
                 placeholder="For example: AC not cooling in the kitchen">
          @error('title') <small class="err-inline">{{ $message }}</small> @enderror
        </div>

        {{-- DESCRIPTION (full width) --}}
        <div class="field full {{ $errors->has('description') ? 'has-error' : '' }}">
          <label for="description" class="lbl">Description <span class="opt">(optional)</span></label>
          <textarea id="description" name="description" class="textarea" maxlength="10000"
                    placeholder="What's wrong, when did it start, who's on site today?">{{ old('description', $sr->description) }}</textarea>
          @error('description') <small class="err-inline">{{ $message }}</small> @enderror
        </div>

        {{-- PO REFERENCE (left col) --}}
        <div class="field {{ $errors->has('client_po_reference') ? 'has-error' : '' }}">
          <label for="client_po_reference" class="lbl">PO reference <span class="opt">(optional)</span></label>
          <input type="text" id="client_po_reference" name="client_po_reference" maxlength="100"
                 value="{{ old('client_po_reference', $sr->client_po_reference) }}" class="input"
                 placeholder="e.g. PO-2026-1142">
          @error('client_po_reference') <small class="err-inline">{{ $message }}</small> @enderror
        </div>

        {{-- Empty right col for alignment --}}
        <div></div>

        {{-- ATTACHMENTS (full width) --}}
      <div class="field full {{ $errors->has('attachments') || $errors->has('attachments.*') ? 'has-error' : '' }}">
  <label class="lbl">Attachments <span class="opt">(optional)</span></label>

  {{-- Already uploaded (edit mode only) --}}
  @if ($mode === 'edit')
    @php $existing = $sr->attachments(); @endphp
    @if (!empty($existing))
      <div style="margin-bottom:14px">
        <div class="section-sublabel">Already uploaded ({{ count($existing) }})</div>
        <div class="preview-grid">
          @foreach ($existing as $att)
            <div class="preview">
              <a href="{{ $att['url'] }}" target="_blank" class="thumb" style="text-decoration:none">
                @if ($att['type'] === 'image')
                  <img src="{{ $att['url'] }}" alt="{{ $att['display'] }}">
                @else
                  <div class="pdf-ico">{{ strtoupper($att['ext']) }}</div>
                @endif
              </a>
              <div class="meta">
                <div class="name" title="{{ $att['display'] }}">{{ $att['display'] }}</div>
                <div class="size">
                  @php
                    $kb = $att['size'] / 1024;
                    echo $kb < 1024 ? number_format($kb, 1) . ' KB' : number_format($kb / 1024, 1) . ' MB';
                  @endphp
                </div>
              </div>
             
           <button type="button"
        class="rm remove-existing-attachment"
        title="Remove attachment"
        aria-label="Remove {{ $att['display'] }}"
        data-filename="{{ $att['filename'] }}">
    <svg viewBox="0 0 24 24"
         fill="none"
         stroke="currentColor"
         stroke-width="2.5"
         stroke-linecap="round"
         stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"/>
        <line x1="6" y1="6" x2="18" y2="18"/>
    </svg>
</button>

            </div>
          @endforeach
        </div>
      </div>

      <div class="section-sublabel">Add more</div>
    @endif
  @endif

  <div class="uploader">
    <label class="drop-zone" id="drop-zone">
      <div class="ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
      </div>
      <div class="main"><span>Click to browse</span> or drag &amp; drop files here</div>
      <div class="sub">Up to 10 files · 10 MB each · JPG, PNG or PDF</div>
      <input type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf">
    </label>

    <div class="upload-tally" id="upload-tally" style="display:none">
      <span><strong id="tally-count">0</strong> file(s) selected</span>
      <span><strong id="tally-size">0 KB</strong> total</span>
    </div>

    <div class="preview-grid" id="preview-grid"></div>
  </div>

  @error('attachments')  <small class="err-inline">{{ $message }}</small> @enderror
  @error('attachments.*')<small class="err-inline">{{ $message }}</small> @enderror
</div>


      </div>
    </div>
  </div>

  {{-- ============= STICKY ACTIONS BAR ============= --}}
  <div class="form-actions">
    <a href="{{ route('service-requests.index') }}" class="btn btn-ghost">Cancel</a>
    <div class="spacer"></div>
    <button type="submit" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      {{ $mode === 'create' ? 'Create request' : 'Save changes' }}
    </button>
  </div>
</form>

<script>
(function () {
  /* -------- Priority pill selection -------- */
  const row = document.getElementById('priority-row');
  const note = document.getElementById('emergency-note');
  row.addEventListener('change', (e) => {
    if (e.target.name !== 'priority') return;
    row.querySelectorAll('.priority-pill').forEach(p => p.classList.remove('is-selected'));
    e.target.closest('.priority-pill').classList.add('is-selected');
    note.classList.toggle('show', e.target.value === 'emergency');
  });

  /* -------- Dependent Site dropdown -------- */
  const clientSel = document.getElementById('client_id');
  const siteSel   = document.getElementById('project_site_id');
  const sitesUrl  = "{{ route('service-requests.sites.for-client') }}";

  async function loadSites(clientId, selectedSiteId) {
    siteSel.disabled = true;
    siteSel.innerHTML = '<option value="">Loading sites…</option>';
    if (!clientId) {
      siteSel.innerHTML = '<option value="">Choose a client first</option>';
      return;
    }
    try {
      const res = await fetch(`${sitesUrl}?client_id=${encodeURIComponent(clientId)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      });
      const sites = await res.json();
      if (!sites.length) {
        siteSel.innerHTML = '<option value="">No sites found for this client</option>';
        return;
      }
      let html = '<option value="">Choose a site</option>';
      sites.forEach(s => {
        const sel = String(s.id) === String(selectedSiteId) ? 'selected' : '';
        const type = s.site_type ? ` (${s.site_type.replace(/_/g, ' ')})` : '';
        html += `<option value="${s.id}" ${sel}>${s.name}</option>`;
      });
      siteSel.innerHTML = html;
      siteSel.disabled = false;
    } catch (err) {
      siteSel.innerHTML = '<option value="">Could not load sites</option>';
      console.error(err);
    }
  }
  clientSel.addEventListener('change', () => loadSites(clientSel.value, ''));

  if (clientSel.value) {
    const pre = siteSel.dataset.selected || '';
    if (siteSel.options.length <= 1 || siteSel.disabled) {
      loadSites(clientSel.value, pre);
    } else {
      siteSel.disabled = false;
    }
  }

  /* -------- Attachments: drag-drop + preview + remove -------- */
  const dropZone = document.getElementById('drop-zone');
  const fileInp  = document.getElementById('attachments');
  const grid     = document.getElementById('preview-grid');
  const tally    = document.getElementById('upload-tally');
  const tallyC   = document.getElementById('tally-count');
  const tallyS   = document.getElementById('tally-size');

  // DataTransfer holds the current list (so we can add/remove via UI)
  const dt = new DataTransfer();

  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function render() {
    grid.innerHTML = '';
    let total = 0;
    Array.from(dt.files).forEach((file, idx) => {
      total += file.size;
      const card = document.createElement('div');
      card.className = 'preview';

      const thumb = document.createElement('div');
      thumb.className = 'thumb';
      if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.onload = () => URL.revokeObjectURL(img.src);
        thumb.appendChild(img);
      } else {
        thumb.innerHTML = '<div class="pdf-ico">PDF</div>';
      }

      const meta = document.createElement('div');
      meta.className = 'meta';
      meta.innerHTML = `<div class="name" title="${file.name}">${file.name}</div><div class="size">${formatSize(file.size)}</div>`;

      const rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'rm';
      rm.title = 'Remove';
      rm.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
      rm.addEventListener('click', () => {
        const kept = new DataTransfer();
        Array.from(dt.files).forEach((f, i) => { if (i !== idx) kept.items.add(f); });
        dt.items.clear();
        Array.from(kept.files).forEach(f => dt.items.add(f));
        fileInp.files = dt.files;
        render();
      });

      card.appendChild(thumb);
      card.appendChild(meta);
      card.appendChild(rm);
      grid.appendChild(card);
    });

    if (dt.files.length) {
      tally.style.display = 'flex';
      tallyC.textContent = dt.files.length;
      tallyS.textContent = formatSize(total);
    } else {
      tally.style.display = 'none';
    }
  }

  // Add files via browse
  fileInp.addEventListener('change', (e) => {
    Array.from(e.target.files).forEach(f => {
      // Avoid duplicates by name+size
      const existing = Array.from(dt.files).some(x => x.name === f.name && x.size === f.size);
      if (!existing) dt.items.add(f);
    });
    fileInp.files = dt.files;
    render();
  });


// No route call and no server/database deletion.
// Existing attachments marked for removal
const removedAttachments = new Set();

document.querySelectorAll('.remove-existing-attachment').forEach(button => {
    button.addEventListener('click', function () {
        const filename = this.dataset.filename;
        const card = this.closest('.preview');

        if (!filename || !card) return;

        removedAttachments.add(filename);
        card.style.display = 'none';
    });
});

// Submit removed filenames with the existing edit form
const mainForm = document.querySelector('form.form-wrap');

if (mainForm) {
    mainForm.addEventListener('submit', function () {
        mainForm.querySelectorAll('input[name="removed_attachments[]"]')
            .forEach(input => input.remove());

        removedAttachments.forEach(filename => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'removed_attachments[]';
            input.value = filename;
            mainForm.appendChild(input);
        });
    });
}


  // Drag & drop
  ['dragenter', 'dragover'].forEach(ev => {
    dropZone.addEventListener(ev, (e) => { e.preventDefault(); e.stopPropagation(); dropZone.classList.add('is-dragover'); });
  });
  ['dragleave', 'drop'].forEach(ev => {
    dropZone.addEventListener(ev, (e) => { e.preventDefault(); e.stopPropagation(); dropZone.classList.remove('is-dragover'); });
  });
  dropZone.addEventListener('drop', (e) => {
    Array.from(e.dataTransfer.files).forEach(f => {
      const okType = /\.(jpg|jpeg|png|pdf)$/i.test(f.name);
      if (okType && !Array.from(dt.files).some(x => x.name === f.name && x.size === f.size)) {
        dt.items.add(f);
      }
    });
    fileInp.files = dt.files;
    render();
  });
})();
</script>
@endsection