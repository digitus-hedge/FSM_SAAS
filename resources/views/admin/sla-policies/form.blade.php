@extends('layouts.admin')
@section('title', $mode === 'create' ? 'New SLA Policy' : 'Edit SLA Policy')
@section('hide_layout_errors', true)

@section('content')
<style>
  .page-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
  .page-head h1{font-family:var(--font-display);font-size:26px;font-weight:700;letter-spacing:-.3px}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-body);font-size:13px;font-weight:600;padding:10px 18px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:color-mix(in srgb, var(--yellow) 85%, black 15%)}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .form-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:28px;margin:0 auto}
  .section{padding-bottom:22px;margin-bottom:22px;border-bottom:1px solid var(--line)}
  .section:last-of-type{border-bottom:0;padding-bottom:0;margin-bottom:0}
  .section-title{font-family:var(--font-display);font-size:14px;font-weight:700;color:var(--ink);margin-bottom:4px}
  .section-sub{font-size:12px;color:var(--muted);margin-bottom:14px}

  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 20px}
  .field{display:flex;flex-direction:column;gap:6px}
  .field-wide{grid-column:span 2}
  .field label{font-size:12px;font-weight:600;color:var(--ink)}
  .req{color:var(--red)}
  .hint{font-size:11px;color:var(--muted);margin-top:2px}
  .hint.live{color:var(--amber);font-weight:600}
  .err{font-size:11px;color:var(--red);margin-top:2px;font-weight:500;display:flex;align-items:center;gap:4px}
  .err::before{content:'⚠';font-size:12px}

  .input,select.input{font-family:var(--font-body);font-size:13px;padding:10px 14px;border-radius:10px;border:1px solid var(--line);background:#FCFBF8;color:var(--ink);outline:none;width:100%;transition:border .15s,background .15s}
  .input:focus,select.input:focus{border-color:var(--yellow);background:#fff;box-shadow:0 0 0 3px color-mix(in srgb, var(--yellow) 15%, transparent)}
  .input.has-error{border-color:var(--red);background:#fff}
  select.input{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}

  .time-row{display:flex;gap:6px;align-items:center}
  .time-row input{flex:1}
  .time-row .unit-select{width:120px;flex:0 0 auto}

  .quick-pills{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
  .quick-pill{font-size:11px;font-weight:600;padding:4px 10px;border-radius:999px;border:1px solid var(--line);background:#fff;cursor:pointer;color:var(--muted);transition:all .12s}
  .quick-pill:hover{border-color:var(--yellow);color:var(--amber)}

  .active-row{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:#FCFBF8}
  .active-row input[type="checkbox"]{appearance:none;-webkit-appearance:none;width:18px;height:18px;border:1.5px solid var(--line);border-radius:5px;background:#fff;cursor:pointer;position:relative;flex-shrink:0}
  .active-row input[type="checkbox"]:checked{background:var(--yellow);border-color:var(--yellow)}
  .active-row input[type="checkbox"]:checked::after{content:'';position:absolute;left:5px;top:1px;width:5px;height:10px;border:solid var(--ink);border-width:0 2px 2px 0;transform:rotate(45deg)}
  .active-row label{font-size:13px;cursor:pointer}

  .form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--line)}
</style>

<div class="page-head">
  <h1>{{ $mode === 'create' ? 'New SLA policy' : 'Edit SLA policy' }}</h1>
  <a href="{{ route('admin.sla-policies.index') }}" class="btn btn-ghost">← Back</a>
</div>

<form method="POST"
      action="{{ $mode === 'create' ? route('admin.sla-policies.store') : route('admin.sla-policies.update', $policy) }}"
      class="form-card" novalidate id="sla-form">
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  {{-- SECTION 1 — Scope --}}
  <div class="section">
    <div class="section-title">Scope</div>
    <div class="section-sub">Which category and priority this policy applies to. One row per (category + priority) — tenant-wide defaults use "Any category".</div>

    <div class="form-grid">
      <div class="field">
        <label for="service_category_id">Service Category</label>
        <select id="service_category_id" name="service_category_id"
                class="input @error('service_category_id') has-error @enderror">
          <option value="">— Select Service Category —</option>
          @foreach ($categories as $cat)
            <option value="{{ $cat->id }}" @selected((string) old('service_category_id', $policy->service_category_id) === (string) $cat->id)>
              {{ $cat->name }} ({{ $cat->code }})
            </option>
          @endforeach
        </select>
        @error('service_category_id')<span class="err">{{ $message }}</span>
        @else<span class="hint">Leave empty to make this the tenant-wide default for this priority.</span>@enderror
      </div>

      <div class="field">
        <label for="priority">Priority <span class="req">*</span></label>
        <select id="priority" name="priority" required
                class="input @error('priority') has-error @enderror">
          @foreach ($priorities as $value => $label)
            <option value="{{ $value }}" @selected(old('priority', $policy->priority) === $value)>{{ $label }}</option>
          @endforeach
        </select>
        @error('priority')<span class="err">{{ $message }}</span>@enderror
      </div>
    </div>
  </div>

  {{-- SECTION 2 — Response time --}}
  <div class="section">
    <div class="section-title">Response time</div>
    <div class="section-sub">How long until a technician must punch in (first response).</div>

    <div class="field">
      <label for="response_minutes">Response time (minutes) <span class="req">*</span></label>
      <input type="number" id="response_minutes" name="response_minutes"
             min="1" max="525600" step="1" required
             value="{{ old('response_minutes', $policy->response_minutes) }}"
             class="input @error('response_minutes') has-error @enderror" />
      @error('response_minutes')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint live" id="response-human">—</span>
      @enderror
      <div class="quick-pills">
        <button type="button" class="quick-pill" onclick="setMin('response_minutes',30)">30 min</button>
        <button type="button" class="quick-pill" onclick="setMin('response_minutes',60)">1 hour</button>
        <button type="button" class="quick-pill" onclick="setMin('response_minutes',120)">2 hours</button>
        <button type="button" class="quick-pill" onclick="setMin('response_minutes',480)">8 hours</button>
        <button type="button" class="quick-pill" onclick="setMin('response_minutes',1440)">1 day</button>
      </div>
    </div>
  </div>

  {{-- SECTION 3 — Resolution time --}}
  <div class="section">
    <div class="section-title">Resolution time</div>
    <div class="section-sub">How long until the job must be marked complete. Must be ≥ response time.</div>

    <div class="field">
      <label for="resolution_minutes">Resolution time (minutes) <span class="req">*</span></label>
      <input type="number" id="resolution_minutes" name="resolution_minutes"
             min="1" max="525600" step="1" required
             value="{{ old('resolution_minutes', $policy->resolution_minutes) }}"
             class="input @error('resolution_minutes') has-error @enderror" />
      @error('resolution_minutes')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint live" id="resolution-human">—</span>
      @enderror
      <div class="quick-pills">
        <button type="button" class="quick-pill" onclick="setMin('resolution_minutes',120)">2 hours</button>
        <button type="button" class="quick-pill" onclick="setMin('resolution_minutes',240)">4 hours</button>
        <button type="button" class="quick-pill" onclick="setMin('resolution_minutes',480)">8 hours</button>
        <button type="button" class="quick-pill" onclick="setMin('resolution_minutes',1440)">1 day</button>
        <button type="button" class="quick-pill" onclick="setMin('resolution_minutes',2880)">2 days</button>
        <button type="button" class="quick-pill" onclick="setMin('resolution_minutes',10080)">1 week</button>
      </div>
    </div>
  </div>

  {{-- SECTION 4 — Clock --}}
  <div class="section">
    <div class="section-title">SLA clock behaviour</div>
    <div class="section-sub">Whether the clock pauses outside business hours and on holidays.</div>

    <div class="active-row">
      <input type="hidden" name="uses_business_hours" value="0">
      <input type="checkbox" name="uses_business_hours" id="uses_business_hours" value="1"
             @checked(old('uses_business_hours', $policy->exists ? $policy->uses_business_hours : true))>
      <label for="uses_business_hours">
        Only count time inside business hours and non-holidays
      </label>
    </div>
    <span class="hint" style="margin-top:8px;display:block">
      Off = 24 × 7 clock (used for Critical / Emergency). On = clock pauses evenings, weekends and holidays.
    </span>
  </div>

  <div class="form-actions">
    <a href="{{ route('admin.sla-policies.index') }}" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      {{ $mode === 'create' ? 'Create policy' : 'Save changes' }}
    </button>
  </div>
</form>

<script>
function humanDuration(mins) {
  mins = parseInt(mins, 10);
  if (!mins || mins < 1) return '—';
  const d = Math.floor(mins / 1440);
  const h = Math.floor((mins % 1440) / 60);
  const m = mins % 60;
  const parts = [];
  if (d) parts.push(d + 'd');
  if (h) parts.push(h + 'h');
  if (m) parts.push(m + 'm');
  return '= ' + parts.join(' ');
}

function setMin(id, val) {
  const el = document.getElementById(id);
  el.value = val;
  el.dispatchEvent(new Event('input'));
}

['response_minutes', 'resolution_minutes'].forEach(id => {
  const el    = document.getElementById(id);
  const human = document.getElementById(id === 'response_minutes' ? 'response-human' : 'resolution-human');
  if (!el || !human) return;
  const sync = () => human.textContent = humanDuration(el.value);
  el.addEventListener('input', sync);
  sync();
});

document.getElementById('sla-form').addEventListener('submit', function (e) {
  const response   = parseInt(document.getElementById('response_minutes').value, 10);
  const resolution = parseInt(document.getElementById('resolution_minutes').value, 10);
  if (response && resolution && resolution < response) {
    e.preventDefault();
    if (window.Swal) {
      Swal.fire({
        icon: 'warning',
        title: 'Resolution time too short',
        text: 'Resolution time must be greater than or equal to response time.'
      });
    } else {
      alert('Resolution time must be greater than or equal to response time.');
    }
  }
});
</script>
@endsection