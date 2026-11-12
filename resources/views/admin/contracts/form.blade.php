@extends('layouts.admin')

@php
    $mode = $mode ?? ($contract->exists ? 'edit' : 'create');
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : '';
    $locked = $contract->exists && $contract->isLocked();
    $isDraft = ! $contract->exists || $contract->status === 'draft';
    $showMoney = $showMoney ?? false;
    $statusClass = ['draft' => 'status-off', 'active' => 'status-ok', 'expiring_soon' => 'status-warn', 'expired' => 'status-bad', 'renewed' => 'status-off', 'cancelled' => 'status-off'];
@endphp

@section('title', $mode === 'create' ? 'New ' . strtolower($label) : $contract->contract_number)
@section('hide_layout_errors', true)

@section('content')
<style>
  .crumb{font-size:13px;color:#5F5D6E;margin-bottom:12px}
  .crumb a{color:#0F0F17;text-decoration:underline;text-underline-offset:3px}
  .page-head h1{font-family:var(--font-display),sans-serif;font-size:34px;font-weight:800;line-height:1.08;letter-spacing:-.02em;margin:0;color:#0F0F17}
  .meta-row{display:flex;gap:8px;align-items:center;margin-top:12px;margin-bottom:24px}
  .code-pill{display:inline-block;font-family:var(--font-body);font-size:12.5px;font-weight:600;color:var(--amber);background:var(--yellow-soft);padding:5px 11px;border-radius:7px}
  .status{display:inline-flex;align-items:center;font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:999px}
  .status-ok{background:#E6F7EC;color:#1D7A3A}
  .status-warn{background:#FEF3C7;color:#92400E}
  .status-off{background:#F2F1F6;color:#5F5D6E}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:var(--font-body);font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:all .12s;white-space:nowrap}
  .btn-primary{background:var(--amber);color:#fff}
  .btn-primary:hover{background:color-mix(in srgb, var(--amber) 85%, black 15%)}
  .btn-ghost{background:#fff;color:#0F0F17;border-color:#E5E4EC}
  .btn-ghost:hover{border-color:#0F0F17}
  .btn-danger-out{background:#fff;color:#C7372F;border-color:#F0C7C7}
  .btn-danger-out:hover{background:#FFF5F5;border-color:#C7372F}
  .card{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:24px;max-width:860px}
  .fld{margin-bottom:20px;display:flex;flex-direction:column}
  .fld label{font-weight:600;font-size:14px;margin-bottom:8px;color:#0F0F17}
  .req{color:#C7372F}
  .inp,.sel,.txt{width:100%;font-family:var(--font-body);font-size:14px;padding:12px 14px;border-radius:10px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;outline:none;transition:border .15s, box-shadow .15s;box-sizing:border-box}
  .inp:focus,.sel:focus,.txt:focus{border-color:var(--amber);box-shadow:0 0 0 3px color-mix(in srgb, var(--amber) 15%, transparent)}
  .inp.err,.sel.err,.txt.err{border-color:#C7372F;background:#FFF7F7}
  .txt{min-height:88px;resize:vertical;font-family:inherit}
  .sel{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}
  .hint{font-size:12.5px;color:#5F5D6E;margin-top:6px}
  .errtxt{font-size:12.5px;color:#C7372F;margin-top:6px;font-weight:600}
  .two{display:grid;grid-template-columns:1fr 1fr;gap:20px}
  @media (max-width:600px){.two{grid-template-columns:1fr}}
  .formact{display:flex;gap:10px;align-items:center;margin-top:24px;flex-wrap:wrap}
  .alert-err{background:#FDECE7;border:1px solid #F2C2C2;color:#C2381B;padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px;max-width:860px}
  .alert-info{background:#F0F4FE;border:1px solid #D7E0FA;color:var(--amber);padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px;max-width:860px}
  fieldset{border:0;padding:0;margin:0;min-width:0}

  .checks{border:1px solid #E5E4EC;border-radius:10px;padding:10px 14px;max-height:220px;overflow-y:auto;background:#fff}
  .checks label{display:flex;align-items:center;gap:10px;font-weight:500;font-size:14px;margin:0;padding:6px 0;cursor:pointer}
  .checks input{width:16px;height:16px;accent-color:var(--amber)}
  .three{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px}
  @media (max-width:700px){.three{grid-template-columns:1fr}}
  .sect{font-family:var(--font-display),sans-serif;font-size:16px;font-weight:800;margin:6px 0 14px;color:#0F0F17}
  .sepline{border:0;border-top:1px solid #EEEDF3;margin:8px 0 22px}
  .status-bad{background:#FDECE7;color:#C2381B}
</style>

<div class="crumb">
  <a href="{{ route('admin.contracts.index') }}">{{ $plural }}</a> / {{ $mode === 'create' ? 'New ' . strtolower($label) : $contract->contract_number }}
</div>

<div class="page-head">
  <h1>{{ $mode === 'create' ? 'New ' . strtolower($label) : $contract->contract_number }}</h1>
  @if ($mode === 'edit')
    <div class="meta-row">
      <span class="code-pill">{{ $contract->contract_number }}</span>
      <span class="status {{ $statusClass[$contract->status] ?? 'status-off' }}">{{ $contract->status_label }}</span>
      <span class="code-pill" style="background:#F2F1F6;color:#5F5D6E">Calls used: {{ $contract->calls_used_current_period }}{{ $contract->max_calls ? ' of ' . $contract->max_calls : '' }}</span>
    </div>
  @endif
</div>

@if (session('error'))
  <div class="alert-err">{{ session('error') }}</div>
@endif
@if (session('success'))
  <div class="alert-info">{{ session('success') }}</div>
@endif
@if ($errors->any())
  <div class="alert-err">Please fix the highlighted fields and save again.</div>
@endif

<form method="POST"
      action="{{ $mode === 'create' ? route('admin.contracts.store') : route('admin.contracts.update', $contract) }}"
      id="contract-form" enctype="multipart/form-data" novalidate>
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  <div class="card">

    <div class="sect">Basics</div>
    <div class="two">
      <div class="fld">
        <label for="client_id">Client <span class="req">*</span></label>
        <select id="client_id" name="client_id" required @disabled($locked) class="sel @error('client_id') err @enderror">
          <option value="">Choose a client</option>
          @foreach ($clients as $c)
            <option value="{{ $c->id }}" @selected((string) old('client_id', $contract->client_id) === (string) $c->id)>{{ $c->name }}</option>
          @endforeach
        </select>
        @error('client_id')<div class="errtxt">{{ $message }}</div>@else
          @if ($locked)<div class="hint">The client and the type cannot be changed after the contract is active.</div>@endif
        @enderror
      </div>

      <div class="fld">
        <label>Number</label>
        <input class="inp" value="{{ $mode === 'edit' ? $contract->contract_number : 'Assigned when you save' }}" disabled>
        <div class="hint">Numbers come from the company's numbering sequence and do not change.</div>
      </div>
    </div>

    <div class="three">
      <div class="fld">
        <label for="contract_type">Type <span class="req">*</span></label>
        <select id="contract_type" name="contract_type" required @disabled($locked) class="sel @error('contract_type') err @enderror">
          @foreach (\App\Models\Contract::TYPES as $k => $v)
            <option value="{{ $k }}" @selected(old('contract_type', $contract->contract_type) === $k)>{{ $v }}</option>
          @endforeach
        </select>
        @error('contract_type')<div class="errtxt">{{ $message }}</div>@enderror
      </div>
      <div class="fld">
        <label>Status</label>
        <div style="padding:9px 0"><span class="status {{ $statusClass[$contract->status ?: 'draft'] ?? 'status-off' }}">{{ \App\Models\Contract::STATUSES[$contract->status ?: 'draft'] }}</span></div>
        <div class="hint">Use Activate, Renew and Cancel to change the status.</div>
      </div>
      <div class="fld">
        <label>&nbsp;</label>
        <label style="display:flex;align-items:center;gap:10px;font-weight:600;margin:12px 0 0">
          <input type="hidden" name="auto_renew" value="0">
          <input type="checkbox" name="auto_renew" value="1" style="width:16px;height:16px;accent-color:var(--amber)" @checked(old('auto_renew', $contract->auto_renew))>
          Auto-renew
        </label>
        @error('auto_renew')<div class="errtxt">{{ $message }}</div>@enderror
      </div>
    </div>

    <div class="two">
        <div class="fld">
          <label for="start_date">Start date <span class="req">*</span></label>
          <input type="date" id="start_date" name="start_date" value="{{ $date(old('start_date', $contract->start_date)) }}"  class="inp @error('start_date') err @enderror">
          @error('start_date')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
        <div class="fld">
          <label for="end_date">End date <span class="req">*</span></label>
          <input type="date" id="end_date" name="end_date" value="{{ $date(old('end_date', $contract->end_date)) }}"  class="inp @error('end_date') err @enderror">
          @error('end_date')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
    </div>

    <hr class="sepline">
    <div class="sect">Service levels and call cap</div>
    <div class="two">
        <div class="fld">
          <label for="response_sla_minutes">Response SLA (minutes)</label>
          <input type="number" id="response_sla_minutes" name="response_sla_minutes" value="{{ old('response_sla_minutes', $contract->response_sla_minutes) }}" min="1" class="inp @error('response_sla_minutes') err @enderror">
          @error('response_sla_minutes')<div class="errtxt">{{ $message }}</div>@else<div class="hint">Leave empty to use the normal SLA policy.</div>@enderror
        </div>
        <div class="fld">
          <label for="resolution_sla_minutes">Resolution SLA (minutes)</label>
          <input type="number" id="resolution_sla_minutes" name="resolution_sla_minutes" value="{{ old('resolution_sla_minutes', $contract->resolution_sla_minutes) }}" min="1" class="inp @error('resolution_sla_minutes') err @enderror">
          @error('resolution_sla_minutes')<div class="errtxt">{{ $message }}</div>@else<div class="hint">Leave empty to use the normal SLA policy.</div>@enderror
        </div>
    </div>
    <div class="two">
        <div class="fld">
          <label for="max_calls">Call cap</label>
          <input type="number" id="max_calls" name="max_calls" value="{{ old('max_calls', $contract->max_calls) }}" min="1" class="inp @error('max_calls') err @enderror">
          @error('max_calls')<div class="errtxt">{{ $message }}</div>@else<div class="hint">Leave empty for no cap.</div>@enderror
        </div>
      <div class="fld">
        <label for="call_period">Call period <span class="req">*</span></label>
        <select id="call_period" name="call_period" class="sel @error('call_period') err @enderror">
          @foreach (\App\Models\Contract::CALL_PERIODS as $k => $v)
            <option value="{{ $k }}" @selected(old('call_period', $contract->call_period) === $k)>{{ $v }}</option>
          @endforeach
        </select>
        @error('call_period')<div class="errtxt">{{ $message }}</div>@enderror
      </div>
    </div>

    @if ($showMoney)
    <hr class="sepline">
    <div class="sect">Value and payment</div>
    <div class="two">
        <div class="fld">
          <label for="annual_value">Annual value</label>
          <input type="number" id="annual_value" name="annual_value" value="{{ old('annual_value', $contract->annual_value) }}" min="0" step="0.001" class="inp @error('annual_value') err @enderror">
          @error('annual_value')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
        <div class="fld">
          <label for="payment_terms">Payment terms</label>
          <input type="text" id="payment_terms" name="payment_terms" value="{{ old('payment_terms', $contract->payment_terms) }}" maxlength="120" class="inp @error('payment_terms') err @enderror">
          @error('payment_terms')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
    </div>

    @endif

    <hr class="sepline">
    <div class="sect">What this {{ strtolower($label) }} covers</div>
    <div class="two">
      <div class="fld">
        <label>Covered sites</label>
        <div class="checks" id="site-checks">
          @forelse ($sites as $s)
            <label data-client="{{ $s->client_id }}">
              <input type="checkbox" name="site_ids[]" value="{{ $s->id }}" @checked(in_array($s->id, $coveredSites))>
              {{ $s->name }}@if ($s->site_code) <span class="cell-muted" style="color:#9A98A7">· {{ $s->site_code }}</span>@endif
            </label>
          @empty
            <div class="hint">No client sites yet.</div>
          @endforelse
          <div class="hint" id="site-empty" style="display:none">Choose a client to see its sites.</div>
        </div>
        @error('site_ids')<div class="errtxt">{{ $message }}</div>@else
          <div class="hint">Tick none to cover all of the client's sites.</div>
        @enderror
      </div>

      <div class="fld">
        <label>Covered categories</label>
        <div class="checks">
          @foreach ($categories as $cat)
            <label>
              <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $coveredCats))>
              {{ $cat->name }}
            </label>
          @endforeach
        </div>
        @error('category_ids')<div class="errtxt">{{ $message }}</div>@else
          <div class="hint">Tick none to cover all categories.</div>
        @enderror
      </div>
    </div>

    <div class="hint" id="cover-line" style="margin:-6px 0 18px"></div>

    <div class="fld">
      <label for="notes">Notes</label>
      <textarea id="notes" name="notes" rows="4" maxlength="5000" class="txt @error('notes') err @enderror">{{ old('notes', $contract->notes) }}</textarea>
      @error('notes')<div class="errtxt">{{ $message }}</div>@enderror
    </div>

    @if ($showMoney)
      <hr class="sepline">
      <div class="sect">Documents</div>
      <div class="fld">
        <label for="documents">Attach the signed contract</label>
        <input type="file" id="documents" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="inp @error('documents') err @enderror @error('documents.*') err @enderror">
        @error('documents')<div class="errtxt">{{ $message }}</div>@enderror
        @error('documents.*')<div class="errtxt">{{ $message }}</div>@enderror
        <div class="hint">PDF, JPG or PNG. Up to 5 files of 10 MB each.</div>
        @if ($documents->count())
          <div class="hint" style="margin-top:10px"><strong>{{ $documents->count() }}</strong> already attached: {{ $documents->pluck('original_name')->implode(', ') }}. Remove files from the Documents tab of the contract.</div>
        @endif
      </div>
    @endif

    <div class="formact">
      @if ($isDraft)
        <button type="submit" name="action" value="save_draft" class="btn btn-ghost">Save draft</button>
        <button type="submit" name="action" value="activate" class="btn btn-primary">Activate</button>
      @else
        <button type="submit" name="action" value="save" class="btn btn-primary">Save changes</button>
      @endif
      <a href="{{ $contract->exists ? route('admin.contracts.show', $contract) : route('admin.contracts.index') }}" class="btn btn-ghost">Back</a>
    </div>
  </div>
</form>

<script>
(function () {
  const client = document.getElementById('client_id');
  const labels = Array.from(document.querySelectorAll('#site-checks label[data-client]'));
  const empty  = document.getElementById('site-empty');

  function filterSites() {
    const c = client.value;
    let shown = 0;
    labels.forEach(l => {
      const ok = c && l.dataset.client === c;
      l.style.display = ok ? '' : 'none';
      if (!ok) l.querySelector('input').checked = false;
      if (ok) shown++;
    });
    empty.style.display = (!c && labels.length) ? '' : 'none';
    if (c && !shown && labels.length) { empty.textContent = 'This client has no sites yet.'; empty.style.display = ''; }
  }
  const line = document.getElementById('cover-line');
  function coverLine() {
    const n = document.querySelectorAll('#site-checks input:checked').length;
    line.textContent = n ? ('Covers ' + n + (n === 1 ? ' site' : ' sites')) : "Covers all of this client's sites";
  }
  document.getElementById('site-checks').addEventListener('change', coverLine);
  client.addEventListener('change', () => { filterSites(); coverLine(); });
  filterSites(); coverLine();

  document.querySelectorAll('#contract-form .err').forEach(el => {
    const clear = () => { el.classList.remove('err'); const m = el.parentElement.querySelector('.errtxt'); if (m) m.remove(); };
    el.addEventListener('input', clear); el.addEventListener('change', clear);
  });
})();
</script>
@endsection