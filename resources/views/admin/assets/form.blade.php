@extends('layouts.admin')

@php
    $asset   = $asset ?? new \App\Models\Asset();
    $mode    = $mode  ?? ($asset->exists ? 'edit' : 'create');
    $locked  = $mode === 'edit' && $asset->is_decommissioned;
    $statusLabels = ['active' => 'Active', 'under_repair' => 'Under repair', 'inactive' => 'Inactive'];
    // A decommissioned asset shows its true status (the field is locked anyway).
    $statusOptions = $locked ? $statusLabels + ['decommissioned' => 'Decommissioned'] : $statusLabels;
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : '';
@endphp

@section('title', $mode === 'create' ? 'New ' . strtolower($label) : $asset->name)
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
</style>

<div class="crumb">
  <a href="{{ route('admin.assets.index') }}">{{ $plural }}</a> / {{ $mode === 'create' ? 'New ' . strtolower($label) : $asset->name }}
</div>

<div class="page-head">
  <h1>{{ $mode === 'create' ? 'New ' . strtolower($label) : $asset->name }}</h1>
   @if ($mode === 'edit')
    <div class="meta-row">
      <span class="code-pill">{{ $asset->asset_code }}</span>
      <span class="status {{ $asset->status === 'active' ? 'status-ok' : ($asset->status === 'under_repair' ? 'status-warn' : 'status-off') }}">
        {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
      </span>
      @if (! $locked)
        <a href="{{ route('admin.assets.label', $asset) }}" target="_blank" class="btn btn-ghost" style="padding:6px 14px;font-size:12.5px">Print label</a>
      @endif
    </div>
  @endif
</div>

@if (session('error'))
  <div class="alert-err">{{ session('error') }}</div>
@endif
@if ($locked)
  <div class="alert-info">This {{ strtolower($label) }} is decommissioned. It stays in the history of its requests and can no longer be edited.</div>
@endif

<form method="POST"
      action="{{ $mode === 'create' ? route('admin.assets.store') : route('admin.assets.update', $asset) }}"
      id="asset-form" enctype="multipart/form-data" novalidate>
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  <div class="card">
    <fieldset @disabled($locked)>

      <div class="two">
        <div class="fld">
          <label for="client_id">Client <span class="req">*</span></label>
          <select id="client_id" name="client_id" required class="sel @error('client_id') err @enderror">
            <option value="">Choose a client</option>
            @foreach ($clients as $c)
              <option value="{{ $c->id }}" @selected((string) old('client_id', $asset->client_id) === (string) $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
          @error('client_id')<div class="errtxt">{{ $message }}</div>@enderror
        </div>

        <div class="fld">
          <label for="project_site_id">Site <span class="req">*</span></label>
          <select id="project_site_id" name="project_site_id" required class="sel @error('project_site_id') err @enderror">
            <option value="">Choose a site</option>
            @foreach ($sites as $s)
              <option value="{{ $s->id }}" data-client="{{ $s->client_id }}"
                      @selected((string) old('project_site_id', $asset->project_site_id) === (string) $s->id)>{{ $s->name }}</option>
            @endforeach
          </select>
          @error('project_site_id')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="two">
        <div class="fld">
          <label for="service_category_id">Service category <span class="req">*</span></label>
          <select id="service_category_id" name="service_category_id" required class="sel @error('service_category_id') err @enderror">
            <option value="">Choose a category</option>
            @foreach ($categories as $cat)
              <option value="{{ $cat->id }}" @selected((string) old('service_category_id', $asset->service_category_id) === (string) $cat->id)>{{ $cat->name }}</option>
            @endforeach
          </select>
          @error('service_category_id')<div class="errtxt">{{ $message }}</div>@enderror
        </div>

        <div class="fld">
          <label for="parent_asset_id">Parent {{ strtolower($label) }}</label>
          <select id="parent_asset_id" name="parent_asset_id" class="sel @error('parent_asset_id') err @enderror">
            <option value="">None</option>
          </select>
          @error('parent_asset_id')<div class="errtxt">{{ $message }}</div>@else
            <div class="hint">Only assets at the same site can be a parent.</div>
          @enderror
        </div>
      </div>

      @php
        $formsOn = app(\App\Services\Forms\FormTemplates::class)->enabled(\App\Models\Tenant::current());
      @endphp
      @if ($formsOn)
      <div class="two">
        <div class="fld">
          <label for="form_template_id">Checklist template</label>
          <select id="form_template_id" name="form_template_id" class="sel @error('form_template_id') err @enderror">
            <option value="">Use the category's checklist</option>
            @foreach (\App\Models\FormTemplate::where('status', 'active')->orderBy('name')->get() as $tpl)
              <option value="{{ $tpl->id }}" @selected((string) old('form_template_id', $asset->form_template_id) === (string) $tpl->id)>{{ $tpl->name }}</option>
            @endforeach
          </select>
          @error('form_template_id')<div class="errtxt">{{ $message }}</div>@else
            <div class="hint">Overrides the category's checklist for this asset.</div>
          @enderror
        </div>
      </div>
      @endif

      <div class="fld">
        <label for="name">Name <span class="req">*</span></label>
        <input id="name" name="name" value="{{ old('name', $asset->name) }}" maxlength="180" required class="inp @error('name') err @enderror">
        @error('name')<div class="errtxt">{{ $message }}</div>@enderror
      </div>

      <div class="two">
        <div class="fld">
          <label for="manufacturer">Manufacturer</label>
          <input id="manufacturer" name="manufacturer" value="{{ old('manufacturer', $asset->manufacturer) }}" maxlength="120" class="inp @error('manufacturer') err @enderror">
          @error('manufacturer')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
        <div class="fld">
          <label for="model">Model</label>
          <input id="model" name="model" value="{{ old('model', $asset->model) }}" maxlength="120" class="inp @error('model') err @enderror">
          @error('model')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="two">
        <div class="fld">
          <label for="serial_number">Serial number</label>
          <input id="serial_number" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" maxlength="120" class="inp @error('serial_number') err @enderror">
          @error('serial_number')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
        <div class="fld">
          <label for="location_within_site">Location within site</label>
          <input id="location_within_site" name="location_within_site" value="{{ old('location_within_site', $asset->location_within_site) }}" maxlength="255" placeholder="e.g. Plant room, level 2" class="inp @error('location_within_site') err @enderror">
          @error('location_within_site')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="two">
        <div class="fld">
          <label for="installation_date">Installation date</label>
          <input type="date" id="installation_date" name="installation_date" value="{{ old('installation_date', $date($asset->installation_date)) }}" class="inp @error('installation_date') err @enderror">
          @error('installation_date')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
        <div class="fld">
          <label for="warranty_expiry_date">Warranty expiry</label>
          <input type="date" id="warranty_expiry_date" name="warranty_expiry_date" value="{{ old('warranty_expiry_date', $date($asset->warranty_expiry_date)) }}" class="inp @error('warranty_expiry_date') err @enderror">
          @error('warranty_expiry_date')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="fld">
        <label for="status">Status <span class="req">*</span></label>
        <select id="status" name="status" class="sel @error('status') err @enderror">
          @foreach ($statusOptions as $k => $v)
            <option value="{{ $k }}" @selected(old('status', $asset->status ?: 'active') === $k)>{{ $v }}</option>
          @endforeach
        </select>
        @error('status')<div class="errtxt">{{ $message }}</div>@else
          <div class="hint">To retire an {{ strtolower($label) }}, use Decommission.</div>
        @enderror
      </div>

      @php
        $contractsOn = (bool) \App\Models\Tenant::current()?->hasFeature('contracts');
        // Spec 5.6: Active and Expiring soon contracts in date. The browser narrows the list to the chosen client and site (data-client, data-sites).
        $pinOptions  = $contractsOn
          ? \App\Models\Contract::pinnable(null, null)->with('sites:id')->orderBy('contract_number')->get()
          : collect();
        // A pin the asset already has but that no longer qualifies stays in the list, with a warning (CC-059), so saving does not drop it by accident.
        $pinNow   = $asset->default_contract_id;
        $pinStale = $contractsOn && $pinNow && ! \App\Models\Contract::pinnable($asset->client_id, $asset->project_site_id)->whereKey($pinNow)->exists();
        $stale    = $pinStale ? \App\Models\Contract::withTrashed()->with('sites:id')->find($pinNow) : null;
        if ($stale && ! $pinOptions->contains('id', $stale->id)) { $pinOptions = $pinOptions->push($stale); }
      @endphp
      @if ($contractsOn)
        <div class="fld">
          <label for="default_contract_id">Default contract</label>
          <select id="default_contract_id" name="default_contract_id" class="sel @error('default_contract_id') err @enderror">
            <option value="">None</option>
            @foreach ($pinOptions as $ct)
              <option value="{{ $ct->id }}" data-client="{{ $ct->client_id }}" data-sites="{{ $ct->sites->pluck('id')->join(',') }}"@if ($stale && $ct->id === $stale->id) data-stale="1"@endif
                      @selected((string) old('default_contract_id', $asset->default_contract_id) === (string) $ct->id)>
                {{ $ct->contract_number }} · {{ $ct->type_label }} · ends {{ $ct->end_date->format('d M Y') }}
              </option>
            @endforeach
          </select>
          @error('default_contract_id')<div class="errtxt">{{ $message }}</div>@else
            @if ($pinStale)
              <div class="errtxt">The pinned contract is no longer valid.</div>
            @endif
            <div class="hint">Used without asking while it is valid and covers the request. The manager can still change it.</div>
          @enderror
        </div>
      @endif

      {{-- AST-02: photo, meter settings and specifications --}}
      @php
        $photo    = $asset->photo_attachment_id ? \App\Models\Attachment::find($asset->photo_attachment_id) : null;
        $tracks   = session()->hasOldInput() ? (bool) old('tracks_meter') : (bool) $asset->tracks_meter;
        $specRows = session()->hasOldInput()
            ? (array) old('specs', [])
            : collect($asset->specifications_json ?? [])->map(fn ($r) => ['name' => $r['name'] ?? '', 'value' => $r['value'] ?? ''])->all();
        if (! $specRows) { $specRows = [['name' => '', 'value' => '']]; }
      @endphp

      <div class="fld">
        <label for="photo">Photo</label>
        @if ($photo)
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px">
            <img src="{{ $photo->url }}" alt="Photo of {{ $asset->name }}" style="width:96px;height:72px;object-fit:cover;border-radius:10px;border:1px solid #E5E4EC">
            <label style="font-weight:500"><input type="checkbox" name="remove_photo" value="1" @checked(old('remove_photo'))> Remove this photo</label>
          </div>
        @endif
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="inp @error('photo') err @enderror">
        @error('photo')<div class="errtxt">{{ $message }}</div>@else
          <div class="hint">JPG, PNG or WebP, up to 5 MB. {{ $photo ? 'A new photo replaces the current one.' : '' }}</div>
        @enderror
      </div>

      <div class="fld">
        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
          <input type="checkbox" id="tracks_meter" name="tracks_meter" value="1" @checked($tracks)> Track a meter reading
        </label>
        <div class="hint">For equipment that is serviced by use, such as running hours or kilometres.</div>
      </div>

      <div class="two" id="meter-fields" @if (! $tracks) hidden @endif>
        <div class="fld">
          <label for="meter_unit">Meter unit <span class="req">*</span></label>
          <input id="meter_unit" name="meter_unit" value="{{ old('meter_unit', $asset->meter_unit) }}" maxlength="30" placeholder="e.g. hours, km" class="inp @error('meter_unit') err @enderror" @disabled(! $tracks)>
          @error('meter_unit')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
        <div class="fld">
          <label for="current_meter_reading">Current reading</label>
          <input type="number" step="0.01" min="0" id="current_meter_reading" name="current_meter_reading" value="{{ old('current_meter_reading', $asset->exists ? $asset->current_meter_reading : '') }}" class="inp @error('current_meter_reading') err @enderror" @disabled(! $tracks)>
          @error('current_meter_reading')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="fld" id="specs-box">
        <label>Specifications</label>
        <div id="spec-rows">
          @foreach ($specRows as $i => $row)
            <div class="two spec-row" style="margin-bottom:8px;align-items:center">
              <div>
                <input name="specs[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="60" placeholder="Name, e.g. Capacity" class="inp @error('specs.' . $i . '.name') err @enderror">
                @error('specs.' . $i . '.name')<div class="errtxt">{{ $message }}</div>@enderror
              </div>
              <div style="display:flex;gap:8px">
                <input name="specs[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" maxlength="255" placeholder="Value, e.g. 500 kW" class="inp @error('specs.' . $i . '.value') err @enderror">
                <button type="button" class="btn btn-ghost spec-remove" aria-label="Remove this specification">Remove</button>
              </div>
            </div>
          @endforeach
        </div>
        <button type="button" class="btn btn-ghost" id="spec-add">+ Add a specification</button>
        <div class="hint">Free-form name and value pairs, up to 30.</div>
      </div>

      <div class="fld">
        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="4" maxlength="5000" class="txt @error('notes') err @enderror">{{ old('notes', $asset->notes) }}</textarea>
        @error('notes')<div class="errtxt">{{ $message }}</div>@enderror
      </div>

    </fieldset>

    <div class="formact">
      @if ($mode === 'create')
        <button type="submit" class="btn btn-primary">Create {{ strtolower($label) }}</button>
      @elseif (! $locked)
        <button type="submit" class="btn btn-primary">Save changes</button>
        <button type="button" class="btn btn-danger-out" id="decommission-btn">Decommission</button>
      @endif
      <a href="{{ route('admin.assets.index') }}" class="btn btn-ghost">{{ $locked ? 'Back' : 'Cancel' }}</a>
    </div>
  </div>
</form>

<script>
(function () {
  const box = document.getElementById('tracks_meter');
  const fields = document.getElementById('meter-fields');
  function meter() {
    if (!box || !fields) return;
    fields.hidden = !box.checked;
    fields.querySelectorAll('input').forEach(i => { i.disabled = !box.checked; });
  }
  if (box) { box.addEventListener('change', meter); meter(); }

  const rows = document.getElementById('spec-rows');
  const add = document.getElementById('spec-add');
  if (!rows || !add) return;
  function reindex() {
    rows.querySelectorAll('.spec-row').forEach((row, i) => {
      const inputs = row.querySelectorAll('input');
      inputs[0].name = 'specs[' + i + '][name]';
      inputs[1].name = 'specs[' + i + '][value]';
    });
  }
  add.addEventListener('click', () => {
    if (rows.querySelectorAll('.spec-row').length >= 30) return;
    const first = rows.querySelector('.spec-row');
    const copy = first.cloneNode(true);
    copy.querySelectorAll('input').forEach(i => { i.value = ''; i.classList.remove('err'); });
    copy.querySelectorAll('.errtxt').forEach(e => e.remove());
    rows.appendChild(copy);
    reindex();
  });
  rows.addEventListener('click', e => {
    if (!e.target.classList.contains('spec-remove')) return;
    const all = rows.querySelectorAll('.spec-row');
    if (all.length === 1) { all[0].querySelectorAll('input').forEach(i => { i.value = ''; }); return; }
    e.target.closest('.spec-row').remove();
    reindex();
  });
})();
</script>

@if ($mode === 'edit' && ! $locked)
  {{-- Kept outside the main form: forms cannot be nested. --}}
  <form method="POST" action="{{ route('admin.assets.decommission', $asset) }}"
        class="needs-confirm" id="decommission-form" style="display:none"
        data-confirm-title="Decommission {{ $asset->asset_code }}?"
        data-confirm-text="It can no longer be chosen for new service requests. Its history is kept and it cannot be edited afterwards."
        data-confirm-ok="Yes, decommission" data-confirm-ok-color="red">
    @csrf
  </form>
@endif

<script>
(function () {
  const client = document.getElementById('client_id');
  const site   = document.getElementById('project_site_id');
  const parent = document.getElementById('parent_asset_id');
  const parents = @json($parentOptions);
  let keep = @json((string) old('parent_asset_id', $asset->parent_asset_id));

  function filterSites() {
    const c = client.value;
    Array.from(site.options).forEach(o => {
      if (!o.value) return;
      const ok = !c || o.dataset.client === c;
      o.hidden = !ok; o.disabled = !ok;
    });
    if (site.selectedOptions[0] && site.selectedOptions[0].disabled) site.value = '';
  }

  function rebuildParents() {
    parent.innerHTML = '<option value="">None</option>';
    parents.filter(p => String(p.project_site_id) === site.value).forEach(p => {
      const o = document.createElement('option');
      o.value = p.id;
      o.textContent = p.asset_code + ' — ' + p.name;
      if (String(p.id) === keep) o.selected = true;
      parent.appendChild(o);
    });
    keep = '';
  }

  const pin = document.getElementById('default_contract_id');
  function filterPin() {
    if (!pin) return;
    Array.from(pin.options).forEach(o => {
      if (!o.value) return;
      const sites = (o.dataset.sites || '').split(',').filter(Boolean);
      const ok = o.dataset.stale === '1'
        || ((!client.value || o.dataset.client === client.value) && (!site.value || sites.length === 0 || sites.includes(site.value)));
      o.hidden = !ok; o.disabled = !ok;
    });
    if (pin.selectedOptions[0] && pin.selectedOptions[0].disabled) pin.value = '';
  }

  client.addEventListener('change', () => { filterSites(); filterPin(); keep = ''; rebuildParents(); });
  site.addEventListener('change', () => { keep = ''; rebuildParents(); filterPin(); });

  filterSites();
  filterPin();
  rebuildParents();

  // Clear a field's error as soon as it is changed
  document.querySelectorAll('#asset-form .err').forEach(el => {
    const clear = () => {
      el.classList.remove('err');
      const msg = el.parentElement.querySelector('.errtxt');
      if (msg) msg.remove();
    };
    el.addEventListener('input', clear);
    el.addEventListener('change', clear);
  });

  const btn = document.getElementById('decommission-btn');
  const dform = document.getElementById('decommission-form');
  if (btn && dform) {
    btn.addEventListener('click', () => dform.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
  }
})();
</script>
@endsection
