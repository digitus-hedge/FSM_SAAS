@extends('layouts.admin')
@section('title', ($mode === 'create' ? 'New' : 'Edit') . ' Vendor')
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

  .row-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  @media (max-width:640px){ .row-2{grid-template-columns:1fr} }

  .field{margin-bottom:16px}
  .field:last-child{margin-bottom:0}
  .field label.lbl{display:block;font-size:12px;font-weight:600;color:var(--ink);margin-bottom:6px;letter-spacing:.02em}
  .field .req{color:var(--red);margin-left:2px}
  .field .hint{display:block;font-size:11px;color:var(--muted);margin-top:5px}

  .input, .textarea, .select{
    width:100%;font-family:'DM Sans';font-size:13px;padding:10px 14px;border-radius:10px;
    border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s, box-shadow .15s;
  }
  .input:focus, .textarea:focus, .select:focus{border-color:var(--yellow);box-shadow:0 0 0 3px rgba(255,198,26,.15)}
  .textarea{min-height:90px;resize:vertical;font-family:inherit}

  .select{appearance:none;padding-right:38px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center}

  .field.has-error .input, .field.has-error .textarea, .field.has-error .select{border-color:var(--red);background:#FFF7F7}
  .err-inline{display:block;font-size:11px;color:var(--red);margin-top:5px;font-weight:500}

  .form-actions{
    display:flex;gap:10px;align-items:center;justify-content:flex-end;
    padding-top:20px;margin-top:24px;border-top:1px solid var(--line);
  }
</style>

<div class="page-head">
  <div>
    <h1>{{ $mode === 'create' ? 'New Vendor' : 'Edit Vendor' }}</h1>
    <p>Record a subcontractor or supplier your field team works with.</p>
  </div>
  <a href="{{ route('admin.vendors.index') }}" class="btn btn-ghost">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Back
  </a>
</div>

<form method="POST"
      action="{{ $mode === 'create'
                   ? route('admin.vendors.store')
                   : route('admin.vendors.update', $vendor) }}"
      class="form-card">
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  <div class="section">
    <div class="section-title">Vendor identity</div>
    <div class="section-sub">Who they are and how your tax records refer to them.</div>

    <div class="field {{ $errors->has('name') ? 'has-error' : '' }}">
      <label for="name" class="lbl">Vendor name <span class="req">*</span></label>
      <input type="text" id="name" name="name" maxlength="180" required
             value="{{ old('name', $vendor->name) }}" class="input"
             placeholder="e.g. Siemens Industrial Services, Mehta Electricals, Rajesh Enterprises">
      @error('name') <small class="err-inline">{{ $message }}</small> @enderror
    </div>

    <div class="field {{ $errors->has('tax_id') ? 'has-error' : '' }}">
      <label for="tax_id" class="lbl">Tax ID / GSTIN</label>
      <input type="text" id="tax_id" name="tax_id" maxlength="60"
             value="{{ old('tax_id', $vendor->tax_id) }}" class="input"
             placeholder="e.g. 29ABCDE1234F1Z5" style="text-transform:uppercase">
      <small class="hint">Optional. Shown on POs and reconciled against vendor invoices.</small>
      @error('tax_id') <small class="err-inline">{{ $message }}</small> @enderror
    </div>
  </div>

  <div class="section">
    <div class="section-title">Primary contact</div>
    <div class="section-sub">Who to call for quotes, scheduling and dispatch coordination.</div>

    <div class="field {{ $errors->has('contact_name') ? 'has-error' : '' }}">
      <label for="contact_name" class="lbl">Contact person</label>
      <input type="text" id="contact_name" name="contact_name" maxlength="150"
             value="{{ old('contact_name', $vendor->contact_name) }}" class="input"
             placeholder="e.g. Rakesh Nair">
      @error('contact_name') <small class="err-inline">{{ $message }}</small> @enderror
    </div>

    <div class="row-2">
      <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
        <label for="email" class="lbl">Email</label>
        <input type="email" id="email" name="email" maxlength="190"
               value="{{ old('email', $vendor->email) }}" class="input"
               placeholder="sales@example.com">
        @error('email') <small class="err-inline">{{ $message }}</small> @enderror
      </div>

      <div class="field {{ $errors->has('phone') ? 'has-error' : '' }}">
        <label for="phone" class="lbl">Phone</label>
        <input type="text" id="phone" name="phone" maxlength="40"
               value="{{ old('phone', $vendor->phone) }}" class="input"
               placeholder="+91 98765 43210">
        @error('phone') <small class="err-inline">{{ $message }}</small> @enderror
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Status &amp; notes</div>
    <div class="section-sub">Keep private remarks for your team — payment terms, past issues, speciality.</div>

    <div class="field {{ $errors->has('status') ? 'has-error' : '' }}">
      <label for="status" class="lbl">Status <span class="req">*</span></label>
      <select id="status" name="status" class="select" required>
        <option value="active"    @selected(old('status', $vendor->status) === 'active')>Active — appears in vendor pickers</option>
        <option value="suspended" @selected(old('status', $vendor->status) === 'suspended')>Suspended — hidden from new SRs/POs</option>
      </select>
      @error('status') <small class="err-inline">{{ $message }}</small> @enderror
    </div>

    <div class="field {{ $errors->has('notes') ? 'has-error' : '' }}">
      <label for="notes" class="lbl">Internal notes</label>
      <textarea id="notes" name="notes" class="textarea" maxlength="5000"
                placeholder="e.g. Net-30 payment terms. Specializes in Siemens S7 PLCs and Schneider VFDs. Call Rakesh before 6pm.">{{ old('notes', $vendor->notes) }}</textarea>
      <small class="hint">Visible only to admins. Not shared with the vendor or shown on documents.</small>
      @error('notes') <small class="err-inline">{{ $message }}</small> @enderror
    </div>
  </div>

  <div class="form-actions">
    <a href="{{ route('admin.vendors.index') }}" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      {{ $mode === 'create' ? 'Create vendor' : 'Save changes' }}
    </button>
  </div>
</form>
@endsection