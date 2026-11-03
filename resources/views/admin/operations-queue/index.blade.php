@extends('layouts.admin')
@section('title', 'Operations queue')

@section('content')
<style>
  .ops-head{margin-bottom:22px}
  .ops-head h1{font-family:'Figtree';font-size:34px;font-weight:800;letter-spacing:-.02em;margin:0;color:#0F0F17}
  .ops-head p{color:#5F5D6E;font-size:14px;margin-top:6px}

  .top-tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
  @media (max-width:900px){ .top-tiles{grid-template-columns:1fr} }

  .tile{background:#fff;border:1px solid #E5E4EC;border-radius:16px;padding:18px 22px}
  .tile .lbl{color:#5F5D6E;font-size:13px;font-weight:600;margin-bottom:10px}
  .tile .val{font-family:'Figtree';font-size:32px;font-weight:800;color:#0F0F17;line-height:1}

  .cols{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
  @media (max-width:960px){ .cols{grid-template-columns:1fr} }

  .section{background:#fff;border:1px solid #E5E4EC;border-radius:16px;padding:20px}
  .section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
  .section-head h3{font-family:'Figtree';font-size:17px;font-weight:700;color:#0F0F17;margin:0;display:flex;align-items:center;gap:9px}
  .count-chip{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:22px;padding:0 8px;border-radius:999px;background:#F3F4F8;color:#5F5D6E;font-size:12px;font-weight:600}
  .see-all{color:#0F0F17;font-size:13px;font-weight:600;text-decoration:underline;text-underline-offset:3px}
  .see-all:hover{color:#2F5BEA}

  .row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-top:1px solid #F2F1F6;gap:12px}
  .row:first-of-type{border-top:0}
  .row-main{min-width:0;flex:1}
  .sr-line{font-size:14px;color:#0F0F17;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .sr-line a{text-decoration:none;color:inherit}
  .sr-num{font-weight:700}
  .sub-line{color:#5F5D6E;font-size:12.5px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

  .row-right{display:flex;align-items:center;gap:8px;flex-shrink:0}

  .pri-pill{display:inline-block;padding:3px 11px;border-radius:999px;font-size:11.5px;font-weight:600}
  .pri-emergency{background:#FDECE7;color:#C2381B}
  .pri-critical{background:#FFEDD5;color:#C2410C}
  .pri-high{background:#FFF4CC;color:#8A5A00}
  .pri-medium{background:#DBEAFE;color:#1E40AF}
  .pri-low{background:#F3F4F8;color:#5F5D6E}

  .btn-approve{display:inline-flex;align-items:center;padding:6px 14px;border-radius:9px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;font-family:'DM Sans';font-size:12.5px;font-weight:700;cursor:pointer;text-decoration:none;transition:all .12s}
  .btn-approve:hover{border-color:#0F0F17;background:#FAFAFC}

  .empty-sec{color:#8B89A0;font-size:13px;text-align:center;padding:28px 10px}
</style>

<div class="ops-head">
  <h1>Operations queue</h1>
  <p>What needs your action.</p>
</div>

{{-- ============== TOP METRIC TILES ============== --}}
<div class="top-tiles">
  <div class="tile">
    <div class="lbl">Geofence overrides waiting</div>
    <div class="val">{{ $geofenceWaiting }}</div>
  </div>
  <div class="tile">
    <div class="lbl">Field conflicts to review</div>
    <div class="val">{{ $fieldConflicts }}</div>
  </div>
  <div class="tile">
    <div class="lbl">On hold for more than 24 hours</div>
    <div class="val">{{ $onHoldLong }}</div>
  </div>
</div>

{{-- ============== PENDING APPROVAL + PENDING QC ============== --}}
<div class="cols">
  {{-- Pending approval --}}
  <div class="section">
    <div class="section-head">
      <h3>Pending approval <span class="count-chip">{{ $pendingApprovalTotal }}</span></h3>
      <a href="{{ route('service-requests.index', ['stage' => 'needs']) }}" class="see-all">See all</a>
    </div>
    @forelse ($pendingApproval as $sr)
      <div class="row">
        <div class="row-main">
          <div class="sr-line">
            <a href="{{ route('service-requests.show', $sr) }}">
              <span class="sr-num">{{ $sr->sr_number }}</span>
              {{ $sr->title ?? '—' }}
            </a>
          </div>
          <div class="sub-line">{{ $sr->client->name ?? '—' }} | waited {{ \App\Http\Controllers\Admin\OperationsQueueController::waited($sr->created_at) }}</div>
        </div>
        <div class="row-right">
          <span class="pri-pill pri-{{ $sr->priority }}">{{ ucfirst($sr->priority) }}</span>
          <a href="{{ route('service-requests.show', $sr) }}" class="btn-approve">Approve</a>
        </div>
      </div>
    @empty
      <div class="empty-sec">No pending approvals.</div>
    @endforelse
  </div>

  {{-- Pending QC review --}}
  <div class="section">
    <div class="section-head">
      <h3>Pending QC review <span class="count-chip">{{ $pendingQcTotal }}</span></h3>
      <a href="{{ route('service-requests.index', ['stage' => 'review']) }}" class="see-all">See all</a>
    </div>
    @forelse ($pendingQc as $sr)
      <div class="row">
        <div class="row-main">
          <div class="sr-line">
            <a href="{{ route('service-requests.show', $sr) }}">
              <span class="sr-num">{{ $sr->sr_number }}</span>
              {{ $sr->title ?? '—' }}
            </a>
          </div>
          <div class="sub-line">{{ $sr->client->name ?? '—' }} | waited {{ \App\Http\Controllers\Admin\OperationsQueueController::waited($sr->last_punch_out_at) }}</div>
        </div>
        <div class="row-right">
          <span class="pri-pill pri-{{ $sr->priority }}">{{ ucfirst($sr->priority) }}</span>
        </div>
      </div>
    @empty
      <div class="empty-sec">No QC reviews pending.</div>
    @endforelse
  </div>
</div>

{{-- ============== OVERDUE + AWAITING INVOICE ============== --}}
<div class="cols">
  {{-- Overdue --}}
  <div class="section">
    <div class="section-head">
      <h3>Overdue <span class="count-chip">{{ $overdueTotal }}</span></h3>
      <a href="{{ route('service-requests.index', ['overdue' => 1]) }}" class="see-all">See all</a>
    </div>
    @forelse ($overdue as $sr)
      <div class="row">
        <div class="row-main">
          <div class="sr-line">
            <a href="{{ route('service-requests.show', $sr) }}">
              <span class="sr-num">{{ $sr->sr_number }}</span>
              {{ $sr->title ?? '—' }}
            </a>
          </div>
          <div class="sub-line">{{ $sr->client->name ?? '—' }} | waited {{ \App\Http\Controllers\Admin\OperationsQueueController::waited($sr->sla_resolution_due_at) }}</div>
        </div>
        <div class="row-right">
          <span class="pri-pill pri-{{ $sr->priority }}">{{ ucfirst($sr->priority) }}</span>
        </div>
      </div>
    @empty
      <div class="empty-sec">Nothing overdue — nice.</div>
    @endforelse
  </div>

  {{-- Awaiting invoice --}}
  <div class="section">
    <div class="section-head">
      <h3>Awaiting invoice <span class="count-chip">{{ $awaitingInvoiceTotal }}</span></h3>
      <a href="{{ route('service-requests.index', ['stage' => 'invoice']) }}" class="see-all">See all</a>
    </div>
    @forelse ($awaitingInvoice as $sr)
      <div class="row">
        <div class="row-main">
          <div class="sr-line">
            <a href="{{ route('service-requests.show', $sr) }}">
              <span class="sr-num">{{ $sr->sr_number }}</span>
              {{ $sr->title ?? '—' }}
            </a>
          </div>
          <div class="sub-line">{{ $sr->client->name ?? '—' }} | waited {{ \App\Http\Controllers\Admin\OperationsQueueController::waited($sr->updated_at) }}</div>
        </div>
        <div class="row-right">
          <span class="pri-pill pri-{{ $sr->priority }}">{{ ucfirst($sr->priority) }}</span>
        </div>
      </div>
    @empty
      <div class="empty-sec">No invoices waiting.</div>
    @endforelse
  </div>
</div>
@endsection