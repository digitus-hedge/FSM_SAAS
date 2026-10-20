@extends('layouts.admin')
@section('title', ($mode === 'create' ? 'New' : 'Edit') . ' Holiday')
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

  .section{margin-bottom:24px}
  .section:last-of-type{margin-bottom:0}
  .section-title{font-family:'Figtree';font-size:13px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
  .section-sub{font-size:12px;color:var(--muted);margin-bottom:16px}

  .field{margin-bottom:16px}
  .field:last-child{margin-bottom:0}
  .field label.lbl{display:block;font-size:12px;font-weight:600;color:var(--ink);margin-bottom:6px;letter-spacing:.02em}
  .field .req{color:var(--red);margin-left:2px}
  .field .hint{display:block;font-size:11px;color:var(--muted);margin-top:5px}

  .input{
    width:100%;font-family:'DM Sans';font-size:13px;padding:10px 14px;border-radius:10px;
    border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s, box-shadow .15s;
  }
  .input:focus{border-color:var(--yellow);box-shadow:0 0 0 3px rgba(255,198,26,.15)}

  .field.has-error .input{border-color:var(--red);background:#FFF7F7}
  .err-inline{display:block;font-size:11px;color:var(--red);margin-top:5px;font-weight:500}

  .form-actions{
    display:flex;gap:10px;align-items:center;justify-content:flex-end;
    padding-top:20px;margin-top:24px;border-top:1px solid var(--line);
  }
</style>

<div class="page-head">
  <div>
    <h1>{{ $mode === 'create' ? 'New Holiday' : 'Edit Holiday' }}</h1>
    <p>Add a non-working day so the SLA scheduler skips it.</p>
  </div>
  <a href="{{ route('admin.tenant-holidays.index') }}" class="btn btn-ghost">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Back
  </a>
</div>

<form method="POST"
      action="{{ $mode === 'create'
                   ? route('admin.tenant-holidays.store')
                   : route('admin.tenant-holidays.update', $holiday) }}"
      class="form-card">
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  <div class="section">
    <div class="section-title">Holiday details</div>
    <div class="section-sub">Pick the date and give it a short name so staff can recognize it in the calendar.</div>

    <div class="field {{ $errors->has('holiday_date') ? 'has-error' : '' }}">
      <label for="holiday_date" class="lbl">Date <span class="req">*</span></label>
      <input type="date" id="holiday_date" name="holiday_date" required
             value="{{ old('holiday_date', optional($holiday->holiday_date)->format('Y-m-d')) }}"
             class="input">
      <small class="hint">SLA timers won't run on this date.</small>
      @error('holiday_date') <small class="err-inline">{{ $message }}</small> @enderror
    </div>

    <div class="field {{ $errors->has('name') ? 'has-error' : '' }}">
      <label for="name" class="lbl">Name <span class="req">*</span></label>
      <input type="text" id="name" name="name" maxlength="120" required
             value="{{ old('name', $holiday->name) }}" class="input"
             placeholder="e.g. Republic Day, Diwali, Christmas, Company anniversary">
      @error('name') <small class="err-inline">{{ $message }}</small> @enderror
    </div>
  </div>

  <div class="form-actions">
    <a href="{{ route('admin.tenant-holidays.index') }}" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      {{ $mode === 'create' ? 'Create holiday' : 'Save changes' }}
    </button>
  </div>
</form>
@endsection