@extends('layouts.admin')

@php
    $statusClass = ['draft' => 'status-off', 'active' => 'status-ok', 'expiring_soon' => 'status-warn', 'expired' => 'status-bad', 'renewed' => 'status-off', 'cancelled' => 'status-off'];
    $fmt   = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : '—';
    $used  = (int) $contract->calls_used_current_period;
    $cap   = $contract->max_calls;
    $pct   = $cap ? min(100, (int) round($used / $cap * 100)) : 0;
    $barBg = ! $cap ? '#9A98A7' : ($used > $cap ? '#C2381B' : ($used >= $cap * 0.8 ? '#D97706' : '#1D7A3A'));
    $tabs  = ['details' => 'Details', 'calls' => 'Calls', 'requests' => 'Requests'] + ($showMoney ? ['documents' => 'Documents'] : []) + ['history' => 'History'];
    $money = fn ($v) => $v !== null && $v !== '' ? number_format((float) $v, 3) : '—';
@endphp

@section('title', $contract->contract_number)

@section('content')
<style>
  .crumb{font-size:13px;color:#5F5D6E;margin-bottom:12px}
  .crumb a{color:#0F0F17;text-decoration:underline;text-underline-offset:3px}
  .head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px}
  .head h1{font-family:var(--font-display),sans-serif;font-size:34px;font-weight:800;line-height:1.08;letter-spacing:-.02em;margin:0;color:#0F0F17}
  .meta-row{display:flex;gap:8px;align-items:center;margin-top:12px;flex-wrap:wrap;color:#5F5D6E;font-size:13.5px}
  .code-pill{display:inline-block;font-size:12.5px;font-weight:600;color:var(--amber);background:var(--yellow-soft);padding:5px 11px;border-radius:7px}
  .status{display:inline-flex;align-items:center;font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:999px;white-space:nowrap}
  .status-ok{background:#E6F7EC;color:#1D7A3A}.status-warn{background:#FEF3C7;color:#92400E}.status-off{background:#F2F1F6;color:#5F5D6E}.status-bad{background:#FDECE7;color:#C2381B}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:10px 18px;font-family:var(--font-body);font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;white-space:nowrap;background:#fff}
  .btn-primary{background:var(--amber);color:#fff}
  .btn-ghost{color:#0F0F17;border-color:#E5E4EC}.btn-ghost:hover{border-color:#0F0F17}
  .btn-danger-out{color:#C7372F;border-color:#F0C7C7}.btn-danger-out:hover{background:#FFF5F5;border-color:#C7372F}
  .acts{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start}
  .alert-err{background:#FDECE7;border:1px solid #F2C2C2;color:#C2381B;padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
  .alert-ok{background:#E6F7EC;border:1px solid #BFE5CB;color:#1D7A3A;padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
  .tabs{display:flex;gap:4px;border-bottom:1px solid #E5E4EC;margin-bottom:18px;flex-wrap:wrap}
  .tabs a{padding:11px 16px;font-size:14px;font-weight:700;color:#5F5D6E;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px}
  .tabs a.on{color:#0F0F17;border-bottom-color:var(--amber)}
  .card{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:22px;margin-bottom:16px}
  .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px 24px}
  @media (max-width:760px){.grid{grid-template-columns:1fr 1fr}}
  .k{font-size:12.5px;color:#5F5D6E;margin-bottom:4px}.v{font-size:14.5px;font-weight:600;color:#0F0F17}
  .sect{font-family:var(--font-display),sans-serif;font-size:16px;font-weight:800;margin:0 0 14px}
  table.t{width:100%;border-collapse:collapse;font-size:14px}
  table.t th{text-align:left;padding:12px 14px;font-size:13px;color:#5F5D6E;background:#FAFAFC;border-bottom:1px solid #EEEDF3;white-space:nowrap}
  table.t td{padding:12px 14px;border-bottom:1px solid #F2F1F6;vertical-align:top}
  .tblwrap{overflow-x:auto}
  .muted{color:#5F5D6E}
  .bar{height:12px;border-radius:999px;background:#F2F1F6;overflow:hidden;margin:10px 0 6px}
  .bar>span{display:block;height:100%;border-radius:999px}
  .chips{display:flex;flex-wrap:wrap;gap:6px}
  .chip{background:#F2F1F6;border-radius:999px;padding:4px 11px;font-size:12.5px;font-weight:600;color:#0F0F17}
  details.cancel{border:1px solid #F0C7C7;border-radius:12px;padding:0;background:#fff}
  details.cancel summary{list-style:none;cursor:pointer;padding:10px 18px;font-weight:700;font-size:14px;color:#C7372F}
  details.cancel summary::-webkit-details-marker{display:none}
  details.cancel[open]{padding-bottom:14px;min-width:300px}
  details.cancel .in{padding:0 14px}
  details.cancel textarea{width:100%;box-sizing:border-box;font-family:inherit;font-size:14px;padding:10px;border:1px solid #E5E4EC;border-radius:10px;min-height:70px;margin:8px 0}
  .errtxt{font-size:12.5px;color:#C7372F;margin-bottom:8px;font-weight:600}
  .empty{padding:36px 10px;text-align:center;color:#5F5D6E}
</style>

<div class="crumb"><a href="{{ route('admin.contracts.index') }}">{{ $plural }}</a> / {{ $contract->contract_number }}</div>

<div class="head">
  <div>
    <h1>{{ $contract->contract_number }}</h1>
    <div class="meta-row">
      <span class="status {{ $statusClass[$contract->status] ?? 'status-off' }}">{{ $contract->status_label }}</span>
      <span class="code-pill">{{ $contract->type_label }}</span>
      <span><strong>{{ $contract->client?->name ?? '—' }}</strong></span>
      <span>· {{ $fmt($contract->start_date) }} to {{ $fmt($contract->end_date) }}</span>
    </div>
  </div>

  @if ($canManage)
  <div class="acts">
    @if (! in_array($contract->status, ['cancelled', 'renewed'], true))
      <a href="{{ route('admin.contracts.edit', $contract) }}" class="btn btn-ghost">Edit</a>
    @endif
    @if ($contract->status === 'draft')
      <form method="POST" action="{{ route('admin.contracts.activate', $contract) }}">@csrf<button class="btn btn-primary">Activate</button></form>
    @endif
    @if ($contract->isRenewable())
      <form method="POST" action="{{ route('admin.contracts.renew', $contract) }}">@csrf<button class="btn btn-ghost">Renew</button></form>
    @endif
    @if ($contract->isCancellable())
      <details class="cancel" @if ($errors->has('cancel_reason')) open @endif>
        <summary>Cancel contract</summary>
        <form method="POST" action="{{ route('admin.contracts.cancel', $contract) }}" class="in">
          @csrf
          <label for="cancel_reason" style="font-size:13px;font-weight:600">Reason</label>
          <textarea id="cancel_reason" name="cancel_reason" maxlength="255">{{ old('cancel_reason') }}</textarea>
          @error('cancel_reason')<div class="errtxt">{{ $message }}</div>@enderror
          <button class="btn btn-danger-out" type="submit" onclick="return confirm('Cancel this contract? It stops matching at once.')">Confirm cancel</button>
        </form>
      </details>
    @endif
    @if ($contract->isDeletable())
      <form method="POST" action="{{ route('admin.contracts.destroy', $contract) }}" onsubmit="return confirm('Delete this draft?')">@csrf @method('DELETE')<button class="btn btn-danger-out">Delete</button></form>
    @endif
  </div>
  @endif
</div>

@if (session('success'))<div class="alert-ok">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert-err">{{ session('error') }}</div>@endif

<div class="tabs">
  @foreach ($tabs as $k => $v)
    <a href="{{ route('admin.contracts.show', [$contract, 'tab' => $k]) }}" class="{{ $tab === $k ? 'on' : '' }}">{{ $v }}</a>
  @endforeach
</div>

{{-- ------------------------------------------------------------- Details --}}
@if ($tab === 'details')
  <div class="card">
    <div class="grid">
      <div><div class="k">Client</div><div class="v">{{ $contract->client?->name ?? '—' }}</div></div>
      <div><div class="k">Type</div><div class="v">{{ $contract->type_label }}</div></div>
      <div><div class="k">Auto-renew</div><div class="v">{{ $contract->auto_renew ? 'On' : 'Off' }}</div></div>
      <div><div class="k">Start date</div><div class="v">{{ $fmt($contract->start_date) }}</div></div>
      <div><div class="k">End date</div><div class="v">{{ $fmt($contract->end_date) }}</div></div>
      <div><div class="k">Activated</div><div class="v">{{ $fmt($contract->activated_at) }}</div></div>
      <div><div class="k">Response time</div><div class="v">{{ $contract->response_sla_minutes ? $contract->response_sla_minutes . ' min' : 'Normal SLA policy' }}</div></div>
      <div><div class="k">Resolution time</div><div class="v">{{ $contract->resolution_sla_minutes ? $contract->resolution_sla_minutes . ' min' : 'Normal SLA policy' }}</div></div>
      <div><div class="k">Call cap</div><div class="v">{{ $cap ? $cap . ' · ' . (\App\Models\Contract::CALL_PERIODS[$contract->call_period] ?? '') : 'No cap' }}</div></div>
      @if ($showMoney)
        <div><div class="k">Annual value</div><div class="v">{{ $money($contract->annual_value) }}</div></div>
        <div><div class="k">Payment terms</div><div class="v">{{ $contract->payment_terms ?: '—' }}</div></div>
      @endif
      @if ($contract->status === 'cancelled')
        <div><div class="k">Cancelled</div><div class="v">{{ $fmt($contract->cancelled_at) }}</div></div>
        <div style="grid-column:span 2"><div class="k">Reason</div><div class="v">{{ $contract->cancel_reason }}</div></div>
      @endif
      @if ($contract->auto_renewed_at)
        <div><div class="k">Auto-renewed</div><div class="v">{{ $fmt($contract->auto_renewed_at) }}</div></div>
      @endif
    </div>
  </div>

  <div class="card">
    <p class="sect">Coverage</p>
    <div class="k">Sites</div>
    <div class="chips" style="margin-bottom:14px">
      @forelse ($sites as $s)<span class="chip">{{ $s->name }}</span>@empty<span class="muted">Covers all of this client's sites</span>@endforelse
    </div>
    <div class="k">Categories</div>
    <div class="chips">
      @forelse ($cats as $c)<span class="chip">{{ $c->name }}</span>@empty<span class="muted">Covers all categories</span>@endforelse
    </div>
  </div>

  @if ($contract->notes)
    <div class="card"><p class="sect">Notes</p><div style="white-space:pre-wrap">{{ $contract->notes }}</div></div>
  @endif
@endif

{{-- --------------------------------------------------------------- Calls --}}
@if ($tab === 'calls')
  <div class="card">
    <p class="sect">Current period</p>
    <div style="font-size:15px"><strong>{{ $used }}</strong> {{ $cap ? 'of ' . $cap . ' calls used' : 'calls used · no cap' }}</div>
    @if ($cap)
      <div class="bar"><span style="width:{{ $pct }}%;background:{{ $barBg }}"></span></div>
      <div class="muted" style="font-size:13px">
        @if ($used > $cap) Over the cap @elseif ($used >= $cap * 0.8) Near the cap @else {{ $cap - $used }} left @endif
        · period started {{ $fmt($contract->period_started_on) }}
      </div>
    @endif
  </div>

  <div class="card" style="padding:0">
    <div class="tblwrap">
      <table class="t">
        <thead><tr><th>Request</th><th>Date</th><th>Action</th><th>By</th><th>Over cap</th></tr></thead>
        <tbody>
          @forelse ($calls as $row)
            <tr>
              <td>{{ $row->sr_number ?? '—' }}</td>
              <td class="muted">{{ $fmt($row->created_at) }}</td>
              <td>{{ $row->action === 'counted' ? 'Counted' : 'Given back' }}</td>
              <td class="muted">{{ $row->user_name ?? 'System' }}</td>
              <td>{{ $row->over_cap ? 'Yes' : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty">No calls recorded yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endif

{{-- ------------------------------------------------------------ Requests --}}
@if ($tab === 'requests')
  <div class="card" style="padding:0">
    <div class="tblwrap">
      <table class="t">
        <thead><tr><th>Request</th><th>Status</th><th>Coverage</th><th>Created</th></tr></thead>
        <tbody>
          @forelse ($requests as $r)
            <tr>
              <td>{{ $r->sr_number }}</td>
              <td>{{ ucwords(str_replace('_', ' ', (string) $r->status)) }}</td>
              <td class="muted">{{ ucwords(str_replace('_', ' ', (string) $r->coverage_type)) ?: '—' }}</td>
              <td class="muted">{{ $fmt($r->created_at) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="empty">No requests under this contract yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endif

{{-- ----------------------------------------------------------- Documents --}}
@if ($tab === 'documents' && $showMoney)
  <div class="card" style="padding:0">
    <div class="tblwrap">
      <table class="t">
        <thead><tr><th>File</th><th>Size</th><th>Added</th><th></th></tr></thead>
        <tbody>
          @forelse ($documents as $d)
            <tr>
              <td><a href="{{ route('admin.contracts.documents.download', [$contract, $d->id]) }}">{{ $d->original_name }}</a></td>
              <td class="muted">{{ number_format($d->size_bytes / 1048576, 2) }} MB</td>
              <td class="muted">{{ $fmt($d->created_at) }}</td>
              <td style="text-align:right">
                @if ($canManage)
                  <form method="POST" action="{{ route('admin.contracts.documents.destroy', [$contract, $d->id]) }}" onsubmit="return confirm('Remove this file?')">@csrf @method('DELETE')<button class="btn btn-danger-out" style="padding:6px 12px">Remove</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="empty">No files attached.@if ($canManage) Add them from Edit.@endif</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endif

{{-- ------------------------------------------------------------- History --}}
@if ($tab === 'history')
  <div class="card" style="padding:0">
    <div class="tblwrap">
      <table class="t">
        <thead><tr><th>When</th><th>Who</th><th>What</th><th>Change</th></tr></thead>
        <tbody>
          @forelse ($history as $h)
            <tr>
              <td class="muted" style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($h->created_at)->format('d M Y H:i') }}</td>
              <td>{{ $h->actor_name ?: 'System' }}</td>
              <td>{{ ucwords(str_replace(['contract.', '_'], ['', ' '], $h->action)) }}</td>
              <td class="muted" style="font-size:13px">
                @foreach (array_diff_key($h->new_values ?? [], ['contract_number' => 1]) as $key => $val)
                  <div>{{ str_replace('_', ' ', $key) }}:
                    @if (array_key_exists($key, $h->old_values ?? []))
                      {{ is_array($h->old_values[$key]) ? implode(', ', $h->old_values[$key]) : $h->old_values[$key] }} →
                    @endif
                    {{ is_array($val) ? implode(', ', $val) : $val }}
                  </div>
                @endforeach
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="empty">No history yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endif
@endsection
