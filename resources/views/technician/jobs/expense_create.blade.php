@extends('layouts.technician')
@section('title', 'Add expense')

@section('appbar')
<div class="appbar">
  <button type="button" class="back" onclick="history.back()"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
  <h1>Add expense</h1>
  <span class="sent-chip">All sent</span>
</div>
@endsection

@section('content')
<style>
  .ex-label{display:block;font-weight:800;font-size:14px;margin-bottom:6px}
  .ex-field{width:100%;font-family:'DM Sans';font-size:14.5px;padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;outline:none;margin-bottom:4px;box-sizing:border-box;transition:border .15s, box-shadow .15s}
  .ex-field:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(47,91,234,.15)}
  .ex-field.has-error{border-color:var(--red);background:#FFF7F7;box-shadow:0 0 0 3px rgba(194,56,27,.1)}
  .err-inline{display:flex;align-items:center;gap:4px;font-size:12.5px;color:var(--red);margin:4px 0 14px;font-weight:600}
  .err-inline::before{content:'⚠'}
  .hint-inline{display:block;font-size:12px;color:var(--muted);margin:4px 0 14px}
  .ex-total{display:flex;justify-content:space-between;align-items:center;padding:14px;border:1px solid var(--line);border-radius:12px;background:var(--soft);margin:6px 0 14px}
</style>

@if ($errors->any())
  <div style="background:var(--red-soft);border:1px solid #F0C7C7;border-radius:12px;padding:12px 14px;margin-bottom:14px;color:var(--red);font-size:13.5px;font-weight:700">
    Please fix the {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} below.
  </div>
@endif

<form method="POST" action="{{ route('technician.jobs.expenses.store', $job) }}" id="exp-form" enctype="multipart/form-data">@csrf

<label class="ex-label">Category</label>
<select name="category_id" class="ex-field @error('category_id') has-error @enderror">
  @foreach ($cats as $c)
    <option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name }}</option>
  @endforeach
</select>
@error('category_id')<span class="err-inline">{{ $message }}</span>@enderror

<label class="ex-label">Description</label>
<input type="text" name="desc" value="{{ old('desc') }}" class="ex-field @error('desc') has-error @enderror">
@error('desc')
  <span class="err-inline">{{ $message }}</span>
@else
  <span class="hint-inline">Enter a description (2 to 255 characters).</span>
@enderror

<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
  <div>
    <label class="ex-label">Quantity</label>
    <input type="number" name="qty" step="0.01" min="0.01" value="{{ old('qty', 1) }}"
           class="ex-field @error('qty') has-error @enderror" id="qty">
    @error('qty')
      <span class="err-inline">{{ $message }}</span>
    @else
      <span class="hint-inline">Enter a quantity above 0, with up to 2 decimals.</span>
    @enderror
  </div>
  <div>
    <label class="ex-label">Rate (AED)</label>
    <input type="number" name="rate" step="0.01" min="0" value="{{ old('rate') }}"
           class="ex-field @error('rate') has-error @enderror" id="rate">
    @error('rate')
      <span class="err-inline">{{ $message }}</span>
    @else
      <span class="hint-inline">Enter a rate of 0 or more, with up to 2 decimals.</span>
    @enderror
  </div>
</div>

<div class="ex-total"><span style="color:var(--muted);font-weight:600">Total</span><strong id="total" style="font-size:18px">0.00 AED</strong></div>

<label class="ex-label">Receipt photo</label>
<label class="ex-field @error('receipt') has-error @enderror" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:8px">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
  <span id="recpt-text">Take a photo of the receipt</span>
  <input type="file" name="receipt" accept="image/*" capture="environment" style="display:none"
         onchange="document.getElementById('recpt-text').textContent = this.files[0]?.name || 'Take a photo of the receipt'">
</label>
@error('receipt')<span class="err-inline">{{ $message }}</span>@enderror

<button type="submit" class="btn btn-primary" style="margin-top:10px">Save expense</button>
</form>

<script>
  const q=document.getElementById('qty'),r=document.getElementById('rate'),t=document.getElementById('total');
  function calc(){t.textContent=((+q.value||0)*(+r.value||0)).toFixed(2)+' AED'}
  q.addEventListener('input',calc);r.addEventListener('input',calc);
  calc();

  @if ($errors->any())
    // Scroll to the first error after the page loads
    document.addEventListener('DOMContentLoaded', () => {
      const first = document.querySelector('.has-error');
      if (first) { first.scrollIntoView({behavior:'smooth',block:'center'}); first.focus({preventScroll:true}); }
    });
  @endif
</script>
@endsection