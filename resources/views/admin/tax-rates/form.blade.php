@extends('layouts.admin')
@section('title', $mode === 'create' ? 'New Tax Rate' : 'Edit Tax Rate')
@section('hide_layout_errors', true)

@section('content')
<style>
  .page-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
  .page-head h1{font-family:'Figtree';font-size:26px;font-weight:700;letter-spacing:-.3px}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:13px;font-weight:600;padding:10px 18px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:#F0B700}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .form-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:28px;max-width:620px;margin:0 auto}
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 20px}
  .field{display:flex;flex-direction:column;gap:6px}
  .field-wide{grid-column:span 2}
  .field label{font-size:12px;font-weight:600;color:var(--ink)}
  .req{color:var(--red)}
  .hint{font-size:11px;color:var(--muted);margin-top:2px}
  .err{font-size:11px;color:var(--red);margin-top:2px;font-weight:500;display:flex;align-items:center;gap:4px}
  .err::before{content:'⚠';font-size:12px}

  .input{font-family:'DM Sans';font-size:13px;padding:10px 14px;border-radius:10px;border:1px solid var(--line);background:#FCFBF8;color:var(--ink);outline:none;width:100%;transition:border .15s,background .15s}
  .input:focus{border-color:var(--yellow);background:#fff;box-shadow:0 0 0 3px rgba(255,198,26,.15)}
  .input.has-error{border-color:var(--red);background:#fff}

  .rate-input{position:relative}
  .rate-input input{padding-right:36px;font-size:16px;font-weight:700}
  .rate-input::after{content:'%';position:absolute;right:16px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;font-weight:600;pointer-events:none}

  .quick-pills{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
  .quick-pill{font-size:11px;font-weight:600;padding:4px 10px;border-radius:999px;border:1px solid var(--line);background:#fff;cursor:pointer;color:var(--muted);transition:all .12s}
  .quick-pill:hover{border-color:var(--yellow);color:var(--amber)}

  .check-row{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:#FCFBF8}
  .check-row input[type="checkbox"]{appearance:none;-webkit-appearance:none;width:18px;height:18px;border:1.5px solid var(--line);border-radius:5px;background:#fff;cursor:pointer;position:relative;flex-shrink:0}
  .check-row input[type="checkbox"]:checked{background:var(--yellow);border-color:var(--yellow)}
  .check-row input[type="checkbox"]:checked::after{content:'';position:absolute;left:5px;top:1px;width:5px;height:10px;border:solid var(--ink);border-width:0 2px 2px 0;transform:rotate(45deg)}
  .check-row label{font-size:13px;cursor:pointer;flex:1}
  .check-row .notice{font-size:11px;color:var(--amber);margin-top:4px}

  .form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--line)}
</style>

<div class="page-head">
  <h1>{{ $mode === 'create' ? 'New tax rate' : 'Edit '.$rate->name }}</h1>
  <a href="{{ route('admin.tax-rates.index') }}" class="btn btn-ghost">← Back</a>
</div>

<form method="POST"
      action="{{ $mode === 'create' ? route('admin.tax-rates.store') : route('admin.tax-rates.update', $rate) }}"
      class="form-card" novalidate>
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  <div class="form-grid">

    <div class="field">
      <label for="name">Name <span class="req">*</span></label>
      <input id="name" name="name" value="{{ old('name', $rate->name) }}"
             maxlength="80" required
             placeholder="e.g. GST 18%"
             class="input @error('name') has-error @enderror" />
      @error('name')<span class="err">{{ $message }}</span>
      @else<span class="hint">What appears on invoices and in dropdowns.</span>@enderror
    </div>

    <div class="field">
      <label for="rate_percent">Rate <span class="req">*</span></label>
      <div class="rate-input">
        <input type="number" id="rate_percent" name="rate_percent"
               min="0" max="999.99" step="0.01" required
               value="{{ old('rate_percent', $rate->rate_percent) }}"
               class="input @error('rate_percent') has-error @enderror" />
      </div>
      @error('rate_percent')<span class="err">{{ $message }}</span>
      @else<span class="hint">Percentage from 0.00 to 999.99.</span>@enderror
      <div class="quick-pills">
        <button type="button" class="quick-pill" onclick="setRate(0)">0%</button>
        <button type="button" class="quick-pill" onclick="setRate(5)">5%</button>
        <button type="button" class="quick-pill" onclick="setRate(12)">12%</button>
        <button type="button" class="quick-pill" onclick="setRate(18)">18%</button>
        <button type="button" class="quick-pill" onclick="setRate(28)">28%</button>
      </div>
    </div>

    <div class="field field-wide">
      <div class="check-row">
        <input type="hidden" name="is_default" value="0">
        <input type="checkbox" name="is_default" id="is_default" value="1"
               @checked(old('is_default', $rate->is_default))>
        <label for="is_default">
          Set as the default tax rate
          @if ($hasDefault && !$rate->is_default)
            <div class="notice">⚠ Another rate is currently the default — ticking this will replace it.</div>
          @endif
        </label>
      </div>
    </div>

    <div class="field field-wide">
      <div class="check-row">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1"
               @checked(old('is_active', $rate->exists ? $rate->is_active : true))>
        <label for="is_active">Active — appears in tax dropdown on new line items</label>
      </div>
    </div>

  </div>

  <div class="form-actions">
    <a href="{{ route('admin.tax-rates.index') }}" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      {{ $mode === 'create' ? 'Create tax rate' : 'Save changes' }}
    </button>
  </div>
</form>

<script>
function setRate(val) {
  document.getElementById('rate_percent').value = val.toFixed(2);
}
</script>
@endsection