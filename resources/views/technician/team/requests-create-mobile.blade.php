@extends('layouts.technician')
@section('title', 'New request')
@section('content')

<div class="sec">Raise a request</div>

<form method="POST" action="{{ route('technician.team.requests.store') }}" enctype="multipart/form-data">
    @csrf
    @if (! empty($prefill))<input type="hidden" name="from_qr" value="1">@endif
    <div class="card" style="padding:14px;margin-bottom:10px">
        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Client</label>
        <select name="client_id" id="client_id" class="sel" required style="margin-bottom:14px">
            <option value="">Choose a client</option>
            @foreach ($clients as $c)<option value="{{ $c->id }}" @selected((string) ($prefill['client_id'] ?? '') === (string) $c->id)>{{ $c->name }}</option>@endforeach
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Site</label>
        <select name="project_site_id" id="project_site_id" class="sel" required @disabled(empty($prefill)) style="margin-bottom:14px">
            @if (! empty($prefill))
                        <option value="">Choose a site</option>
                        @foreach ($prefill['sites'] as $s)<option value="{{ $s->id }}" @selected((int) $prefill['project_site_id'] === (int) $s->id)>{{ $s->name }}</option>@endforeach
                    @else
                        <option value="">Choose a client first</option>
                    @endif
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Category</label>
        <select name="service_category_id" id="service_category_id" class="sel" required style="margin-bottom:14px">
            <option value="">Choose a category</option>
            @foreach ($categories as $cat)<option value="{{ $cat->id }}" @selected((string) ($prefill['service_category_id'] ?? '') === (string) $cat->id)>{{ $cat->name }}</option>@endforeach
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Asset (optional)</label>
        <select name="primary_asset_id" id="primary_asset_id" class="sel" style="margin-bottom:14px">
            <option value="">Choose a site first</option>
        </select>
        <div id="asset-info" style="display:none;border:1px solid var(--line,#E6E4EE);border-radius:10px;padding:10px 12px;font-size:12.5px;margin-bottom:14px"></div>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Priority</label>
        <select name="priority" class="sel" required style="margin-bottom:14px">
            @foreach (\App\Models\ServiceRequest::PRIORITIES as $value => $label)
                <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
            @endforeach
        </select>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Title</label>
        <input type="text" name="title" class="sel" maxlength="190" required style="margin-bottom:14px" placeholder="e.g. AC not cooling">

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Description (optional)</label>
        <textarea name="description" class="sel" maxlength="2000" rows="4" style="margin-bottom:14px;resize:vertical"></textarea>

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">PO reference (optional)</label>
        <input type="text" name="client_po_reference" class="sel" maxlength="100" style="margin-bottom:14px">

        <label class="muted" style="font-size:12.5px;font-weight:700;display:block;margin-bottom:6px">Attachments (optional)</label>
        <input type="file" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx">
    </div>

    <button type="submit" class="btn dark block">Create request</button>
</form>

<script>
document.getElementById('client_id')?.addEventListener('change', function () {
    const siteSel = document.getElementById('project_site_id');
    const clientId = this.value;
    if (! clientId) { siteSel.innerHTML = '<option value="">Choose a client first</option>'; siteSel.disabled = true; return; }
    siteSel.innerHTML = '<option value="">Loading…</option>';
    siteSel.disabled = true;
    fetch("{{ route('technician.team.sites-for-client') }}?client_id=" + encodeURIComponent(clientId))
        .then(r => r.json())
        .then(sites => {
            if (! sites.length) { siteSel.innerHTML = '<option value="">No sites for this client</option>'; return; }
            siteSel.innerHTML = '<option value="">Choose a site</option>' + sites.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
            siteSel.disabled = false;
        })
        .catch(() => { siteSel.innerHTML = '<option value="">Could not load sites</option>'; });
});
</script>

<script>
/* Asset picker: lists assets at the chosen site, fills the category, shows warranty and recent requests */
(function () {
  const clientSel = document.getElementById('client_id');
  const siteSel   = document.getElementById('project_site_id');
  const assetSel  = document.getElementById('primary_asset_id');
  const assetInfo = document.getElementById('asset-info');
  const catSel    = document.getElementById('service_category_id');
  if (!clientSel || !siteSel || !assetSel || !assetInfo) return;

  const assetsUrl  = "{{ route('service-requests.assets.for-site') }}";
  const summaryTpl = "{{ route('service-requests.assets.summary', ['asset' => '__ID__']) }}";
  const headers    = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let assetsMap = {};

  async function loadAssets(siteId, selectedId) {
    assetInfo.style.display = 'none';
    assetsMap = {};
    if (!siteId) { assetSel.innerHTML = '<option value="">Choose a site first</option>'; return; }
    assetSel.innerHTML = '<option value="">Loading assets…</option>';
    try {
      const res = await fetch(assetsUrl + '?site_id=' + encodeURIComponent(siteId), { headers });
      const assets = await res.json();
      let html = '<option value="">' + (assets.length ? 'No asset' : 'No assets at this site') + '</option>';
      assets.forEach(a => { assetsMap[a.id] = a; html += `<option value="${a.id}">${esc(a.label)}</option>`; });
      assetSel.innerHTML = html;
      if (selectedId) { assetSel.value = String(selectedId); if (assetSel.value) showAsset(selectedId); }
    } catch (e) {
      assetSel.innerHTML = '<option value="">Could not load assets</option>';
      console.error(e);
    }
  }

  async function showAsset(id) {
    const a = assetsMap[id];
    if (a && a.service_category_id && catSel) {
      const want = String(a.service_category_id);
      if (Array.from(catSel.options).some(o => o.value === want)) catSel.value = want;
    }
    try {
      const res = await fetch(summaryTpl.replace('__ID__', encodeURIComponent(id)), { headers });
      const d = await res.json();
      if (assetSel.value !== String(id)) return;
      const warranty = d.warranty_status === 'in_warranty'
        ? `<span style="color:var(--green,#00866F);font-weight:700">In warranty until ${esc(d.warranty_expiry)}</span>`
        : d.warranty_status === 'expired'
          ? `<span style="color:var(--red,#C2381B);font-weight:700">Warranty expired ${esc(d.warranty_expiry)}</span>`
          : '<span style="color:var(--muted,#5F5D6E)">No warranty date</span>';
      const recent = d.recent.length
        ? d.recent.map(r => `<div style="margin-top:4px"><strong>${esc(r.sr_number)}</strong>
            <span style="color:var(--muted,#5F5D6E)"> · ${esc(r.title)} · ${esc(r.status_label)} · ${esc(r.date)}</span></div>`).join('')
        : '<div style="color:var(--muted,#5F5D6E);margin-top:4px">No previous requests for this asset.</div>';
      assetInfo.innerHTML = `<div><strong>${esc(d.asset_code)}</strong> · ${esc(d.name)} — ${warranty}</div>
        <div style="margin-top:8px;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted,#5F5D6E)">Recent requests</div>${recent}`;
      assetInfo.style.display = 'block';
    } catch (e) { console.error(e); }
  }

  assetSel.addEventListener('change', () => {
    if (assetSel.value) showAsset(assetSel.value); else assetInfo.style.display = 'none';
  });
  siteSel.addEventListener('change', () => loadAssets(siteSel.value));
  clientSel.addEventListener('change', () => loadAssets(''));

  /* Opened from an asset QR page: the site is already chosen, so load its assets and pick the asset */
  if (siteSel.value) loadAssets(siteSel.value, "{{ $prefill['primary_asset_id'] ?? '' }}");
})();
</script>
@endsection