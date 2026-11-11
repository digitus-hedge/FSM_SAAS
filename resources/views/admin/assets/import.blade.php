@extends('layouts.admin')
@section('title', 'Import assets')

@section('content')
@php $plural = $plural ?? 'Assets'; @endphp
<style>
  .crumb{font-size:13px;color:#5F5D6E;margin-bottom:12px}
  .crumb a{color:#0F0F17;text-decoration:underline}
  .page-h1{font-family:var(--font-display);font-size:28px;font-weight:800;margin-bottom:6px}
  .page-sub{color:#5F5D6E;font-size:14px;margin-bottom:22px;max-width:640px}
  .card{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:24px;margin-bottom:14px}
  .card h3{font-family:var(--font-display);font-weight:700;font-size:16px;margin-bottom:10px}
  .drop{border:2px dashed #D1CFDA;border-radius:14px;background:#FAFAFC;padding:36px 20px;text-align:center;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;min-height:150px}
  .drop.hover{border-color:var(--amber);background:color-mix(in srgb, var(--amber) 6%, white)}
  .drop .main{font-family:var(--font-display);font-weight:700;font-size:15px;color:#0F0F17}
  .drop .sub{font-size:12.5px;color:#5F5D6E;margin-top:4px}
  .drop input[type=file]{display:none}
  .filechip{margin-top:12px;display:none;background:var(--yellow-soft);color:var(--amber);padding:8px 14px;border-radius:999px;font-weight:600;font-size:13px}
  .filechip.show{display:inline-flex}
  .cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:14px 0}
  .cols .col{background:#F8F7FB;border-radius:8px;padding:10px 14px;font-size:13px;color:#5F5D6E}
  .cols .col b{color:#0F0F17;display:block;margin-bottom:4px}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:var(--font-body);font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none}
  .btn-primary{background:var(--amber);color:#fff}
  .btn-primary:disabled{background:#B4B4B4;cursor:not-allowed}
  .btn-ghost{background:#fff;color:#0F0F17;border-color:#E5E4EC}
  .formact{display:flex;gap:10px;align-items:center;margin-top:20px}
  .alert-err{background:#FDECE7;border:1px solid #F2C2C2;color:#C2381B;padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
  .alert-info{background:#F0F4FE;border:1px solid #D7E0FA;color:var(--amber);padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
  @media(max-width:700px){.cols{grid-template-columns:1fr}}
</style>

<div class="crumb"><a href="{{ route('admin.assets.index') }}">{{ $plural }}</a> / Import</div>
<h1 class="page-h1">Import {{ strtolower($plural) }} from CSV</h1>
<p class="page-sub">Upload a CSV, review the preview, then create the valid rows. Rows with errors are left out and listed so you can fix and upload them again.</p>

@if (session('error'))<div class="alert-err">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="alert-err">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('admin.assets.import.upload') }}" enctype="multipart/form-data" id="impForm">
  @csrf
  <div class="card">
    <h3>1. Choose your file</h3>
    <label class="drop" id="drop">
      <div class="main">Click to choose a CSV file, or drop it here</div>
      <div class="sub">.csv, up to 2 MB and 2,000 rows</div>
      <input type="file" name="csv" id="csv" accept=".csv,text/csv,text/plain" required>
      <span class="filechip" id="chip"></span>
    </label>

    <div class="cols">
      <div class="col"><b>Required columns</b>name, client_code, site_code, category</div>
      <div class="col"><b>Optional columns</b>asset_code, parent_asset_code, manufacturer, model, serial_number, location_within_site, installation_date, warranty_expiry_date, status, notes</div>
    </div>
    <div class="alert-info">
      Category matches a category code or name. Dates use 2026-03-31. Status is active, under_repair or inactive (default active).
      Leave asset_code empty to have one generated. If an asset_code already exists, that row is skipped.
      A parent asset must already exist or appear earlier in the file, and must be at the same site.
    </div>

    <div class="formact">
      <button class="btn btn-primary" type="submit" id="go" disabled>Validate file</button>
      <a class="btn btn-ghost" href="{{ route('admin.assets.import.sample') }}">Download sample CSV</a>
      <a class="btn btn-ghost" href="{{ route('admin.assets.index') }}">Cancel</a>
    </div>
  </div>
</form>

<script>
(function () {
  const input = document.getElementById('csv'), chip = document.getElementById('chip'),
        go = document.getElementById('go'), drop = document.getElementById('drop');
  function sync() {
    const f = input.files && input.files[0];
    chip.textContent = f ? f.name : '';
    chip.classList.toggle('show', !!f);
    go.disabled = !f;
  }
  input.addEventListener('change', sync);
  ['dragover', 'dragenter'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.add('hover'); }));
  ['dragleave', 'drop'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.remove('hover'); }));
  drop.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) { input.files = ev.dataTransfer.files; sync(); } });
})();
</script>
@endsection