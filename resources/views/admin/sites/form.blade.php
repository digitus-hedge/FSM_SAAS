@extends('layouts.admin')
@section('title', $mode === 'create' ? 'New Site' : 'Edit Site')
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

  .form-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:28px;margin:0 auto}
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 20px}
  .field{display:flex;flex-direction:column;gap:6px}
  .field-wide{grid-column:span 2}
  .field label{font-size:12px;font-weight:600;color:var(--ink);letter-spacing:.01em}
  .req{color:var(--red)}
  .hint{font-size:11px;color:var(--muted);margin-top:2px}
  .err{font-size:11px;color:var(--red);margin-top:2px;font-weight:500;display:flex;align-items:center;gap:4px}
  .err::before{content:'⚠';font-size:12px}

  .input,textarea.input,select.input{font-family:'DM Sans';font-size:13px;padding:10px 14px;border-radius:10px;border:1px solid var(--line);background:#FCFBF8;color:var(--ink);outline:none;transition:border .15s,background .15s,box-shadow .15s;width:100%}
  .input:focus,textarea.input:focus,select.input:focus{border-color:var(--yellow);background:#fff;box-shadow:0 0 0 3px rgba(255,198,26,.15)}
  .input.has-error{border-color:var(--red);background:#fff;box-shadow:0 0 0 3px rgba(194,56,27,.08)}
  textarea.input{resize:vertical;min-height:60px}
  .input.upper{text-transform:uppercase;letter-spacing:.03em}
  select.input{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}

  .seg{display:flex;background:var(--soft);border-radius:10px;padding:3px;gap:2px}
  .seg input{display:none}
  .seg label{flex:1;text-align:center;padding:8px 12px;font-size:12px;font-weight:600;color:var(--muted);border-radius:8px;cursor:pointer;transition:all .12s}
  .seg input:checked + label{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.05)}

  .check{display:flex;align-items:center;gap:10px;padding:10px 0}
  .check input[type="checkbox"]{appearance:none;-webkit-appearance:none;width:18px;height:18px;border:1.5px solid var(--line);border-radius:5px;background:#fff;cursor:pointer;position:relative;flex-shrink:0}
  .check input[type="checkbox"]:checked{background:var(--yellow);border-color:var(--yellow)}
  .check input[type="checkbox"]:checked::after{content:'';position:absolute;left:5px;top:1px;width:5px;height:10px;border:solid var(--ink);border-width:0 2px 2px 0;transform:rotate(45deg)}

  .form-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--line)}
</style>

<div class="page-head">
  <h1>{{ $mode === 'create' ? 'New site' : 'Edit '.$site->name }}</h1>
  <a href="{{ route('admin.sites.index') }}" class="btn btn-ghost">← Back</a>
</div>

<form method="POST"
      action="{{ $mode === 'create' ? route('admin.sites.store') : route('admin.sites.update', $site) }}"
      class="form-card" novalidate>
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif

  <div class="form-grid">

    {{-- SITE TYPE (segmented control) --}}
    <div class="field field-wide">
      <label>Site type <span class="req">*</span></label>
      <div class="seg">
        <input type="radio" name="site_type" id="type-client" value="client_site"
               @checked(old('site_type', $site->site_type ?? 'client_site') === 'client_site')>
        <label for="type-client">🏭 Client site</label>

        <input type="radio" name="site_type" id="type-workshop" value="tenant_workshop"
               @checked(old('site_type', $site->site_type ?? 'client_site') === 'tenant_workshop')>
        <label for="type-workshop">🔧 Our workshop</label>
      </div>
      @error('site_type')<span class="err">{{ $message }}</span>@enderror
    </div>

    {{-- SITE CODE --}}
    <div class="field">
      <label for="site_code">Site code <span class="req">*</span></label>
      <input id="site_code" name="site_code"
             value="{{ old('site_code', $site->site_code) }}"
             maxlength="40" required pattern="[A-Za-z0-9_\-]+"
             class="input upper @error('site_code') has-error @enderror" />
      @error('site_code')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">Unique per tenant. Example: ACME-MAIN</span>
      @enderror
    </div>

    {{-- NAME --}}
    <div class="field">
      <label for="name">Site name <span class="req">*</span></label>
      <input id="name" name="name" value="{{ old('name', $site->name) }}"
             maxlength="180" required minlength="2"
             class="input @error('name') has-error @enderror" />
      @error('name')<span class="err">{{ $message }}</span>@enderror
    </div>

    {{-- CLIENT (shown only for client_site) --}}
    <div class="field field-wide" id="client-field">
      <label for="client_id">Client <span class="req">*</span></label>
      <select id="client_id" name="client_id" class="input @error('client_id') has-error @enderror">
        <option value="">— Choose a client —</option>
        @foreach ($clients as $client)
          <option value="{{ $client->id }}" @selected((string) old('client_id', $site->client_id) === (string) $client->id)>
            {{ $client->name }} ({{ $client->client_code }})
          </option>
        @endforeach
      </select>
      @error('client_id')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">Which client this site belongs to.</span>
      @enderror
    </div>

    {{-- ADDRESS --}}
    <div class="field field-wide">
      <label for="address">Address</label>
      <textarea id="address" name="address" rows="2" maxlength="1000"
                class="input @error('address') has-error @enderror">{{ old('address', $site->address) }}</textarea>
      @error('address')<span class="err">{{ $message }}</span>@enderror
    </div>

    {{-- CITY --}}
    <div class="field">
      <label for="city">City</label>
      <input id="city" name="city" value="{{ old('city', $site->city) }}" maxlength="100"
             class="input @error('city') has-error @enderror" />
      @error('city')<span class="err">{{ $message }}</span>@enderror
    </div>

    {{-- GEOFENCE RADIUS --}}
    <div class="field">
      <label for="geofence_radius_meters">Geofence radius (meters)</label>
      <input type="number" id="geofence_radius_meters" name="geofence_radius_meters"
             min="0" max="10000" step="10"
             value="{{ old('geofence_radius_meters', $site->geofence_radius_meters) }}"
             class="input @error('geofence_radius_meters') has-error @enderror" />
      @error('geofence_radius_meters')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">Overrides the category radius for this site. Leave empty to use defaults.</span>
      @enderror
    </div>

    {{-- LAT --}}
    <div class="field">
      <label for="lat">Latitude</label>
      <input type="number" id="lat" name="lat" step="0.0000001" min="-90" max="90"
             value="{{ old('lat', $site->lat) }}"
             placeholder="e.g. 19.0760"
             class="input @error('lat') has-error @enderror" />
      @error('lat')<span class="err">{{ $message }}</span>@enderror
    </div>

    {{-- LNG --}}
    <div class="field">
      <label for="lng">Longitude</label>
      <input type="number" id="lng" name="lng" step="0.0000001" min="-180" max="180"
             value="{{ old('lng', $site->lng) }}"
             placeholder="e.g. 72.8777"
             class="input @error('lng') has-error @enderror" />
      @error('lng')<span class="err">{{ $message }}</span>@enderror
    </div>

    {{-- CONTACT NAME --}}
    <div class="field">
      <label for="contact_name">On-site contact</label>
      <input id="contact_name" name="contact_name" value="{{ old('contact_name', $site->contact_name) }}"
             maxlength="150" class="input @error('contact_name') has-error @enderror" />
      @error('contact_name')<span class="err">{{ $message }}</span>@enderror
    </div>

    {{-- CONTACT PHONE --}}
    <div class="field">
      <label for="contact_phone">On-site phone</label>
      <input id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $site->contact_phone) }}"
             maxlength="40" class="input @error('contact_phone') has-error @enderror" />
      @error('contact_phone')<span class="err">{{ $message }}</span>@enderror
    </div>

    {{-- ACCESS INSTRUCTIONS --}}
    <div class="field field-wide">
      <label for="access_instructions">Access instructions</label>
      <textarea id="access_instructions" name="access_instructions" rows="3" maxlength="2000"
                placeholder="Gate code, floor, parking, PPE required…"
                class="input @error('access_instructions') has-error @enderror">{{ old('access_instructions', $site->access_instructions) }}</textarea>
      @error('access_instructions')
        <span class="err">{{ $message }}</span>
      @else
        <span class="hint">Visible to technicians in the Field View.</span>
      @enderror
    </div>

    {{-- ACTIVE --}}
    <div class="field">
      <div class="check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1"
               @checked(old('is_active', $site->exists ? $site->is_active : true)) />
        <label for="is_active" style="cursor:pointer">Active</label>
      </div>
    </div>

  </div>

  <div class="form-actions">
    <a href="{{ route('admin.sites.index') }}" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      {{ $mode === 'create' ? 'Create site' : 'Save changes' }}
    </button>
  </div>
</form>

<script>
// Toggle client field visibility based on site type
(function () {
  const clientField = document.getElementById('client-field');
  const clientSelect = document.getElementById('client_id');

  function sync() {
    const type = document.querySelector('input[name="site_type"]:checked')?.value;
    if (type === 'tenant_workshop') {
      clientField.style.display = 'none';
      clientSelect.value = '';
      clientSelect.removeAttribute('required');
    } else {
      clientField.style.display = '';
      clientSelect.setAttribute('required', 'required');
    }
  }

  document.querySelectorAll('input[name="site_type"]').forEach(r => r.addEventListener('change', sync));
  sync();
})();
</script>

@error('site_code')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      typeof Swal !== 'undefined' && Swal.fire({ icon:'error', title:'Please check your form', text:'See the highlighted fields below.' });
    });
  </script>
@enderror
@endsection