@extends('layouts.admin')

@php
    $site    = $site    ?? new \App\Models\Site();
    $mode    = $mode    ?? ($site->exists ? 'edit' : 'create');
    $clients = $clients ?? collect();
@endphp

@section('title', $mode === 'create' ? 'New site' : $site->name)
@section('hide_layout_errors', true)

@section('content')
{{-- Leaflet CSS + JS for the location map --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
  .crumb{font-size:13px;color:#5F5D6E;margin-bottom:12px}
  .crumb a{color:#0F0F17;text-decoration:underline;text-underline-offset:3px}
  .crumb a:hover{color:var(--amber)}

  .page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:12px}
  .page-head h1{font-family:var(--font-display),sans-serif;font-size:34px;font-weight:800;line-height:1.08;letter-spacing:-.02em;margin:0;color:#0F0F17}
  .meta-row{display:flex;gap:8px;align-items:center;margin-top:12px;margin-bottom:24px}
  .code-pill{display:inline-block;font-family:var(--font-body);font-size:12.5px;font-weight:600;color:var(--amber);background:var(--yellow-soft);padding:5px 11px;border-radius:7px}
  .status{display:inline-flex;align-items:center;font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:999px}
  .status-ok{background:#E6F7EC;color:#1D7A3A}
  .status-off{background:#F2F1F6;color:#5F5D6E}

  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:var(--font-body);font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:all .12s;white-space:nowrap}
  .btn-primary{background:var(--amber);color:#fff}
  .btn-primary:hover{background:color-mix(in srgb, var(--amber) 85%, black 15%)}
  .btn-ghost{background:#fff;color:#0F0F17;border-color:#E5E4EC}
  .btn-ghost:hover{border-color:#0F0F17}
  .btn-danger-out{background:#fff;color:#C7372F;border-color:#F0C7C7}
  .btn-danger-out:hover{background:#FFF5F5;border-color:#C7372F}
  .btn-sm{padding:8px 14px;font-size:12.5px;border-radius:10px}

  /* Two-column layout */
  .layout{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start}
  @media (max-width:960px){.layout{grid-template-columns:1fr}}

  .card{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:24px}

  .fld{margin-bottom:20px;display:flex;flex-direction:column}
  .fld:last-child{margin-bottom:0}
  .fld label{font-weight:600;font-size:14px;margin-bottom:8px;color:#0F0F17}
  .req{color:#C7372F}
  .inp,.sel,.txt{width:100%;font-family:var(--font-body);font-size:14px;padding:12px 14px;border-radius:10px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;outline:none;transition:border .15s, box-shadow .15s;box-sizing:border-box}
  .inp:focus,.sel:focus,.txt:focus{border-color:var(--amber);box-shadow:0 0 0 3px color-mix(in srgb, var(--amber) 15%, transparent)}
  .inp.err,.txt.err{border-color:#C7372F;background:#FFF7F7}
  .inp::placeholder,.txt::placeholder{color:#9A98A7}
  .txt{min-height:88px;resize:vertical;font-family:inherit}
  .sel{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}
  .hint{font-size:12.5px;color:#5F5D6E;margin-top:6px}
  .errtxt{font-size:12.5px;color:#C7372F;margin-top:6px;font-weight:600}

  .two{display:grid;grid-template-columns:1fr 1fr;gap:20px}
  @media (max-width:600px){.two{grid-template-columns:1fr}}

  /* Map block */
  .map-wrap{border:1px solid #E5E4EC;border-radius:12px;overflow:hidden}
  #site-map{height:260px;width:100%;background:#F2F1F6}
  .map-tools{display:flex;gap:8px;margin:10px 0 14px;flex-wrap:wrap}
  .paste-row{display:flex;gap:8px;margin-top:4px}
  .paste-row input{flex:1}

  /* Plain checkbox row (not switch-row) */
  .check-row{display:flex;align-items:flex-start;gap:10px;margin:4px 0 20px;cursor:pointer}
  .check-row input[type=checkbox]{appearance:none;width:18px;height:18px;border:1.5px solid #D1CFDA;border-radius:5px;cursor:pointer;position:relative;flex-shrink:0;margin-top:1px;background:#fff}
  .check-row input:checked{background:var(--amber);border-color:var(--amber)}
  .check-row input:checked::after{content:'';position:absolute;left:5px;top:1px;width:5px;height:10px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
  .check-row .ck-ttl{font-size:13.5px;font-weight:700;color:#0F0F17}
  .check-row .ck-sub{font-size:12px;color:#5F5D6E;margin-top:2px}

  /* Form actions */
  .formact{display:flex;gap:10px;align-items:center;margin-top:24px;flex-wrap:wrap}

  /* Aside: People for this site */
  .aside h3{font-family:var(--font-display);font-size:16px;font-weight:700;margin:0 0 16px;color:#0F0F17;display:flex;align-items:center;gap:8px}
  .aside h3 svg{color:var(--amber);width:18px;height:18px}
  .person{padding:12px 0;border-bottom:1px solid #EEEDF3}
  .person:last-of-type{border-bottom:0}
  .person-name{font-weight:700;font-size:14px;color:#0F0F17}
  .person-sub{font-size:12.5px;color:#5F5D6E;margin-top:2px}
  .aside-note{font-size:12.5px;color:#5F5D6E;margin-top:14px;padding-top:14px;border-top:1px solid #EEEDF3;line-height:1.5}
  .aside-empty{padding:20px 0;text-align:center;color:#5F5D6E;font-size:13px}

  .proto-note{color:#5F5D6E;font-size:12.5px;margin-top:16px}
</style>

<div class="crumb">
  <a href="{{ route('admin.sites.index') }}">Sites</a> / {{ $mode === 'create' ? 'New site' : $site->name }}
</div>

<div class="page-head">
  <div style="flex:1">
    <h1>{{ $mode === 'create' ? 'New site' : $site->name }}</h1>
    @if ($mode === 'edit')
      <div class="meta-row">
        <span class="code-pill">{{ $site->site_code }}</span>
        @if ($site->is_active)
          <span class="status status-ok">Active</span>
        @else
          <span class="status status-off">Inactive</span>
        @endif
      </div>
    @endif
  </div>
</div>

<div class="layout">
  {{-- LEFT: form --}}
  <form method="POST"
        action="{{ $mode === 'create' ? route('admin.sites.store') : route('admin.sites.update', $site) }}"
        id="site-form" novalidate>
    @csrf

    @if ($mode === 'edit') @method('PUT') @endif
<input type="hidden" name="site_type"
       value="{{ old('site_type', $site->site_type ?? 'client_site') }}">
    <div class="card">

      {{-- CLIENT --}}
      <div class="fld">
        <label for="client_id">Client <span class="req">*</span></label>
        <select id="client_id" name="client_id" required class="sel @error('client_id') err @enderror">
          <option value="">Choose a client</option>
          @foreach ($clients as $c)
            <option value="{{ $c->id }}" @selected((string) old('client_id', $site->client_id) === (string) $c->id)>
              {{ $c->name }}
            </option>
          @endforeach
        </select>
        @error('client_id')<div class="errtxt">{{ $message }}</div>@enderror
      </div>

      {{-- NAME --}}
      <div class="fld">
        <label for="name">Name <span class="req">*</span></label>
        <input id="name" name="name" value="{{ old('name', $site->name) }}" maxlength="180" required
               class="inp @error('name') err @enderror">
        @error('name')<div class="errtxt">{{ $message }}</div>@enderror
      </div>

      {{-- ADDRESS --}}
      <div class="fld">
        <label for="address">Address</label>
        <textarea id="address" name="address" rows="3" maxlength="1000"
                  class="txt @error('address') err @enderror">{{ old('address', $site->address) }}</textarea>
        @error('address')<div class="errtxt">{{ $message }}</div>@enderror
      </div>

      {{-- CITY --}}
      <div class="fld">
        <label for="city">City</label>
        <input id="city" name="city" value="{{ old('city', $site->city) }}" maxlength="100"
               class="inp @error('city') err @enderror">
        @error('city')<div class="errtxt">{{ $message }}</div>@enderror
      </div>

      {{-- LOCATION (map) --}}

      <!-- <div class="fld">
        <label>Location</label>
        <div class="map-wrap">
          <div id="site-map"></div>
        </div> -->

        <div class="fld">
  <label>Location</label>

  {{-- PLACE SEARCH --}}
  <div style="position:relative;margin-bottom:10px">
    <input type="text" id="place-search" class="inp"
           placeholder="Search a place, e.g. Bluewave Cafe Airport Road Dubai"
           autocomplete="off"
           style="padding-right:40px">
    <button type="button" id="place-search-btn"
            style="position:absolute;right:6px;top:6px;background:var(--amber);color:#fff;border:0;border-radius:8px;padding:7px 12px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">
      Search
    </button>
    <div id="place-results"
         style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #E5E4EC;border-radius:10px;margin-top:4px;max-height:260px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,.08);z-index:1000;display:none"></div>
  </div>

  <div class="map-wrap">
    <div id="site-map"></div>
  </div>



        <div class="hint" style="margin-top:10px;margin-bottom:6px">Click the map to drop a pin, drag it to fine-tune, or search a place above. The shaded circle shows the geofence radius technicians must be inside to check in.</div>
        <div class="map-tools">
          <button type="button" class="btn btn-ghost btn-sm" id="geo-locate">Find my location</button>
          <button type="button" class="btn btn-ghost btn-sm" id="geo-remove">Remove pin</button>
        </div>

        <div class="two" style="gap:14px">
          <div class="fld" style="margin-bottom:12px">
            <label for="lat">Latitude</label>
            <input type="number" id="lat" name="lat" step="0.0000001" min="-90" max="90"
                   value="{{ old('lat', $site->lat) }}" class="inp @error('lat') err @enderror">
            @error('lat')<div class="errtxt">{{ $message }}</div>@enderror
          </div>
          <div class="fld" style="margin-bottom:12px">
            <label for="lng">Longitude</label>
            <input type="number" id="lng" name="lng" step="0.0000001" min="-180" max="180"
                   value="{{ old('lng', $site->lng) }}" class="inp @error('lng') err @enderror">
            @error('lng')<div class="errtxt">{{ $message }}</div>@enderror
          </div>
        </div>
        
        <div class="paste-row">
          <input type="text" id="paste-coords" class="inp" placeholder="Paste coordinates, for example 25.2048, 55.2708">
          <button type="button" class="btn btn-ghost btn-sm" id="paste-apply">Apply</button>
        </div>
      </div>

      {{-- RADIUS --}}
      <div class="fld">
        <label for="geofence_radius_meters">Radius (metres) <span class="req">*</span></label>
        <input type="number" id="geofence_radius_meters" name="geofence_radius_meters"
               min="100" max="2000" step="10" required
               value="{{ old('geofence_radius_meters', $site->geofence_radius_meters ?? 250) }}"
               class="inp @error('geofence_radius_meters') err @enderror">
        @error('geofence_radius_meters')<div class="errtxt">{{ $message }}</div>@else
          <div class="hint">
            @if ($mode === 'edit')
              Phone GPS is often only accurate to 20 to 150 metres. A small radius can stop technicians punching in.
            @else
              Between 100 and 2,000 metres. The default is 250.
            @endif
          </div>
        @enderror
      </div>

      {{-- ALWAYS ENFORCE GEOFENCE --}}
      <label class="check-row" for="always_enforce_geofence">
        <input type="hidden" name="always_enforce_geofence" value="0">
        <input type="checkbox" name="always_enforce_geofence" id="always_enforce_geofence" value="1"
               @checked(old('always_enforce_geofence', $site->always_enforce_geofence ?? false))>
        <div>
          <div class="ck-ttl">Always enforce geofence</div>
          <div class="ck-sub">Needs a saved location.</div>
        </div>
      </label>

      {{-- SITE CONTACT --}}
      <div class="two">
        <div class="fld">
          <label for="contact_name">Site contact name</label>
          <input id="contact_name" name="contact_name" value="{{ old('contact_name', $site->contact_name) }}"
                 maxlength="150" class="inp @error('contact_name') err @enderror">
          @error('contact_name')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
        <div class="fld">
          <label for="contact_phone">Site contact phone</label>
          <input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $site->contact_phone) }}"
                 maxlength="40" placeholder="+971501234567" inputmode="tel" pattern="[0-9+\-\s()]*"
                 class="inp @error('contact_phone') err @enderror"
                 oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')">
          @error('contact_phone')<div class="errtxt">{{ $message }}</div>@enderror
        </div>
      </div>

      {{-- ACCESS INSTRUCTIONS --}}
      <div class="fld">
        <label for="access_instructions">Access instructions</label>
        <textarea id="access_instructions" name="access_instructions" rows="4" maxlength="2000"
                  class="txt @error('access_instructions') err @enderror">{{ old('access_instructions', $site->access_instructions) }}</textarea>
        @error('access_instructions')<div class="errtxt">{{ $message }}</div>@else
          <div class="hint">Shown to the technician on the job.</div>
        @enderror
      </div>

      {{-- ACTIONS --}}
      <div class="formact">
        @if ($mode === 'create')
          <button type="submit" class="btn btn-primary">Create site</button>
          <a href="{{ route('admin.sites.index') }}" class="btn btn-ghost">Cancel</a>
        @else
          <button type="submit" class="btn btn-primary">Save changes</button>

          @if ($site->is_active)
            <button type="button" class="btn btn-danger-out" id="deactivate-btn">Deactivate</button>
            <form method="POST" action="{{ route('admin.sites.suspend', $site) }}"
                  class="needs-confirm" id="deactivate-form" style="display:none"
                  data-confirm-title="Deactivate {{ $site->name }}?"
                  data-confirm-text="This site will stop appearing in new service requests."
                  data-confirm-ok="Yes, deactivate" data-confirm-ok-color="red">
              @csrf
            </form>
          @else
            <button type="button" class="btn btn-ghost" id="reactivate-btn">Reactivate</button>
            <form method="POST" action="{{ route('admin.sites.reactivate', $site) }}"
                  class="needs-confirm" id="reactivate-form" style="display:none"
                  data-confirm-title="Reactivate {{ $site->name }}?"
                  data-confirm-ok="Yes, reactivate" data-confirm-ok-color="green">
              @csrf
            </form>
          @endif

          <a href="{{ route('admin.sites.index') }}" class="btn btn-ghost">Cancel</a>
        @endif
      </div>
    </div>

    @if ($mode === 'create')
      <div class="proto-note">Design prototype. Names and numbers are examples.</div>
    @endif
  </form>

  {{-- RIGHT: People for this site --}}
  <aside class="aside">
    <div class="card">
      <h3>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        People for this site
      </h3>

  
        @php
    $people = collect();
    if ($mode === 'edit' && $site->client) {
        $people = $site->client->contacts()->get();
    }
@endphp
    

      @forelse ($people as $person)
        <div class="person">
          <div class="person-name">{{ $person->name }}</div>
          <div class="person-sub">
            Client contact{{ $person->email ? ', '.$person->email : ($person->phone ? ', '.$person->phone : '') }}
          </div>
        </div>
      @empty
        <div class="aside-empty">
          @if ($mode === 'create')
            Save the site first, then client contacts for this client will appear here.
          @else
            No contacts linked to this site yet.
          @endif
        </div>
      @endforelse

      @if ($people->count())
        <div class="aside-note">A technician can pick from these when a signature code is needed.</div>
      @endif
    </div>
  </aside>
</div>

<script>
(function () {
  // ===== Map =====
  const defaultCenter = [10.8505, 76.2711]; // Kerala (state center) — used when no location is saved yet
  const defaultZoom    = 8;

  const initLat = parseFloat(document.getElementById('lat').value);
  const initLng = parseFloat(document.getElementById('lng').value);
  const hasInit = !isNaN(initLat) && !isNaN(initLng);
  const start   = hasInit ? [initLat, initLng] : defaultCenter;

  const map = L.map('site-map', { scrollWheelZoom: false }).setView(start, hasInit ? 16 : defaultZoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  // Click-to-enable scroll zoom, so scrolling the page doesn't accidentally zoom the map
  const scrollHint = document.createElement('div');
  scrollHint.textContent = 'Click the map to enable scroll zoom';
  scrollHint.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(27,26,36,.35);color:#fff;font-size:13px;font-weight:600;z-index:500;cursor:pointer;text-align:center;padding:10px';
  document.getElementById('site-map').style.position = 'relative';
  document.getElementById('site-map').appendChild(scrollHint);
  document.getElementById('site-map').addEventListener('click', function enableZoom() {
    map.scrollWheelZoom.enable();
    scrollHint.remove();
  }, { once: true });

  let marker = null;
  let geoCircle = null;

  function currentRadius() {
    const r = parseInt(document.getElementById('geofence_radius_meters').value, 10);
    return isNaN(r) ? 250 : r;
  }

  function setMarker(latlng) {
    if (marker) {
      marker.setLatLng(latlng);
    } else {
      marker = L.marker(latlng, { draggable: true })
        .addTo(map)
        .bindTooltip('Drag to fine-tune', { permanent: false, direction: 'top' })
        .on('dragend', e => { writeLatLng(e.target.getLatLng()); updateCircle(e.target.getLatLng()); });
    }
    if (!geoCircle) {
      geoCircle = L.circle(latlng, {
        radius: currentRadius(),
        color: '#8A5A00',
        weight: 1.5,
        fillColor: '#FFC61A',
        fillOpacity: 0.15
      }).addTo(map);
    } else {
      geoCircle.setLatLng(latlng);
    }
    writeLatLng(latlng);
  }

  function updateCircle(latlng) {
    if (geoCircle) geoCircle.setLatLng(latlng || marker.getLatLng()).setRadius(currentRadius());
  }

  function clearMarker() {
    if (marker)   { map.removeLayer(marker);   marker   = null; }
    if (geoCircle){ map.removeLayer(geoCircle); geoCircle = null; }
    document.getElementById('lat').value = '';
    document.getElementById('lng').value = '';
  }
  function writeLatLng(ll) {
    document.getElementById('lat').value = ll.lat.toFixed(7);
    document.getElementById('lng').value = ll.lng.toFixed(7);
  }

  // Live-update the circle as the radius field changes, so the picker can SEE the coverage area
  document.getElementById('geofence_radius_meters').addEventListener('input', () => updateCircle());

  if (hasInit) setMarker(start);
  map.on('click', e => {
    setMarker(e.latlng);
    map.flyTo(e.latlng, Math.max(map.getZoom(), 16), { duration: 0.6 });
  });

    // "Use my current location"
  document.getElementById('geo-locate').addEventListener('click', async () => {
    const isSecure = location.protocol === 'https:'
                  || location.hostname === 'localhost'
                  || location.hostname === '127.0.0.1';

    if (navigator.geolocation && isSecure) {
      navigator.geolocation.getCurrentPosition(
        pos => {
          const lat = pos.coords.latitude, lng = pos.coords.longitude;
          setMarker({ lat, lng });
          map.flyTo([lat, lng], 16, { duration: 0.8 });
          setTimeout(() => map.invalidateSize(), 50);
        },
        err => Swal.fire({ icon: 'warning', title: 'Could not get precise location', text: err.message, confirmButtonText: 'OK' })
      );
      return;
    }

    try {
      const r = await fetch('https://ipapi.co/json/');
      const d = await r.json();
      if (! d.latitude || ! d.longitude) throw new Error('No coords in response');

      const lat = parseFloat(d.latitude), lng = parseFloat(d.longitude);
      setMarker({ lat, lng });
      map.flyTo([lat, lng], 13, { duration: 0.8 });
      setTimeout(() => map.invalidateSize(), 50);

      Swal.fire({
        toast: true, position: 'top-end', icon: 'info',
        title: 'Using approximate location',
        html: '<small>' + (d.city || '?') + ', ' + (d.country_name || '?') + '. Drag the pin or paste coordinates for precise GPS.</small>',
        showConfirmButton: false, timer: 4000, timerProgressBar: true,
      });
    } catch (e) {
      Swal.fire({
        icon: 'info', title: 'Location unavailable',
        text: 'Click on the map to drop a pin manually, or paste coordinates in the box below.',
        confirmButtonText: 'OK'
      });
    }
  });
  // "Remove pin"
  document.getElementById('geo-remove').addEventListener('click', clearMarker);

  // "Paste coordinates" + Apply
  document.getElementById('paste-apply').addEventListener('click', () => {
    const raw = document.getElementById('paste-coords').value.trim();
    const m = raw.match(/^(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)$/);
    if (!m) return alert('Format: 25.2048, 55.2708');
    const lat = parseFloat(m[1]), lng = parseFloat(m[2]);
    if (lat<-90||lat>90||lng<-180||lng>180) return alert('Coordinates out of range.');
    const ll = { lat, lng };
    map.setView(ll, 16); setMarker(ll);
    document.getElementById('paste-coords').value = '';
  });

  // Manual lat/lng typing → update marker
  ['lat','lng'].forEach(id => document.getElementById(id).addEventListener('change', () => {
    const la = parseFloat(document.getElementById('lat').value);
    const ln = parseFloat(document.getElementById('lng').value);
    if (!isNaN(la) && !isNaN(ln)) { const ll={lat:la,lng:ln}; map.setView(ll, 16); setMarker(ll); }
  }));

  // Fix map tile loading when inside hidden/async layouts
  setTimeout(() => map.invalidateSize(), 150);


  // ===== Place search (Nominatim / OpenStreetMap) =====
const searchInput  = document.getElementById('place-search');
const searchBtn    = document.getElementById('place-search-btn');
const searchResults = document.getElementById('place-results');

async function searchPlace(q) {
  if (! q || q.trim().length < 2) return;

  searchResults.innerHTML = '<div style="padding:12px 14px;color:#5F5D6E;font-size:13px">Searching…</div>';
  searchResults.style.display = 'block';

  try {
   const url = 'https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=8'
          + '&countrycodes=in'
          + '&viewbox=74.86,12.48,77.42,8.18&bounded=0'
          + '&q=' + encodeURIComponent(q.trim());
    const r = await fetch(url, { headers: { 'Accept-Language': 'en' } });
    const data = await r.json();

    if (! data.length) {
      searchResults.innerHTML = '<div style="padding:12px 14px;color:#5F5D6E;font-size:13px">No places found.</div>';
      return;
    }

    searchResults.innerHTML = data.map((p, i) => `
      <div class="pres" data-idx="${i}"
           style="padding:10px 14px;border-bottom:1px solid #F2F1F6;cursor:pointer;font-size:13.5px">
        <div style="font-weight:600;color:#0F0F17">${p.display_name.split(',').slice(0,2).join(', ')}</div>
        <div style="color:#5F5D6E;font-size:12px;margin-top:2px">${p.display_name}</div>
      </div>
    `).join('');

    // Store results on the container so clicks can find them
    searchResults._data = data;

    searchResults.querySelectorAll('.pres').forEach(row => {
      row.addEventListener('mouseenter', () => row.style.background = '#F8F7FB');
      row.addEventListener('mouseleave', () => row.style.background = '');
      row.addEventListener('click', () => {
        const pick = searchResults._data[parseInt(row.dataset.idx)];
        const lat = parseFloat(pick.lat), lng = parseFloat(pick.lon);
        setMarker({ lat, lng });
        map.setView([lat, lng], 17);
        setTimeout(() => map.invalidateSize(), 50);

        // Auto-fill address / city when empty
        const addrInput = document.getElementById('address');
        const cityInput = document.getElementById('city');
        if (addrInput && ! addrInput.value.trim()) {
          addrInput.value = pick.display_name;
        }
        if (cityInput && ! cityInput.value.trim()) {
          const city = pick.address?.city
                    || pick.address?.town
                    || pick.address?.village
                    || pick.address?.state
                    || '';
          if (city) cityInput.value = city;
        }

        searchResults.style.display = 'none';
        searchInput.value = pick.display_name.split(',').slice(0,2).join(', ');
      });
    });
  } catch (e) {
    searchResults.innerHTML = '<div style="padding:12px 14px;color:#C7372F;font-size:13px">Search failed. Try again.</div>';
  }
}

// Fire on Enter + button click + 400ms after typing stops
searchBtn.addEventListener('click', () => searchPlace(searchInput.value));
searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); searchPlace(searchInput.value); } });

let searchDebounce;
searchInput.addEventListener('input', () => {
  clearTimeout(searchDebounce);
  const v = searchInput.value;
  if (v.trim().length < 3) { searchResults.style.display = 'none'; return; }
  searchDebounce = setTimeout(() => searchPlace(v), 400);
});

// Hide results when clicking outside
document.addEventListener('click', e => {
  if (! e.target.closest('#place-search') && ! e.target.closest('#place-search-btn') && ! e.target.closest('#place-results')) {
    searchResults.style.display = 'none';
  }
});


})();

// Deactivate / Reactivate buttons submit the hidden forms via SweetAlert confirm
(function () {
  const bind = (btn, form) => btn && form && btn.addEventListener('click', () => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
  bind(document.getElementById('deactivate-btn'), document.getElementById('deactivate-form'));
  bind(document.getElementById('reactivate-btn'), document.getElementById('reactivate-form'));
})();
</script>
@endsection