@extends('layouts.admin')
@section('title', $asset->asset_code)

@section('content')
@php
    $statusLabels = ['active' => 'Active', 'under_repair' => 'Under repair', 'inactive' => 'Inactive', 'decommissioned' => 'Decommissioned'];
    $statusClass  = ['active' => 'status-ok', 'under_repair' => 'status-warn', 'inactive' => 'status-off', 'decommissioned' => 'status-off'];
@endphp
<style>
  .qr-wrap{max-width:720px}
  .code-pill{display:inline-block;font-size:12.5px;font-weight:600;color:var(--amber);background:var(--yellow-soft);padding:5px 11px;border-radius:7px}
  .qr-wrap h1{font-family:var(--font-display),sans-serif;font-size:30px;font-weight:800;letter-spacing:-.02em;margin:10px 0 6px;color:#0F0F17}
  .status{display:inline-flex;align-items:center;font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:999px;white-space:nowrap}
  .status-ok{background:#E6F7EC;color:#1D7A3A}.status-warn{background:#FEF3C7;color:#92400E}
  .status-off{background:#F2F1F6;color:#5F5D6E}.status-bad{background:#FDECE7;color:#C2381B}
  .card{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:22px;margin-top:18px}
  .card h3{font-family:var(--font-display);font-size:15px;font-weight:700;margin:0 0 12px}
  .kv{display:grid;grid-template-columns:150px 1fr;gap:10px 16px;font-size:14px}
  .kv dt{color:#5F5D6E}.kv dd{margin:0;color:#0F0F17;font-weight:600}
  .alert-info{background:#F0F4FE;border:1px solid #D7E0FA;color:var(--amber);padding:12px 14px;border-radius:10px;margin-top:16px;font-size:13.5px}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:10px 18px;font-size:14px;font-weight:700;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;text-decoration:none}
  .btn:hover{border-color:#0F0F17}
  .recent a{color:#0F0F17;text-decoration:none;font-weight:700}
  .recent .row{padding:10px 0;border-bottom:1px solid #F2F1F6;font-size:13.5px}
  .recent .row:last-child{border-bottom:0}
  .muted{color:#5F5D6E}
</style>

<div class="qr-wrap">
  <span class="code-pill">{{ $asset->asset_code }}</span>
  <h1>{{ $asset->name }}</h1>
  <span class="status {{ $statusClass[$asset->status] ?? 'status-off' }}">{{ $statusLabels[$asset->status] ?? $asset->status }}</span>

  @if ($asset->is_decommissioned)
    <div class="alert-info">This asset is decommissioned. It can no longer be chosen for new service requests, but its history is kept.</div>
  @endif

  <div class="card">
    <h3>Details</h3>
    <dl class="kv">
      <dt>Client</dt><dd>{{ $asset->client?->name ?? '—' }}</dd>
      <dt>Site</dt><dd>{{ $asset->site?->name ?? '—' }}</dd>
      <dt>Category</dt><dd>{{ $asset->category?->name ?? '—' }}</dd>
      <dt>Location</dt><dd>{{ $asset->location_within_site ?: '—' }}</dd>
      <dt>Make / model</dt><dd>{{ trim(($asset->manufacturer ?? '') . ' ' . ($asset->model ?? '')) ?: '—' }}</dd>
      <dt>Serial number</dt><dd>{{ $asset->serial_number ?: '—' }}</dd>
      <dt>Warranty</dt>
      <dd>
        @if ($asset->is_decommissioned)
          —
        @elseif ($asset->warranty_status === 'in_warranty')
          <span class="status status-ok">In warranty · {{ $asset->warranty_expiry_date->format('d M Y') }}</span>
        @elseif ($asset->warranty_status === 'expired')
          <span class="status status-bad">Expired · {{ $asset->warranty_expiry_date->format('d M Y') }}</span>
        @else
          No warranty date
        @endif
      </dd>
    </dl>
  </div>

  <div class="card recent">
    <h3>Recent requests</h3>
    @forelse ($recent as $r)
      <div class="row">
        <a href="{{ route('service-requests.show', $r) }}">{{ $r->sr_number }}</a>
        <span class="muted"> · {{ $r->title }} · {{ ucfirst(str_replace('_', ' ', $r->status)) }} · {{ $r->created_at?->format('d M Y') }}</span>
      </div>
    @empty
      <div class="muted">No requests for this asset yet.</div>
    @endforelse
  </div>

  @if ($raiseUrl || ($canManage && ! $asset->is_decommissioned))
    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
      @if ($raiseUrl)
        <a class="btn" style="background:var(--amber);color:#fff;border-color:var(--amber)" href="{{ $raiseUrl }}">Raise a request</a>
      @endif
      @if ($canManage && ! $asset->is_decommissioned)
        <a class="btn" href="{{ route('admin.assets.edit', $asset) }}">Edit asset</a>
        <a class="btn" href="{{ route('admin.assets.show', $asset) }}">Open full details</a>
      @endif
    </div>
  @endif
</div>
@endsection