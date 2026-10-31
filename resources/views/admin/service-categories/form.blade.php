@extends('layouts.admin')
@section('title', $mode === 'create' ? 'New Category' : 'Edit Category')
@section('hide_layout_errors', true)

@section('content')
<style>
  .page-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px
  }

  .page-head h1 {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 700;
    letter-spacing: -.3px
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 600;
    padding: 10px 18px;
    border-radius: 999px;
    border: 1px solid transparent;
    text-decoration: none;
    cursor: pointer;
    transition: background .15s
  }

  .btn-primary {
    background: var(--yellow);
    color: var(--ink)
  }

  .btn-primary:hover {
    background: color-mix(in srgb, var(--yellow) 85%, black 15%)
  }

  .btn-ghost {
    background: #fff;
    color: var(--ink);
    border-color: var(--line)
  }

  .btn-ghost:hover {
    background: var(--soft)
  }

  .form-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 28px;
    margin: 0 auto
  }

  .section {
    padding-bottom: 22px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--line)
  }

  .section:last-of-type {
    border-bottom: 0;
    padding-bottom: 0;
    margin-bottom: 0
  }

  .section-title {
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 4px
  }

  .section-sub {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 14px
  }

  .form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px 20px
  }

  .field {
    display: flex;
    flex-direction: column;
    gap: 6px
  }

  .field-wide {
    grid-column: span 2
  }

  .field label {
    font-size: 12px;
    font-weight: 600;
    color: var(--ink);
    letter-spacing: .01em
  }

  .req {
    color: var(--red)
  }

  .hint {
    font-size: 11px;
    color: var(--muted);
    margin-top: 2px
  }

  .err {
    font-size: 11px;
    color: var(--red);
    margin-top: 2px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px
  }

  .err::before {
    content: '⚠';
    font-size: 12px
  }

  .input,
  select.input,
  textarea.input {
    font-family: var(--font-body);
    font-size: 13px;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid var(--line);
    background: #FCFBF8;
    color: var(--ink);
    outline: none;
    transition: border .15s, background .15s;
    width: 100%
  }

  .input:focus,
  select.input:focus,
  textarea.input:focus {
    border-color: var(--yellow);
    background: #fff;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--yellow) 15%, transparent)
  }

  .input.has-error {
    border-color: var(--red);
    background: #fff
  }

  .input.upper {
    text-transform: uppercase;
    letter-spacing: .03em
  }

  select.input {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 34px
  }

  /* Flag cards — clickable boolean toggles */
  .flag-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px
  }

  .flag-card {
    display: flex;
    gap: 12px;
    padding: 14px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: #FCFBF8;
    cursor: pointer;
    transition: all .12s
  }

  .flag-card:hover {
    border-color: var(--muted)
  }

  .flag-card input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border: 1.5px solid var(--line);
    border-radius: 5px;
    background: #fff;
    cursor: pointer;
    position: relative;
    flex-shrink: 0;
    margin-top: 1px
  }

  .flag-card input[type="checkbox"]:checked {
    background: var(--yellow);
    border-color: var(--yellow)
  }

  .flag-card input[type="checkbox"]:checked::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 1px;
    width: 5px;
    height: 11px;
    border: solid var(--ink);
    border-width: 0 2px 2px 0;
    transform: rotate(45deg)
  }

  .flag-card:has(input:checked) {
    background: #FFFCF2;
    border-color: var(--yellow)
  }

  .flag-body {
    flex: 1;
    min-width: 0
  }

  .flag-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    line-height: 1.2
  }

  .flag-desc {
    font-size: 11px;
    color: var(--muted);
    margin-top: 3px
  }

  .active-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: #FCFBF8
  }

  .active-row input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    border: 1.5px solid var(--line);
    border-radius: 5px;
    background: #fff;
    cursor: pointer;
    position: relative;
    flex-shrink: 0
  }

  .active-row input[type="checkbox"]:checked {
    background: var(--yellow);
    border-color: var(--yellow)
  }

  .active-row input[type="checkbox"]:checked::after {
    content: '';
    position: absolute;
    left: 5px;
    top: 1px;
    width: 5px;
    height: 10px;
    border: solid var(--ink);
    border-width: 0 2px 2px 0;
    transform: rotate(45deg)
  }

  .active-row label {
    font-size: 13px;
    cursor: pointer;
    font-weight: 400
  }

  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--line)
  }

  #service_domain::-webkit-calendar-picker-indicator{
  display:none !important;
  opacity:0;
}
#service_domain::-webkit-list-button{
  display:none !important;
}
</style>

<div class="page-head">
  <h1>{{ $mode === 'create' ? 'New service category' : 'Edit '.$category->name }}</h1>
  <a href="{{ route('admin.service-categories.index') }}" class="btn btn-ghost">← Back</a>
</div>

<form method="POST"
  action="{{ $mode === 'create' ? route('admin.service-categories.store') : route('admin.service-categories.update', $category) }}"
  class="form-card" novalidate>
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  {{-- SECTION 1 — Identity --}}
  <div class="section">
    <div class="section-title">Identity</div>
    <div class="section-sub">What this category is called and what domain it belongs to.</div>

    <div class="form-grid">

      <div class="field">
        <label for="name">Name <span class="req">*</span></label>
        <input id="name" name="name" value="{{ old('name', $category->name) }}"
          maxlength="120" required minlength="2"
          class="input @error('name') has-error @enderror" />
        @error('name')<span class="err">{{ $message }}</span>@enderror
      </div>

      <div class="field field-wide">
        <label for="service_domain">Service domain</label>
        <input id="service_domain" name="service_domain" value="{{ old('service_domain', $category->service_domain) }}"
          maxlength="80" list="domain-suggestions"
          class="input @error('service_domain') has-error @enderror" />
        <datalist id="domain-suggestions">
          <option value="Industrial Automation">
          <option value="PLC Programming">
          <option value="VFD Services">
          <option value="Maintenance">
          <option value="Commissioning">
        </datalist>
        @error('service_domain')<span class="err">{{ $message }}</span>
        @else<span class="hint">Optional grouping. Example: Industrial Automation</span>@enderror
      </div>
    </div>
  </div>

  {{-- SECTION 2 — Approval --}}
  <div class="section">
    <div class="section-title">Approval</div>
    <div class="section-sub">How a service request of this category becomes Approved.</div>

   <div class="field">
  <label for="approval_mode">Approval mode <span class="req">*</span></label>
  <select id="approval_mode" name="approval_mode" required
          class="input @error('approval_mode') has-error @enderror">
    <option value="manual" selected>Manual approval by Ops Manager</option>
    @foreach ($approvalModes as $value => $label)
      @if ($value !== 'manual')
        <option value="{{ $value }}" disabled>{{ $label }} — Available later</option>
      @endif
    @endforeach
  </select>
  <span class="hint">Only Manual approval is available in this release.</span>
  @error('approval_mode')<span class="err">{{ $message }}</span>@enderror
</div>
  </div>

  {{-- SECTION 3 — Proof rules --}}
  <div class="section">
    <div class="section-title">Proof rules</div>
    <div class="section-sub">What technicians must capture before a job can be marked complete. Frozen on each SR at creation.</div>

    <div class="flag-grid">

      <label class="flag-card">
        <input type="hidden" name="require_before_photo" value="0">
        <input type="checkbox" name="require_before_photo" value="1"
          @checked(old('require_before_photo', $category->require_before_photo))>
        <div class="flag-body">
          <div class="flag-title">Before photo</div>
          <div class="flag-desc">Technician must capture a before-work photo.</div>
        </div>
      </label>

      <label class="flag-card">
        <input type="hidden" name="require_after_photo" value="0">
        <input type="checkbox" name="require_after_photo" value="1"
          @checked(old('require_after_photo', $category->require_after_photo))>
        <div class="flag-body">
          <div class="flag-title">After photo</div>
          <div class="flag-desc">Technician must capture an after-work photo.</div>
        </div>
      </label>

      <label class="flag-card">
        <input type="hidden" name="require_signature" value="0">
        <input type="checkbox" name="require_signature" value="1"
          @checked(old('require_signature', $category->require_signature))>
        <div class="flag-body">
          <div class="flag-title">Customer signature</div>
          <div class="flag-desc">Customer must sign on completion.</div>
        </div>
      </label>

      <!-- <label class="flag-card">
        <input type="hidden" name="require_checklist" value="0">
        <input type="checkbox" name="require_checklist" value="1"
               @checked(old('require_checklist', $category->require_checklist))>
        <div class="flag-body">
          <div class="flag-title">Checklist</div>
          <div class="flag-desc">Attached form checklist must be filled (Release B forms).</div>
        </div>
      </label> -->

      <label class="flag-card" style="opacity:.55;cursor:not-allowed">
        <input type="hidden" name="require_checklist" value="0">
        <input type="checkbox" name="require_checklist" value="1" disabled>
        <div class="flag-body">
          <div class="flag-title">Checklist required <span style="font-weight:400;color:#5F5D6E">(Available later)</span></div>
          <div class="flag-desc">Forms arrive in Release B.</div>
        </div>
      </label>


      <label class="flag-card">
        <input type="hidden" name="invoice_mandatory" value="0">
        <input type="checkbox" name="invoice_mandatory" value="1"
          @checked(old('invoice_mandatory', $category->invoice_mandatory))>
        <div class="flag-body">
          <div class="flag-title">Invoice mandatory</div>
          <div class="flag-desc">SR cannot be marked Completed until an invoice exists.</div>
        </div>
      </label>

    </div>
  </div>

  {{-- SECTION 4 — Batch & depot --}}


  {{-- SECTION 5 — Status --}}


  <div class="form-actions">
    <a href="{{ route('admin.service-categories.index') }}" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      {{ $mode === 'create' ? 'Create category' : 'Save changes' }}
    </button>
  </div>
</form>
@endsection