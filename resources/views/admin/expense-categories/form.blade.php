@extends('layouts.admin')
@section('title', ($mode === 'create' ? 'New' : 'Edit') . ' Expense Category')
@section('hide_layout_errors', true)

@section('content')
<style>
  .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}
  .page-head h1{font-family:'Figtree';font-size:26px;font-weight:700;letter-spacing:-.3px}
  .page-head p{color:var(--muted);font-size:13px;margin-top:3px}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:#F0B700}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .form-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:28px;}

  .section{margin-bottom:28px}
  .section:last-of-type{margin-bottom:0}
  .section-title{font-family:'Figtree';font-size:13px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
  .section-sub{font-size:12px;color:var(--muted);margin-bottom:16px}

  .field{margin-bottom:16px}
  .field:last-child{margin-bottom:0}
  .field label.lbl{display:block;font-size:12px;font-weight:600;color:var(--ink);margin-bottom:6px;letter-spacing:.02em}
  .field .req{color:var(--red);margin-left:2px}
  .field .hint{display:block;font-size:11px;color:var(--muted);margin-top:5px}

  .input, .textarea{
    width:100%;font-family:'DM Sans';font-size:13px;padding:10px 14px;border-radius:10px;
    border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s, box-shadow .15s;
  }
  .input:focus, .textarea:focus{border-color:var(--yellow);box-shadow:0 0 0 3px rgba(255,198,26,.15)}
  .textarea{min-height:80px;resize:vertical;font-family:inherit}

  /* Input with currency prefix */
  .input-wrap{position:relative;display:flex;align-items:center}
  .input-wrap .prefix{
    position:absolute;left:14px;font-size:12px;font-weight:600;color:var(--subtle);
    pointer-events:none;letter-spacing:.03em;
  }
  .input-wrap .input{padding-left:46px}

  /* Field error */
  .field.has-error .input, .field.has-error .textarea{border-color:var(--red);background:#FFF7F7}
  .err-inline{display:block;font-size:11px;color:var(--red);margin-top:5px;font-weight:500}

  /* Toggle rows */
  .toggle-row{
    display:flex;align-items:flex-start;gap:14px;padding:14px 16px;background:#FCFBF8;
    border:1px solid var(--line);border-radius:10px;margin-bottom:10px;cursor:pointer;transition:background .12s;
  }
  .toggle-row:hover{background:#FAF8F2}
  .toggle-row:last-child{margin-bottom:0}
  .toggle-row input[type=checkbox]{position:absolute;opacity:0;pointer-events:none}
  .toggle-row .track{
    position:relative;flex-shrink:0;width:36px;height:20px;background:#D7D4CB;border-radius:999px;
    transition:background .15s;margin-top:1px;
  }
  .toggle-row .track::after{
    content:'';position:absolute;top:2px;left:2px;width:16px;height:16px;
    background:#fff;border-radius:50%;transition:transform .15s;box-shadow:0 1px 2px rgba(0,0,0,.15);
  }
  .toggle-row input:checked ~ .track{background:var(--yellow)}
  .toggle-row input:checked ~ .track::after{transform:translateX(16px)}
  .toggle-row .meta{flex:1;min-width:0}
  .toggle-row .meta-title{font-size:13px;font-weight:600;color:var(--ink);display:block}
  .toggle-row .meta-desc{font-size:11px;color:var(--muted);margin-top:2px;display:block}

  .form-actions{
    display:flex;gap:10px;align-items:center;justify-content:flex-end;
    padding-top:20px;margin-top:24px;border-top:1px solid var(--line);
  }
</style>

<div class="page-head">
  <div>
    <h1>{{ $mode === 'create' ? 'New Expense Category' : 'Edit Expense Category' }}</h1>
    <p>Define a type of expense technicians can log against a service request.</p>
  </div>
  <a href="{{ route('admin.expense-categories.index') }}" class="btn btn-ghost">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Back
  </a>
</div>

<form method="POST"
      action="{{ $mode === 'create'
                   ? route('admin.expense-categories.store')
                   : route('admin.expense-categories.update', $category) }}"
      class="form-card">
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  {{-- SECTION: Basic --}}
  <div class="section">
    <div class="section-title">Basic</div>
    <div class="section-sub">Give the category a short, recognizable name.</div>

    <div class="field {{ $errors->has('name') ? 'has-error' : '' }}">
      <label for="name" class="lbl">Expense Category name <span class="req">*</span></label>
      <input type="text" id="name" name="name" maxlength="100" required
             value="{{ old('name', $category->name) }}" class="input"
             placeholder="e.g. Travel, Food, Hotel, Taxi, Materials">
      @error('name') <small class="err-inline">{{ $message }}</small> @enderror
    </div>

    <div class="field {{ $errors->has('default_unit_rate') ? 'has-error' : '' }}">
      <label for="default_unit_rate" class="lbl">Default unit rate</label>
      <div class="input-wrap">
        <span class="prefix">{{ auth()->user()->tenant->currency_code ?? '₹' }}</span>
        <input type="number" id="default_unit_rate" name="default_unit_rate"
               step="0.01" min="0"
               value="{{ old('default_unit_rate', $category->default_unit_rate) }}" class="input"
               placeholder="0.00">
      </div>
      <small class="hint">Optional. Pre-fills when staff log this expense; they can override per entry.</small>
      @error('default_unit_rate') <small class="err-inline">{{ $message }}</small> @enderror
    </div>
  </div>

  {{-- SECTION: Behavior --}}
  <div class="section">
    <div class="section-title">Behavior</div>
    <div class="section-sub">How this category behaves when added to a service request.</div>

    <label class="toggle-row">
      <input type="hidden" name="is_billable_default" value="0">
      <input type="checkbox" name="is_billable_default" value="1"
             {{ old('is_billable_default', $category->is_billable_default) ? 'checked' : '' }}>
      <span class="track"></span>
      <span class="meta">
        <span class="meta-title">Billable to client by default</span>
        <span class="meta-desc">Expenses added under this category are flagged as billable unless the technician unchecks it.</span>
      </span>
    </label>

    <label class="toggle-row">
      <input type="hidden" name="requires_receipt" value="0">
      <input type="checkbox" name="requires_receipt" value="1"
             {{ old('requires_receipt', $category->requires_receipt) ? 'checked' : '' }}>
      <span class="track"></span>
      <span class="meta">
        <span class="meta-title">Requires receipt attachment</span>
        <span class="meta-desc">Technicians must attach a photo or PDF of the receipt before the expense can be submitted.</span>
      </span>
    </label>
  </div>

  {{-- SECTION: Status --}}
  <div class="section">
    <div class="section-title">Status</div>
    <div class="section-sub">Deactivated categories stay for history but can't be chosen on new expenses.</div>

    <label class="toggle-row">
      <input type="hidden" name="is_active" value="0">
      <input type="checkbox" name="is_active" value="1"
             {{ old('is_active', $category->is_active) ? 'checked' : '' }}>
      <span class="track"></span>
      <span class="meta">
        <span class="meta-title">Active</span>
        <span class="meta-desc">Appears in the expense-category dropdown on new entries.</span>
      </span>
    </label>
  </div>

  <div class="form-actions">
    <a href="{{ route('admin.expense-categories.index') }}" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      {{ $mode === 'create' ? 'Create category' : 'Save changes' }}
    </button>
  </div>
</form>
@endsection