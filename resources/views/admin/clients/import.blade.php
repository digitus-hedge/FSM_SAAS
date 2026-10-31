@extends('layouts.admin')
@section('title', 'Import clients')

@section('content')
<style>
  .crumb{font-size:13px;color:#5F5D6E;margin-bottom:12px}
  .crumb a{color:#0F0F17;text-decoration:underline}
  .page-h1{font-family:var(--font-display);font-size:28px;font-weight:800;margin-bottom:6px}
  .page-sub{color:#5F5D6E;font-size:14px;margin-bottom:22px;max-width:620px}

  .card{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:24px;margin-bottom:14px}
  .card h3{font-family:var(--font-display);font-weight:700;font-size:16px;margin-bottom:10px}

   .drop{
  border:2px dashed #D1CFDA;
  border-radius:14px;
  background:#FAFAFC;
  padding:36px 20px;
  text-align:center;
  cursor:pointer;
  transition:all .15s;

  display:flex;              /* was: default inline */
  flex-direction:column;
  align-items:center;
  justify-content:center;
  gap:4px;
  min-height:150px;
}
  .drop.hover{border-color:var(--amber);background:color-mix(in srgb, var(--amber) 6%, white)}
  .drop svg{color:var(--amber);margin-bottom:6px;display:block}
  .drop .main{font-family:var(--font-display);font-weight:700;font-size:15px;color:#0F0F17}
  .drop .sub{font-size:12.5px;color:#5F5D6E;margin-top:4px}
  .drop input[type=file]{display:none}
  .filechip{margin-top:12px;display:none;align-items:center;gap:8px;background:var(--yellow-soft);color:var(--amber);padding:8px 14px;border-radius:999px;font-weight:600;font-size:13px}
  .filechip.show{display:inline-flex}

  .check-row{display:flex;align-items:flex-start;gap:10px;padding:14px 16px;background:#FAFAFC;border:1px solid #EEEDF3;border-radius:10px;cursor:pointer;margin-top:16px}
  .check-row input{appearance:none;width:18px;height:18px;border:1.5px solid #D1CFDA;border-radius:5px;cursor:pointer;position:relative;flex-shrink:0;margin-top:1px;background:#fff}
  .check-row input:checked{background:var(--amber);border-color:var(--amber)}
  .check-row input:checked::after{content:'';position:absolute;left:5px;top:1px;width:5px;height:10px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
  .check-row .ttl{font-weight:700;font-size:13.5px}
  .check-row .sub{font-size:12.5px;color:#5F5D6E;margin-top:2px}

  .cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:14px 0}
  .cols .col{background:#F8F7FB;border-radius:8px;padding:10px 14px;font-size:13px;color:#5F5D6E}
  .cols .col b{color:#0F0F17;display:block;margin-bottom:4px}

  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:var(--font-body);font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none}
  .btn-primary{background:var(--amber);color:#fff}
  .btn-primary:hover{background:color-mix(in srgb, var(--amber) 85%, black 15%)}
  .btn-primary:disabled{background:#B4B4B4;cursor:not-allowed}
  .btn-ghost{background:#fff;color:#0F0F17;border-color:#E5E4EC}
  .btn-ghost:hover{border-color:#0F0F17}

  .formact{display:flex;gap:10px;align-items:center;margin-top:20px}

  .alert-err{background:#FDECE7;border:1px solid #F2C2C2;color:#C2381B;padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
</style>

<div class="crumb"><a href="{{ route('admin.clients.index') }}">Clients</a> / Import</div>
<h1 class="page-h1">Import clients from CSV</h1>
<p class="page-sub">Upload a CSV file to create multiple clients at once. Columns are matched by name (case-insensitive). Only the <strong>name</strong> column is required.</p>

@if (session('error'))
  <div class="alert-err">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ route('admin.clients.import.store') }}" enctype="multipart/form-data" id="import-form">
  @csrf

  {{-- 1. SAMPLE --}}
  <div class="card">
    <h3>1. Download the template</h3>
    <p style="font-size:13.5px;color:#5F5D6E;margin-bottom:10px">
      Fill in your rows and save as CSV. All columns are optional except <b>name</b>.
    </p>
    <a href="{{ route('admin.clients.import.sample') }}" class="btn btn-ghost">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Download sample CSV
    </a>

    <div class="cols">
      <div class="col"><b>Required</b>name</div>
      <div class="col"><b>Optional</b>client_code, contact_person, email, phone, tax_id, payment_terms_days, billing_address, notes, is_active</div>
    </div>
  </div>

  {{-- 2. UPLOAD --}}
  <div class="card">
    <h3>2. Upload your CSV</h3>

    <label class="drop" id="drop">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <div class="main">Click to choose a file, or drag and drop</div>
      <div class="sub">CSV, up to 2 MB</div>
      <input type="file" name="csv" id="csvfile" accept=".csv,text/csv">
    </label>

    <span class="filechip" id="chip">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span id="chipname">—</span>
    </span>

    @error('csv')<div class="alert-err" style="margin-top:10px">{{ $message }}</div>@enderror
  </div>

  {{-- 3. OPTIONS --}}
  <div class="card">
    <h3>3. Options</h3>
    <label class="check-row">
      <input type="hidden" name="update_existing" value="0">
      <input type="checkbox" name="update_existing" value="1">
      <div>
        <div class="ttl">Update existing clients</div>
        <div class="sub">If a row matches an existing client by code or name, update it instead of skipping.</div>
      </div>
    </label>
  </div>

  {{-- 4. ACTIONS --}}
  <div class="formact">
    <button type="submit" class="btn btn-primary" id="submit-btn">Import clients</button>
    <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost">Cancel</a>
  </div>
</form>

{{-- Row-level errors from the last import --}}
@if (session('import_errors') && count(session('import_errors')))
  <div class="card" style="margin-top:18px">
    <h3>Row issues</h3>
    <ul style="padding-left:18px;font-size:13.5px;color:#C2381B">
      @foreach (session('import_errors') as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif

<script>
const drop     = document.getElementById('drop');
const file     = document.getElementById('csvfile');
const chip     = document.getElementById('chip');
const chipN    = document.getElementById('chipname');
const form     = document.getElementById('import-form');
const submitBtn = document.getElementById('submit-btn');

file.addEventListener('change', e => {
  if (e.target.files.length) {
    chipN.textContent = e.target.files[0].name;
    chip.classList.add('show');
  }
});

['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('hover'); }));
['dragleave','drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('hover'); }));
drop.addEventListener('drop', e => {
  if (e.dataTransfer.files.length) {
    file.files = e.dataTransfer.files;
    chipN.textContent = e.dataTransfer.files[0].name;
    chip.classList.add('show');
  }
});

form.addEventListener('submit', function (e) {
  if (!file.files.length) {
    e.preventDefault();
    Swal.fire({
      icon: 'warning',
      title: 'Choose a CSV file',
      text: 'Pick a file to import before continuing.',
      confirmButtonText: 'Got it'
    });
    return;
  }
  submitBtn.disabled = true;
  submitBtn.textContent = 'Importing…';
});
</script>
@endsection