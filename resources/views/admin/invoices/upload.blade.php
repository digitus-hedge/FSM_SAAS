@extends('layouts.admin')
@section('title', 'Upload invoice PDF')
@section('content')
<style>
.wrap{max-width:640px}
.page-h1{font-family:'Figtree';font-size:24px;font-weight:800;margin-bottom:6px}
.page-sub{color:var(--muted);font-size:14px;margin-bottom:20px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:24px}
.fld{display:flex;flex-direction:column;gap:4px;margin-bottom:14px}
.fld label{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.fld input,.fld textarea{height:42px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font-family:inherit;font-size:14px}
.fld textarea{min-height:70px;padding:12px 14px;height:auto}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.btn{display:inline-block;background:#FFC61A;color:#1B1A24;font-weight:700;padding:11px 22px;border-radius:999px;border:0;cursor:pointer;font-family:inherit;font-size:13px;text-decoration:none}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--ink);font-weight:600}
</style>
<div class="wrap">
  <h1 class="page-h1">Upload invoice PDF</h1>
  <p class="page-sub">{{ $sr->sr_number }} · {{ $sr->title ?? '' }} · {{ $sr->client?->name }}</p>

  <form method="POST" action="{{ route('admin.invoices.store-upload', $sr) }}" enctype="multipart/form-data" class="card">
    @csrf
    <div class="fld"><label>Invoice PDF *</label><input type="file" name="pdf" accept="application/pdf" required></div>
    <div class="row2">
      <div class="fld"><label>Total *</label><input name="total" value="0.00" required style="text-align:right"></div>
      <div class="fld"><label>Tax included</label><input name="tax_included" value="0.00" style="text-align:right"></div>
    </div>
    <div class="fld"><label>Due date *</label><input type="date" name="due_date" value="{{ now()->addDays(20)->format('Y-m-d') }}" required></div>
    <div class="fld"><label>Notes</label><textarea name="notes" maxlength="500"></textarea></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
      <a href="{{ route('admin.invoices.index') }}" class="btn ghost">Cancel</a>
      <button type="submit" class="btn">Save invoice</button>
    </div>
  </form>
</div>
@endsection