@extends('layouts.admin')
@section('title', 'Branding')
@section('content')
<style>
  .brand-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px;max-width:520px}
  .brand-field{margin-bottom:18px}
  .brand-field label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}
  .brand-field select{width:100%;height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font:inherit;font-size:14px;background:#fff}
  .color-row{display:flex;align-items:center;gap:12px}
  .color-row input[type=color]{width:52px;height:44px;padding:0;border:1px solid var(--line);border-radius:10px}
  .color-row input[type=text]{flex:1;height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font:inherit;font-size:14px}
  .btn-primary{background:var(--yellow);color:var(--ink);font-weight:700;font-size:14px;padding:12px 24px;border-radius:999px;border:0;cursor:pointer;font-family:inherit}
</style>

<h1 class="page-h1">Branding</h1>
<p class="sub-txt" style="margin-bottom:22px">Pick your company's color and font. This applies across every page.</p>

<form method="POST" action="{{ route('admin.branding.update') }}" class="brand-card">
  @csrf
  @method('PUT')

  <div class="brand-field">
    <label>Primary color</label>
    <div class="color-row">
      <input type="color" id="colorPicker" value="{{ old('primary_color', $tenant->primary_color ?? '#FFC61A') }}"
             oninput="document.getElementById('colorText').value = this.value">
      <input type="text" name="primary_color" id="colorText" value="{{ old('primary_color', $tenant->primary_color ?? '#FFC61A') }}"
             oninput="document.getElementById('colorPicker').value = this.value" maxlength="7">
    </div>
    @error('primary_color') <span style="color:var(--red);font-size:12px">{{ $message }}</span> @enderror
  </div>

  <div class="brand-field">
    <label>Font</label>
    <select name="font_key">
      @foreach ($fonts as $font)
        <option value="{{ $font->key }}" {{ old('font_key', $tenant->font_key ?? 'dm_sans') === $font->key ? 'selected' : '' }}>
          {{ $font->name }}
        </option>
      @endforeach
    </select>
    @error('font_key') <span style="color:var(--red);font-size:12px">{{ $message }}</span> @enderror
  </div>

  <button type="submit" class="btn-primary">Save branding</button>
</form>
@endsection