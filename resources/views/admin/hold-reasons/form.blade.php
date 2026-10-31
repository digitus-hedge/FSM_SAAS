@extends('layouts.admin')
@section('title', ($mode === 'create' ? 'New' : 'Edit') . ' Hold Reason')
@section('hide_layout_errors', true)

@section('content')
<style>
  .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}
  .page-head h1{font-family:var(--font-display);font-size:26px;font-weight:700;letter-spacing:-.3px}
  .page-head p{color:var(--muted);font-size:13px;margin-top:3px}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-body);font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:color-mix(in srgb, var(--yellow) 85%, black 15%)}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .form-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:28px;}

  .section{margin-bottom:28px}
  .section:last-of-type{margin-bottom:0}
  .section-title{font-family:var(--font-display);font-size:13px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
  .section-sub{font-size:12px;color:var(--muted);margin-bottom:16px}

  .field{margin-bottom:16px}
  .field:last-child{margin-bottom:0}
  .field label.lbl{display:block;font-size:12px;font-weight:600;color:var(--ink);margin-bottom:6px;letter-spacing:.02em}
  .field .req{color:var(--red);margin-left:2px}
  .field .hint{display:block;font-size:11px;color:var(--muted);margin-top:5px}

  .input{
    width:100%;font-family:var(--font-body);font-size:13px;padding:10px 14px;border-radius:10px;
    border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s, box-shadow .15s;
  }
  .input:focus{border-color:var(--yellow);box-shadow:0 0 0 3px color-mix(in srgb, var(--yellow) 15%, transparent)}

  .field.has-error .input{border-color:var(--red);background:var(--red-soft)}
  .err-inline{display:block;font-size:11px;color:var(--red);margin-top:5px;font-weight:500}

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
    <h1>{{ $mode === 'create' ? 'New Hold Reason' : 'Edit Hold Reason' }}</h1>
    <p>Define why work on a service request may be paused.</p>
  </div>
  <a href="{{ route('admin.hold-reasons.index') }}" class="btn btn-ghost">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Back
  </a>
</div>

<form method="POST"
      action="{{ $mode === 'create'
                   ? route('admin.hold-reasons.store')
                   : route('admin.hold-reasons.update', $reason) }}"
      class="form-card">
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  <div class="section">
    <div class="section-title">Basic</div>
    <div class="section-sub">A short, recognizable label — this is what dispatchers pick from the dropdown.</div>

    <div class="field {{ $errors->has('label') ? 'has-error' : '' }}">
      <label for="label" class="lbl">Reason label <span class="req">*</span></label>
      <input type="text" id="label" name="label" maxlength="120" required
             value="{{ old('label', $reason->label) }}" class="input"
             placeholder="e.g. Waiting for parts, Client unavailable, Permit pending">
      @error('label') <small class="err-inline">{{ $message }}</small> @enderror
    </div>
  </div>

  <div class="section">
    <div class="section-title">Behavior</div>
    <div class="section-sub">How this reason affects SLA tracking and operator input.</div>

    <label class="toggle-row">
  <input type="hidden" name="pauses_sla" value="0">
  <input type="checkbox" name="pauses_sla" value="1"
         {{ old('pauses_sla', $mode === 'edit' ? $reason->pauses_sla : false) ? 'checked' : '' }}>
  <span class="track"></span>
  <span class="meta">
    <span class="meta-title">Pauses the SLA clock</span>
    <span class="meta-desc">When a service request is placed on hold with this reason, response and resolution timers stop until the hold is cleared.</span>
  </span>
</label>

<label class="toggle-row">
  <input type="hidden" name="requires_note" value="0">
  <input type="checkbox" name="requires_note" value="1"
         {{ old('requires_note', $mode === 'edit' ? $reason->requires_note : false) ? 'checked' : '' }}>
  <span class="track"></span>
  <span class="meta">
    <span class="meta-title">The technician must write a note</span>
    <span class="meta-desc">Dispatcher must type an explanation (e.g. part number, callback time) before the hold can be saved.</span>
  </span>
</label>
  </div>



  <div class="form-actions">
    <a href="{{ route('admin.hold-reasons.index') }}" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      {{ $mode === 'create' ? 'Create reason' : 'Save changes' }}
    </button>
  </div>
</form>
@endsection