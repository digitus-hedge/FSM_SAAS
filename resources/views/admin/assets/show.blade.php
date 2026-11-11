@extends('layouts.admin')
@section('title', $asset->asset_code . ' · ' . $asset->name)

@section('content')
@php
    $statusLabels = ['active' => 'Active', 'under_repair' => 'Under repair', 'inactive' => 'Inactive', 'decommissioned' => 'Decommissioned'];
    $statusClass  = ['active' => 'status-ok', 'under_repair' => 'status-warn', 'inactive' => 'status-off', 'decommissioned' => 'status-off'];
    $money = fn ($v) => number_format((float) $v, 2) . ' ' . $currency;
    $srStatus = \App\Models\ServiceRequest::STATUSES;
@endphp
<style>
  .crumb{font-size:13px;color:#5F5D6E;margin-bottom:12px}
  .crumb a{color:#0F0F17;text-decoration:underline;text-underline-offset:3px}
  .head{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:flex-start;margin-bottom:18px}
  .head h1{font-family:var(--font-display),sans-serif;font-size:32px;font-weight:800;letter-spacing:-.02em;margin:8px 0 0;color:#0F0F17}
  .code-pill{display:inline-block;font-size:12.5px;font-weight:600;color:var(--amber);background:var(--yellow-soft);padding:5px 11px;border-radius:7px}
  .status{display:inline-flex;align-items:center;font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:999px;white-space:nowrap}
  .status-ok{background:#E6F7EC;color:#1D7A3A}.status-warn{background:#FEF3C7;color:#92400E}
  .status-off{background:#F2F1F6;color:#5F5D6E}.status-bad{background:#FDECE7;color:#C2381B}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:10px 18px;font-size:14px;font-weight:700;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;text-decoration:none;cursor:pointer}
  .btn:hover{border-color:#0F0F17}
  .actions{display:flex;gap:10px;flex-wrap:wrap}
  .tabs{display:flex;gap:4px;border-bottom:1px solid #E5E4EC;margin-bottom:18px;overflow-x:auto}
  .tab{padding:12px 16px;font-size:14px;font-weight:700;color:#5F5D6E;border:0;background:none;cursor:pointer;border-bottom:3px solid transparent;white-space:nowrap}
  .tab.on{color:#0F0F17;border-bottom-color:var(--amber)}
  .tab .n{font-weight:600;color:#9A98A7;margin-left:4px}
  .pane{display:none}.pane.on{display:block}
  .card{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:22px;margin-bottom:16px}
  .card h3{font-family:var(--font-display);font-size:15px;font-weight:700;margin:0 0 12px}
  .kv{display:grid;grid-template-columns:170px 1fr;gap:10px 16px;font-size:14px}
  .kv dt{color:#5F5D6E}.kv dd{margin:0;color:#0F0F17;font-weight:600}
  table{width:100%;border-collapse:collapse;font-size:14px}
  th{text-align:left;padding:12px 14px;font-size:12.5px;font-weight:600;color:#5F5D6E;background:#FAFAFC;border-bottom:1px solid #EEEDF3;white-space:nowrap}
  td{padding:12px 14px;border-bottom:1px solid #F2F1F6;vertical-align:middle}
  tr:last-child td{border-bottom:0}
  .tw{overflow-x:auto}
  a.lnk{color:#0F0F17;font-weight:700;text-decoration:none}a.lnk:hover{text-decoration:underline}
  .muted{color:#5F5D6E}
  .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}
  .stat{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:16px 18px}
  .stat .v{font-family:var(--font-display);font-size:24px;font-weight:800}
  .stat .l{font-size:12.5px;color:#5F5D6E;margin-top:2px}
  .photos{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
  .photos a{display:block;border:1px solid #E5E4EC;border-radius:10px;overflow:hidden;text-decoration:none;color:#5F5D6E;font-size:12px}
  .photos img{width:100%;height:120px;object-fit:cover;display:block}
  .photos span{display:block;padding:6px 8px}
  .alert-info{background:#F0F4FE;border:1px solid #D7E0FA;color:var(--amber);padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
  .soon{padding:40px 20px;text-align:center;color:#5F5D6E}
  @media(max-width:700px){.kv{grid-template-columns:1fr 1fr}.stats{grid-template-columns:1fr}}
</style>

<div class="crumb"><a href="{{ route('admin.assets.index') }}">Assets</a> / {{ $asset->asset_code }}</div>

<div class="head">
  <div>
    <span class="code-pill">{{ $asset->asset_code }}</span>
    <span class="status {{ $statusClass[$asset->status] ?? 'status-off' }}" style="margin-left:6px">{{ $statusLabels[$asset->status] ?? $asset->status }}</span>
    <h1>{{ $asset->name }}</h1>
  </div>
  <div class="actions">
    <a class="btn" href="{{ route('admin.assets.label', $asset) }}" target="_blank">Print label</a>
    @if ($canManage && ! $asset->is_decommissioned)
      <a class="btn" href="{{ route('admin.assets.edit', $asset) }}">Edit</a>
    @endif
  </div>
</div>

@if ($asset->is_decommissioned)
  <div class="alert-info">This asset is decommissioned. It can no longer be chosen for new requests, but its history is kept here.</div>
@endif

<div class="tabs" id="tabs">
  <button class="tab on" data-p="overview">Overview</button>
  <button class="tab" data-p="requests">Requests<span class="n">{{ $requests->count() }}</span></button>
  <button class="tab" data-p="cost">Cost</button>
  <button class="tab" data-p="photos">Photos<span class="n">{{ $photos->count() }}</span></button>
  <button class="tab" data-p="checklists">Checklists</button>
  <button class="tab" data-p="maintenance">Maintenance</button>
</div>

{{-- OVERVIEW --}}
<div class="pane on" id="p-overview">
  <div class="card">
    <h3>Identity</h3>
    <dl class="kv">
      <dt>Client</dt><dd>{{ $asset->client?->name ?? '—' }}</dd>
      <dt>Site</dt><dd>{{ $asset->site?->name ?? '—' }}</dd>
      <dt>Category</dt><dd>{{ $asset->category?->name ?? '—' }}</dd>
      <dt>Location within site</dt><dd>{{ $asset->location_within_site ?: '—' }}</dd>
      <dt>Manufacturer</dt><dd>{{ $asset->manufacturer ?: '—' }}</dd>
      <dt>Model</dt><dd>{{ $asset->model ?: '—' }}</dd>
      <dt>Serial number</dt><dd>{{ $asset->serial_number ?: '—' }}</dd>
      <dt>Installed</dt><dd>{{ $asset->installation_date?->format('d M Y') ?? '—' }}</dd>
      <dt>Parent</dt>
      <dd>@if ($asset->parent)<a class="lnk" href="{{ route('admin.assets.show', $asset->parent) }}">{{ $asset->parent->asset_code }} — {{ $asset->parent->name }}</a>@else — @endif</dd>
      <dt>Child assets</dt>
      <dd>
        @forelse ($asset->children as $c)
          <div><a class="lnk" href="{{ route('admin.assets.show', $c) }}">{{ $c->asset_code }} — {{ $c->name }}</a></div>
        @empty — @endforelse
      </dd>
      @if ($asset->notes)<dt>Notes</dt><dd style="font-weight:400">{{ $asset->notes }}</dd>@endif
    </dl>
  </div>

  <div class="card">
    <h3>Warranty and cover</h3>
    <dl class="kv">
      <dt>Warranty</dt>
      <dd>
        @if ($asset->is_decommissioned) —
        @elseif ($asset->warranty_status === 'in_warranty')<span class="status status-ok">In warranty · {{ $asset->warranty_expiry_date->format('d M Y') }}</span>
        @elseif ($asset->warranty_status === 'expired')<span class="status status-bad">Expired · {{ $asset->warranty_expiry_date->format('d M Y') }}</span>
        @else No warranty date @endif
      </dd>
      <dt>Contract cover</dt><dd class="muted" style="font-weight:400">Shown here once contracts are available.</dd>
    </dl>
  </div>
</div>

{{-- REQUESTS --}}
<div class="pane" id="p-requests">
  <div class="card" style="padding:0">
    <div class="tw">
      <table>
        <thead><tr><th>Request</th><th>Title</th><th>Status</th><th>Priority</th><th>Raised</th><th>Completed</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
          <tr>
            <td><a class="lnk" href="{{ route('service-requests.show', $r) }}">{{ $r->sr_number }}</a></td>
            <td>{{ $r->title }}</td>
            <td>{{ $srStatus[$r->status] ?? $r->status }}</td>
            <td>{{ ucfirst($r->priority) }}</td>
            <td>{{ $r->created_at?->format('d M Y') }}</td>
            <td>{{ $r->completed_at?->format('d M Y') ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="soon">No requests have been raised for this asset yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- COST --}}
<div class="pane" id="p-cost">
  <div class="stats">
    <div class="stat"><div class="v">{{ $money($expenseTotal) }}</div><div class="l">Approved expenses ({{ $expenseCount }})</div></div>
    <div class="stat"><div class="v">{{ $money($invoicedTotal) }}</div><div class="l">Invoiced ({{ $invoiceCount }} {{ $invoiceCount === 1 ? 'invoice' : 'invoices' }})</div></div>
    <div class="stat"><div class="v">{{ $requests->count() }}</div><div class="l">Requests</div></div>
  </div>
  <div class="card" style="padding:0">
    <div class="tw">
      <table>
        <thead><tr><th>Request</th><th>Title</th><th style="text-align:right">Approved expenses</th><th style="text-align:right">Invoiced</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
          @php $e = (float) ($expenseBySr[$r->id] ?? 0); $i = (float) ($invoicedBySr[$r->id] ?? 0); @endphp
          @if ($e > 0 || $i > 0)
          <tr>
            <td><a class="lnk" href="{{ route('service-requests.show', $r) }}">{{ $r->sr_number }}</a></td>
            <td>{{ $r->title }}</td>
            <td style="text-align:right">{{ $e > 0 ? $money($e) : '—' }}</td>
            <td style="text-align:right">{{ $i > 0 ? $money($i) : '—' }}</td>
          </tr>
          @endif
        @empty
        @endforelse
        @if ($expenseTotal == 0 && $invoicedTotal == 0)
          <tr><td colspan="4" class="soon">No approved expenses or invoices for this asset yet.</td></tr>
        @endif
        </tbody>
      </table>
    </div>
  </div>
  <p class="muted" style="font-size:12.5px">Expenses are approved expenses on this asset's requests. Invoiced counts approved, sent and paid invoices, in {{ $currency }}.</p>
</div>

{{-- PHOTOS --}}
<div class="pane" id="p-photos">
  @if ($photos->isEmpty())
    <div class="card soon">No photos yet.</div>
  @else
    <div class="photos">
      @foreach ($photos as $p)
        <a href="{{ $p->url }}" target="_blank" rel="noopener">
          <img src="{{ $p->url }}" alt="" loading="lazy">
          <span>
            {{ ['before_photo' => 'Before', 'after_photo' => 'After', 'asset_photo' => 'Asset photo'][$p->kind] ?? 'Attachment' }}
            @if ($p->attachable_type === \App\Models\ServiceRequest::class && isset($srNumbers[$p->attachable_id])) · {{ $srNumbers[$p->attachable_id] }}@endif
          </span>
        </a>
      @endforeach
    </div>
  @endif
</div>

{{-- COMING WITH LATER SLICES --}}
<div class="pane" id="p-checklists"><div class="card soon">Checklist history will appear here when forms and checklists are added.</div></div>
<div class="pane" id="p-maintenance"><div class="card soon">Upcoming maintenance will appear here when preventive maintenance is added.</div></div>

<script>
(function () {
  const tabs = document.querySelectorAll('#tabs .tab');
  function open(name) {
    tabs.forEach(t => t.classList.toggle('on', t.dataset.p === name));
    document.querySelectorAll('.pane').forEach(p => p.classList.toggle('on', p.id === 'p-' + name));
    try { history.replaceState(null, '', '#' + name); } catch (e) {}
  }
  tabs.forEach(t => t.addEventListener('click', () => open(t.dataset.p)));
  const h = location.hash.replace('#', '');
  if (h && document.getElementById('p-' + h)) open(h);
})();
</script>
@endsection