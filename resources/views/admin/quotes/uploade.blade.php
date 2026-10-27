@extends('layouts.admin')
@section('title','Upload a quote')

@section('content')
<style>
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;max-width:520px}
  h1{font-family:'Figtree';font-size:28px;font-weight:800;margin-bottom:4px}
  .sub{color:var(--muted);font-size:13.5px;margin-bottom:22px}
  .fld{margin-bottom:16px}
  .fld label{display:block;font-weight:700;font-family:'Figtree';margin-bottom:6px}
  .req{color:#D22E2E}
  .fld input{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-family:inherit;font-size:14px;background:#fff}
  .hint{color:var(--muted);font-size:12px;margin-top:4px}
  .err{color:#C2381B;font-size:12px;margin-top:4px}
  .btns{display:flex;gap:10px;margin-top:16px}
  .btn{padding:12px 22px;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;border:1px solid var(--line);background:#fff;color:var(--ink);font-family:inherit}
  .btn-primary{background:#2F5BEA;color:#fff;border-color:#2F5BEA}
</style>

<div style="color:var(--muted);font-size:13px;margin-bottom:4px"><a href="{{ route('admin.requests-review.index') }}" style="color:var(--ink)">Quotes</a></div>
<h1>Upload a quote</h1>
<div class="sub">{{ $sr->sr_number }} {{ $sr->title }}, {{ $sr->client?->name }}</div>

<form method="POST" action="{{ route('admin.quotes.store-upload', $sr) }}" enctype="multipart/form-data" class="card">
  @csrf
  <div class="fld">
    <label>Quote PDF <span class="req">*</span></label>
    <input type="file" name="pdf" accept="application/pdf" required>
    <div class="hint">PDF, up to 10 MB</div>
    @error('pdf')<div class="err">{{ $message }}</div>@enderror
  </div>

  <div class="fld">
    <label>Total <span class="req">*</span></label>
    <input type="number" step="0.01" min="0.01" name="total" value="{{ old('total') }}" required>
    <div class="hint">Above 0, in AED</div>
    @error('total')<div class="err">{{ $message }}</div>@enderror
  </div>

  <div class="fld">
    <label>Tax included in the total</label>
    <input type="number" step="0.01" min="0" name="tax_included" value="{{ old('tax_included') }}">
    <div class="hint">Optional</div>
  </div>

  <div class="fld">
    <label>Valid until <span class="req">*</span></label>
    <input type="date" name="valid_until" value="{{ old('valid_until', now()->addDays(10)->format('Y-m-d')) }}" required>
  </div>

  <div class="btns">
    <button type="submit" class="btn">Save</button>
    <button type="submit" name="and_send" value="1" class="btn btn-primary">Save and send</button>
  </div>
</form>
@endsection