@extends('layouts.admin')
@section('title', $schedule->exists ? 'Edit schedule' : 'New schedule')

@section('content')
@include('admin.partials._page')
@php($old = fn ($k, $d = null) => old($k, $d))

<div class="page-head"><div><h1>{{ $schedule->exists ? 'Edit ' . $schedule->name : 'New maintenance schedule' }}</h1>
  <p>@if ($schedule->exists) Changes apply to requests created after you save. @else A request is created a few days (the lead days) before each due date. @endif</p></div></div>

<form method="POST" action="{{ $schedule->exists ? route('admin.maintenance.update', $schedule) : route('admin.maintenance.store') }}" class="card pad" style="max-width:820px">
  @csrf @if ($schedule->exists) @method('PUT') @endif

  <div class="field"><label>Name</label>
    <input name="name" class="inp" style="width:100%" maxlength="180" required value="{{ $old('name', $schedule->name) }}">
    @error('name')<div class="err">{{ $message }}</div>@enderror</div>

  <div style="display:flex;gap:14px;flex-wrap:wrap">
    <div class="field"><label>Site</label>
      <select name="project_site_id" id="site" class="sel" required>
        <option value="">Choose a site</option>
        @foreach ($sites as $s)<option value="{{ $s->id }}" @selected($old('project_site_id', $schedule->project_site_id) == $s->id)>{{ $s->name }}</option>@endforeach
      </select>@error('project_site_id')<div class="err">{{ $message }}</div>@enderror</div>
    <div class="field"><label>Service category</label>
      <select name="service_category_id" id="category" class="sel" required>
        <option value="">Choose a category</option>
        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($old('service_category_id', $schedule->service_category_id) == $c->id)>{{ $c->name }}</option>@endforeach
      </select>@error('service_category_id')<div class="err">{{ $message }}</div>@enderror</div>
  </div>

  <div class="field"><label>Assets <span class="hint">(one asset, or 2 to 200 for a batch that makes one request)</span></label>
    <div id="assets" style="max-height:240px;overflow:auto;border:1px solid var(--line);border-radius:10px;padding:10px">Choose a site and category first.</div>
    @error('asset_ids')<div class="err">{{ $message }}</div>@enderror</div>

  <div style="display:flex;gap:14px;flex-wrap:wrap">
    <div class="field"><label>Frequency</label>
      <select name="frequency" id="frequency" class="sel">
        @foreach ($frequencies as $k => $l)<option value="{{ $k }}" @selected($old('frequency', $schedule->frequency) === $k)>{{ $l }}</option>@endforeach
      </select></div>
    <div class="field" id="interval-box"><label>N</label>
      <input type="number" name="interval_value" class="inp" style="width:90px" value="{{ $old('interval_value', $schedule->interval_value) }}">
      <div class="hint">Days 7 to 730, months 1 to 36</div></div>
    <div class="field"><label>First due date</label>
      <input type="date" name="first_due_date" class="inp" required value="{{ $old('first_due_date', optional($schedule->first_due_date)->format('Y-m-d')) }}">
      @error('first_due_date')<div class="err">{{ $message }}</div>@enderror</div>
    <div class="field"><label>Lead days</label>
      <input type="number" name="lead_days" class="inp" style="width:90px" min="0" max="60" required value="{{ $old('lead_days', $schedule->lead_days) }}"></div>
    <div class="field"><label>Priority</label>
      <select name="priority" class="sel">@foreach ($priorities as $k => $l)<option value="{{ $k }}" @selected($old('priority', $schedule->priority) === $k)>{{ $l }}</option>@endforeach</select></div>
  </div>

  <div style="display:flex;gap:14px;flex-wrap:wrap">
    <div class="field"><label>Default technician</label>
      <select name="default_technician_user_id" class="sel"><option value="">None</option>
        @foreach ($technicians as $t)<option value="{{ $t->id }}" @selected($old('default_technician_user_id', $schedule->default_technician_user_id) == $t->id)>{{ $t->name }}</option>@endforeach
      </select></div>
    @if ($templates->isNotEmpty())
    <div class="field"><label>Checklist template</label>
      <select name="form_template_id" class="sel"><option value="">Use the asset's or category's</option>
        @foreach ($templates as $t)<option value="{{ $t->id }}" @selected($old('form_template_id', $schedule->form_template_id) == $t->id)>{{ $t->name }}</option>@endforeach
      </select></div>
    @endif
    @if ($contracts->isNotEmpty())
    <div class="field"><label>Pinned contract</label>
      <select name="contract_id" class="sel"><option value="">None</option>
        @foreach ($contracts as $c)<option value="{{ $c->id }}" @selected($old('contract_id', $schedule->contract_id) == $c->id)>{{ $c->contract_number }}</option>@endforeach
      </select></div>
    @endif
    <div class="field"><label>End date <span class="hint">(optional)</span></label>
      <input type="date" name="end_date" class="inp" value="{{ $old('end_date', optional($schedule->end_date)->format('Y-m-d')) }}"></div>
    @if ($schedule->exists)
    <div class="field"><label>Next due date</label>
      <input type="date" name="next_due_date" class="inp" value="{{ $old('next_due_date', optional($schedule->next_due_date)->format('Y-m-d')) }}"></div>
    @endif
  </div>

  <div class="field"><label><input type="hidden" name="skip_if_open" value="0"><input type="checkbox" name="skip_if_open" value="1" @checked($old('skip_if_open', $schedule->skip_if_open))> Skip a due date while an earlier request of this schedule is still open</label></div>
  <div class="field"><label>Notes</label><textarea name="notes" class="txta" maxlength="2000">{{ $old('notes', $schedule->notes) }}</textarea></div>

  <button class="btn btn-primary">{{ $schedule->exists ? 'Save changes' : 'Create schedule' }}</button>
  <a href="{{ route('admin.maintenance.index') }}" class="btn btn-ghost">Cancel</a>
</form>

<script>
(function () {
  var selected = @json(array_map('intval', (array) old('asset_ids', $selectedAssets)));
  var site = document.getElementById('site'), cat = document.getElementById('category'), box = document.getElementById('assets');
  var freq = document.getElementById('frequency'), interval = document.getElementById('interval-box');
  function showInterval() { interval.style.display = (freq.value === 'every_n_days' || freq.value === 'every_n_months') ? '' : 'none'; }
  freq.addEventListener('change', showInterval); showInterval();
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function load() {
    if (!site.value || !cat.value) { box.textContent = 'Choose a site and category first.'; return; }
    fetch(@json(route('admin.maintenance.assets')) + '?site_id=' + site.value + '&category_id=' + cat.value, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
      .then(function (r) { return r.json(); }).then(function (list) {
        box.innerHTML = list.length ? list.map(function (a) {
          return '<label style="display:block;padding:2px 0"><input type="checkbox" name="asset_ids[]" value="' + a.id + '"' + (selected.indexOf(a.id) > -1 ? ' checked' : '') + '> ' + esc(a.label) + '</label>';
        }).join('') : 'No assets of this category at this site.';
      });
  }
  site.addEventListener('change', function () { selected = []; load(); }); cat.addEventListener('change', function () { selected = []; load(); }); load();
})();
</script>
@endsection
