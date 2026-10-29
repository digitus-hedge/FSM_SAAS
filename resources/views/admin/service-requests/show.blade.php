@extends('layouts.admin')
@section('title', $sr->sr_number)

@section('content')
<style>
  .breadcrumb{font-size:12px;color:var(--muted);margin-bottom:6px}
  .breadcrumb a{color:var(--ink);text-decoration:none;border-bottom:1px solid var(--line)}
  .breadcrumb a:hover{color:var(--amber);border-color:var(--yellow)}

  .sr-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:22px;flex-wrap:wrap}
  .sr-head h1{font-family:'Figtree';font-size:26px;font-weight:700;letter-spacing:-.3px;line-height:1.2;margin-bottom:4px}
  .sr-head .num{display:inline-block;font-family:'DM Sans';font-size:12px;font-weight:600;color:var(--amber);background:var(--yellow-soft);padding:3px 10px;border-radius:999px;letter-spacing:.03em;margin-bottom:8px}
  .sr-head .meta{font-size:12px;color:var(--muted)}
  .sr-head .actions{display:flex;gap:8px;flex-shrink:0}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:#F0B700}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .pill{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px}
  .pill-dot{width:5px;height:5px;border-radius:50%;background:currentColor}
  .pill-gray{background:var(--soft);color:var(--muted)}
  .pill-amber{background:var(--yellow-soft);color:var(--amber)}
  .pill-orange{background:#FFEDD5;color:#C2410C}
  .pill-red{background:var(--red-soft);color:var(--red)}
  .pill-green{background:var(--green-soft);color:var(--green)}
  .pill-blue{background:#DBEAFE;color:#1E40AF}

  .grid{display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:flex-start}
  @media (max-width:960px){ .grid{grid-template-columns:1fr} }

  .card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-bottom:16px}
  .card-head{display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid var(--line);background:#FCFBF8}
  .card-head svg{color:var(--amber);width:18px;height:18px;flex-shrink:0}
  .card-head h3{font-family:'Figtree';font-size:14px;font-weight:700;color:var(--ink);letter-spacing:.02em;margin:0}
  .card-head .right{margin-left:auto}
  .card-body{padding:18px 20px}

  .kv{display:grid;grid-template-columns:140px 1fr;gap:10px 20px;font-size:13px;line-height:1.6}
  .kv dt{color:var(--muted);font-weight:500}
  .kv dd{color:var(--ink);margin:0;font-weight:500}
  .kv dd.empty{color:var(--subtle);font-weight:400}

  .sla-rows{padding:4px 0}
  .sla-row{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;font-size:13px;border-top:1px solid var(--line)}
  .sla-row:first-child{border-top:0}
  .sla-row .lbl{color:var(--muted)}
  .sla-row .val{color:var(--ink);font-weight:600;text-align:right}
  .sla-row .val .sub{display:block;font-size:11px;color:var(--subtle);font-weight:400;margin-top:1px}

  .desc-box{font-size:13px;color:var(--ink);line-height:1.6;white-space:pre-wrap}
  .desc-box.empty{color:var(--subtle);font-style:italic}

  .link{color:var(--amber);text-decoration:none;font-weight:600;border-bottom:1px solid var(--yellow)}
  .link:hover{color:var(--ink)}

  .warn-banner{background:#FFF7F7;border:1px solid #FEE2E2;color:var(--red);padding:10px 14px;border-radius:10px;font-size:12px;margin:0 20px 16px;display:flex;gap:8px;align-items:flex-start}
  .warn-banner svg{flex-shrink:0;margin-top:1px}

  /* Action buttons */
  .act-btn{display:flex;align-items:center;justify-content:center;gap:8px;font-family:'DM Sans';font-size:13px;font-weight:600;padding:11px 16px;border-radius:10px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:all .12s;width:100%;background:#fff;color:var(--ink)}
  .act-btn svg{flex-shrink:0}
  .act-primary{background:#2563EB;color:#fff;border-color:#2563EB}
  .act-primary:hover{background:#1D4ED8;border-color:#1D4ED8}
  .act-danger-outline{color:var(--red);border-color:#FCA5A5}
  .act-danger-outline:hover{background:var(--red-soft);border-color:var(--red)}
  .act-neutral{color:var(--ink);border-color:var(--line)}
  .act-neutral:hover{background:var(--soft);border-color:#C7C3BF}
  .card-body form{margin:0}

  /* Modal */
  .modal-backdrop{position:fixed;inset:0;background:rgba(27,26,36,.42);display:none;align-items:center;justify-content:center;z-index:100;padding:20px;backdrop-filter:blur(2px)}
  .modal-backdrop.show{display:flex}
  .modal-card{background:#fff;border-radius:16px;width:100%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,.2);display:flex;flex-direction:column;max-height:90vh;overflow:hidden}
  .modal-head{padding:24px 28px 10px}
  .modal-head h2{font-family:'Figtree';font-size:18px;font-weight:700;color:var(--ink);margin:0 0 4px}
  .modal-head p{font-size:12px;color:var(--muted);margin:0}
  .modal-body{padding:16px 28px;overflow-y:auto}
  .modal-foot{padding:16px 28px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:10px;background:#FCFBF8}

  .field-group{display:flex;flex-direction:column}
  .field-lbl{font-size:12px;font-weight:600;color:var(--ink);margin-bottom:8px;letter-spacing:.02em}
  .field-lbl .req{color:var(--red);margin-left:2px}
  .field-lbl .opt{color:var(--subtle);font-weight:400;margin-left:4px}

  .radio-row{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--line);border-radius:10px;margin-bottom:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--ink);transition:all .12s;position:relative}
  .radio-row:hover{border-color:#C7C3BF;background:#FDFCF9}
  .radio-row input{position:absolute;opacity:0;pointer-events:none}
  .radio-row .radio-dot{width:14px;height:14px;border:2px solid var(--line);border-radius:50%;flex-shrink:0;transition:all .12s}
  .radio-row:has(input:checked){border-color:#2563EB;background:#EEF2FF}
  .radio-row input:checked ~ .radio-dot{border-color:#2563EB;background:#2563EB;box-shadow:inset 0 0 0 3px #fff}

  .note-area{width:100%;font-family:'DM Sans';font-size:13px;padding:12px 14px;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;min-height:90px;resize:vertical;transition:border .15s}
  .note-area:focus{border-color:var(--yellow);box-shadow:0 0 0 3px rgba(255,198,26,.15)}

  .err-msg{color:var(--red);font-size:11px;font-weight:600;margin-top:4px}

  .modal-select, .modal-input{width:100%;font-family:'DM Sans';font-size:13px;padding:11px 14px;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s}
  .modal-select:focus, .modal-input:focus{border-color:var(--yellow);box-shadow:0 0 0 3px rgba(255,198,26,.15)}
  .modal-select{appearance:none;padding-right:38px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center}

  .check-row{display:flex;align-items:flex-start;gap:12px;padding:12px 0;cursor:pointer}
  .check-row + .check-row{border-top:1px dashed var(--line)}
  .check-row input[type=checkbox]{width:16px;height:16px;margin-top:1px;accent-color:#2563EB;flex-shrink:0;cursor:pointer}
  .check-row .check-title{font-size:13px;font-weight:600;color:var(--ink)}
  .check-row .check-desc{font-size:11px;color:var(--muted);margin-top:2px;line-height:1.5}

  /* History */
  .hist{list-style:none;padding:0;margin:0}
  .hist li{position:relative;padding:0 0 18px 24px;font-size:13px}
  .hist li:last-child{padding-bottom:0}
  .hist li::before{content:'';position:absolute;left:5px;top:14px;bottom:-4px;width:2px;background:var(--line)}
  .hist li:last-child::before{display:none}
  .hist-dot{position:absolute;left:0;top:4px;width:12px;height:12px;border-radius:50%;background:var(--ink);border:2px solid #fff;box-shadow:0 0 0 1px var(--line)}
  .hist-title{font-weight:600;color:var(--ink)}
  .hist-sub{color:var(--muted);font-size:12px;margin-top:1px}
  .hist-when{color:var(--subtle);font-size:11px;margin-top:3px}

  .act-danger-solid{background:var(--red);color:#fff;border-color:var(--red)}
  .act-danger-solid:hover{background:#A52E15;border-color:#A52E15}

  /* Attachments grid */
  .att-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(150px, 1fr));gap:12px}
  .att-card{position:relative;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff;display:flex;flex-direction:column;transition:all .12s}
  .att-card:hover{border-color:#C7C3BF;box-shadow:0 2px 8px rgba(0,0,0,.04)}
  .att-thumb{aspect-ratio:4/3;background:#FCFBF8;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;text-decoration:none}
  .att-thumb img{width:100%;height:100%;object-fit:cover;display:block}
  .att-pdf-ico{width:44px;height:52px;background:#FEE2E2;border:1px solid #FCA5A5;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#991B1B;letter-spacing:.03em}
  .att-meta{padding:8px 10px;font-size:11px;line-height:1.3}
  .att-name{display:block;color:var(--ink);font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-decoration:none}
  .att-name:hover{color:var(--amber)}
  .att-size{color:var(--muted);margin-top:2px}
  .att-rm{position:absolute;top:6px;right:6px;width:24px;height:24px;border-radius:50%;background:rgba(27,26,36,.72);border:0;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .12s}
  .att-rm:hover{background:var(--red)}
  .att-rm svg{width:12px;height:12px}
</style>

@php
  $statusPill = [
    'pending' => 'gray', 'quoted' => 'blue', 'approved' => 'green', 'assigned' => 'blue',
    'in_progress' => 'amber', 'pending_review' => 'orange', 'awaiting_invoice' => 'orange',
    'rework' => 'red', 'completed' => 'green', 'cancelled' => 'gray',
  ];
@endphp

<div class="breadcrumb">
  <a href="{{ route('service-requests.index') }}">Requests</a> / {{ $sr->sr_number }}
</div>

<div class="sr-head">
  <div>
    <div class="num">{{ $sr->sr_number }}</div>
    <h1>{{ $sr->title }}</h1>
    <div class="meta">
      Created {{ $sr->created_at->format('d M Y, H:i') }} by {{ $sr->createdBy->name ?? '—' }}
    </div>
  </div>
  <div class="actions">
    <a href="{{ route('service-requests.index') }}" class="btn btn-ghost">← Back</a>
  </div>
</div>

<div class="grid">
  {{-- ========== LEFT COLUMN ========== --}}
  <div>

    {{-- OVERVIEW --}}
    <div class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
        </svg>
        <h3>Overview</h3>
        <div class="right">
          <span class="pill pill-{{ $statusPill[$sr->status] ?? 'gray' }}">
            <span class="pill-dot"></span>{{ $sr->statusLabel() }}
          </span>
        </div>
      </div>
      <div class="card-body">
        <dl class="kv">
          <dt>Client</dt><dd>{{ $sr->client->name ?? '—' }}</dd>
          <dt>Site</dt><dd>
            {{ $sr->site->name ?? '—' }}
            @if ($sr->site?->city)<span style="color:var(--muted);font-weight:400">· {{ $sr->site->city }}</span>@endif
          </dd>
          <dt>Category</dt><dd>
            {{ $sr->category->name ?? '—' }}
            @if ($sr->category?->code)<span style="color:var(--muted);font-size:11px;font-weight:400">· {{ $sr->category->code }}</span>@endif
            @if ($sr->category?->service_domain)<div style="font-size:11px;color:var(--muted);margin-top:2px;font-weight:400">Domain: {{ $sr->category->service_domain }}</div>@endif
          </dd>
          <dt>Priority</dt><dd><span class="pill pill-{{ $sr->priorityColor() }}"><span class="pill-dot"></span>{{ $sr->priorityLabel() }}</span></dd>
          <dt>Service mode</dt><dd>{{ ucwords(str_replace('_', ' ', $sr->service_mode)) }}</dd>
          <dt>Coverage</dt><dd>{{ ucwords(str_replace('_', ' ', $sr->coverage_type)) }}</dd>
          <dt>Source</dt><dd>{{ ucwords(str_replace('_', ' ', $sr->source)) }}</dd>
          @if ($sr->client_po_reference)
            <dt>PO reference</dt><dd style="font-family:'DM Sans';letter-spacing:.03em">{{ $sr->client_po_reference }}</dd>
          @endif
        </dl>
      </div>
    </div>

    {{-- DESCRIPTION --}}
    <div class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/>
        </svg>
        <h3>Description</h3>
      </div>
      <div class="card-body">
        <div class="desc-box {{ $sr->description ? '' : 'empty' }}">{{ $sr->description ?? 'No description provided.' }}</div>
      </div>
    </div>

    {{-- ATTACHMENTS --}}
    @php $atts = $sr->attachments(); @endphp
    @if (!empty($atts))
      <div class="card">
        <div class="card-head">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
          </svg>
          <h3>Attachments</h3>
          <div class="right" style="font-size:12px;color:var(--muted);font-weight:500">
            {{ count($atts) }} {{ Str::plural('file', count($atts)) }}
          </div>
        </div>
        <div class="card-body">
          <div class="att-grid">
            @foreach ($atts as $att)
              <div class="att-card">
                <a href="{{ $att['url'] }}" target="_blank" class="att-thumb">
                  @if ($att['type'] === 'image')
                    <img src="{{ $att['url'] }}" alt="{{ $att['display'] }}">
                  @else
                    <div class="att-pdf-ico">{{ strtoupper($att['ext']) }}</div>
                  @endif
                </a>
                <div class="att-meta">
                  <a href="{{ $att['url'] }}" target="_blank" class="att-name" title="{{ $att['display'] }}">{{ $att['display'] }}</a>
                  <div class="att-size">
                    @php
                      $kb = $att['size'] / 1024;
                      echo $kb < 1024 ? number_format($kb, 1) . ' KB' : number_format($kb / 1024, 1) . ' MB';
                    @endphp
                  </div>
                </div>
                
                @if (!in_array($sr->status, ['completed', 'cancelled']))
                
                @endif
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endif

    {{-- HISTORY --}}
    <div class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <h3>History</h3>
      </div>
      <div class="card-body">
        <ol class="hist">
          @if ($sr->scheduled_eta_at && $sr->assigned_tech_user_id)
            <li>
              <div class="hist-dot"></div>
              <div class="hist-body">
                <div class="hist-title">Approved → Assigned</div>
                <div class="hist-sub">Assigned to {{ $sr->assignedTech->name ?? 'technician' }}</div>
                <div class="hist-when">{{ $sr->updated_at->format('d M, H:i') }}</div>
              </div>
            </li>
          @endif

          @if ($sr->approved_by_user_id)
            <li>
              <div class="hist-dot"></div>
              <div class="hist-body">
                <div class="hist-title">Pending → Approved</div>
                <div class="hist-sub">
                  {{ ucwords(str_replace('_', ' ', $sr->coverage_type)) }}@if ($sr->coverage_override_reason): {{ $sr->coverage_override_reason }}@endif
                </div>
                <div class="hist-when">{{ optional($sr->sla_clock_started_at)->format('d M, H:i') ?? '—' }}</div>
              </div>
            </li>
          @endif

          <li>
            <div class="hist-dot"></div>
            <div class="hist-body">
              <div class="hist-title">Request created</div>
              <div class="hist-sub">{{ $sr->createdBy->name ?? '—' }}</div>
              <div class="hist-when">{{ $sr->created_at->format('d M, H:i') }}</div>
            </div>
          </li>
        </ol>
      </div>
    </div>

    {{-- PROOF RULES --}}
    @if ($sr->proof_rules_snapshot)
      <div class="card">
        <div class="card-head">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          <h3>Proof rules required</h3>
        </div>
        <div class="card-body">
          <dl class="kv">
            <dt>Before photo</dt><dd>{!! !empty($sr->proof_rules_snapshot['require_before_photo']) ? '<span class="pill pill-amber"><span class="pill-dot"></span>Required</span>' : '<span class="pill pill-gray"><span class="pill-dot"></span>Not needed</span>' !!}</dd>
            <dt>After photo</dt><dd>{!! !empty($sr->proof_rules_snapshot['require_after_photo']) ? '<span class="pill pill-amber"><span class="pill-dot"></span>Required</span>' : '<span class="pill pill-gray"><span class="pill-dot"></span>Not needed</span>' !!}</dd>
            <dt>Signature</dt><dd>{!! !empty($sr->proof_rules_snapshot['require_signature']) ? '<span class="pill pill-amber"><span class="pill-dot"></span>Required</span>' : '<span class="pill pill-gray"><span class="pill-dot"></span>Not needed</span>' !!}</dd>
            <dt>Checklist</dt><dd>{!! !empty($sr->proof_rules_snapshot['require_checklist']) ? '<span class="pill pill-amber"><span class="pill-dot"></span>Required</span>' : '<span class="pill pill-gray"><span class="pill-dot"></span>Not needed</span>' !!}</dd>
            <dt>Invoice mandatory</dt><dd>{!! $sr->invoice_mandatory ? '<span class="pill pill-amber"><span class="pill-dot"></span>Yes</span>' : '<span class="pill pill-gray"><span class="pill-dot"></span>No</span>' !!}</dd>
          </dl>
          <div style="font-size:11px;color:var(--subtle);margin-top:14px;padding-top:12px;border-top:1px dashed var(--line)">
            Snapshot taken when this request was created ({{ \Carbon\Carbon::parse($sr->proof_rules_snapshot['snapshot_at'] ?? $sr->created_at)->format('d M Y, H:i') }}).
            Changes to the category after that don't affect this request.
          </div>
        </div>
      </div>
    @endif
  </div>

  {{-- ========== RIGHT COLUMN ========== --}}
  <div>

    {{-- ACTIONS --}}
    <div class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="3"/><circle cx="19" cy="12" r="3"/><circle cx="5" cy="12" r="3"/>
        </svg>
        <h3>Actions</h3>
      </div>
      <div class="card-body" style="padding:16px 20px;display:flex;flex-direction:column;gap:10px">

        @if ($sr->status === 'pending')
          <button type="button" class="act-btn act-primary" onclick="document.getElementById('approve-modal').classList.add('show')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Approve
          </button>

          <!-- <form method="POST" action="{{ route('service-requests.send-for-quote', $sr) }}" class="needs-confirm"
                data-confirm-title="Send for quote?"
                data-confirm-text="This request will move to the Quoted stage. The SLA clock pauses until the client approves the quote."
                data-confirm-ok="Yes, send" data-confirm-ok-color="amber">
            @csrf
            <button type="submit" class="act-btn act-neutral">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
              </svg>
              Send for quote
            </button>
          </form> -->

          <button type="button" class="act-btn act-danger-outline" onclick="document.getElementById('reject-modal').classList.add('show')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Reject
          </button>
        @endif

        @if (in_array($sr->status, ['approved', 'assigned']))
          <button type="button" class="act-btn {{ $sr->status === 'assigned' ? 'act-neutral' : 'act-primary' }}"
                  onclick="document.getElementById('assign-modal').classList.add('show')">
            @if ($sr->status === 'assigned')
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
              Reassign technician
            @else
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              Assign technician
            @endif
          </button>
        @endif

        @if ($sr->status === 'in_progress')
          <form method="POST" action="{{ route('service-requests.mark-review', $sr) }}" class="needs-confirm"
                data-confirm-title="Mark for review?" data-confirm-ok="Yes" data-confirm-ok-color="amber">
            @csrf
            <button type="submit" class="act-btn act-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 11.08 22 12 12 22 2 12 12 2 20.08 2.08"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              Submit for review
            </button>
          </form>
        @endif

        @if (!in_array($sr->status, ['completed', 'cancelled']))
          <button type="button" class="act-btn act-danger-outline"
                  onclick="document.getElementById('cancel-modal').classList.add('show')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Cancel request
          </button>
        @endif

        @if (!in_array($sr->status, ['completed', 'cancelled']))
          <button type="button" class="act-btn act-neutral"
                  onclick="document.getElementById('priority-modal').classList.add('show')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Change priority
          </button>
        @endif

        <a href="{{ route('service-requests.edit', $sr) }}" class="act-btn act-neutral">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Edit details
        </a>
      </div>
    </div>

    {{-- SLA CARD --}}
    <div class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <h3>SLA</h3>
        <div class="right">
          <span class="pill pill-{{ $sr->slaStateColor() }}"><span class="pill-dot"></span>{{ $sr->slaStateLabel() }}</span>
        </div>
      </div>

      @if (!$policy)
        <div class="warn-banner">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div>No SLA policy matches this category and priority. <a href="{{ route('admin.sla-policies.index') }}" class="link" style="color:var(--red);border-color:var(--red)">Add one</a> so response and resolution deadlines are tracked.</div>
        </div>
      @else
        <div class="sla-rows">
          <div class="sla-row">
            <span class="lbl">Policy</span>
            <span class="val">
              {{ $policy->category?->name ?? 'Tenant default' }}:
              respond {{ $policy->response_human ?? ($policy->response_minutes . ' min') }},
              resolve {{ $policy->resolution_human ?? ($policy->resolution_minutes . ' min') }}
            </span>
          </div>

          <div class="sla-row">
            <span class="lbl">Clock started</span>
            @if ($sr->sla_clock_started_at)
              <span class="val">{{ $sr->sla_clock_started_at->format('d M, H:i') }}<span class="sub">(at creation)</span></span>
            @else
              <span class="val" style="color:var(--subtle);font-weight:400">Not started yet</span>
            @endif
          </div>

          <div class="sla-row">
            <span class="lbl">Response due</span>
            @if ($sr->sla_response_due_at)
              <span class="val">
                {{ $sr->sla_response_due_at->format('d M, H:i') }}
                @if (!$sr->tech_accepted_at)
                  <span class="sub">in {{ $sr->sla_response_due_at->diffForHumans(now(), ['parts' => 2, 'short' => true]) }}</span>
                @else
                  <span class="sub" style="color:var(--green)">Accepted</span>
                @endif
              </span>
            @else
              <span class="val" style="color:var(--subtle);font-weight:400">—</span>
            @endif
          </div>

          <div class="sla-row">
            <span class="lbl">Resolution due</span>
            @if ($sr->sla_resolution_due_at)
              <span class="val">
                {{ $sr->sla_resolution_due_at->format('d M, H:i') }}
                @if (!$sr->completed_at)
                  <span class="sub">{{ $sr->sla_resolution_due_at->diffForHumans() }}</span>
                @else
                  <span class="sub" style="color:var(--green)">Done</span>
                @endif
              </span>
            @else
              <span class="val" style="color:var(--subtle);font-weight:400">—</span>
            @endif
          </div>

          <div class="sla-row">
            <span class="lbl">Review time</span>
            <span class="val">
              {{ $sr->total_work_minutes ?? 0 }} min
              @if ($sr->total_hold_minutes > 0)<span class="sub">+ {{ $sr->total_hold_minutes }} min on hold</span>@endif
            </span>
          </div>

          @if ($policy->uses_business_hours ?? false)
            <div class="sla-row">
              <span class="lbl">Clock type</span>
              <span class="val"><span class="pill pill-green" style="font-size:10px;font-weight:600">Business hours only</span></span>
            </div>
          @endif
        </div>
      @endif
    </div>

    {{-- BILLING --}}
    <div class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
        </svg>
        <h3>Billing</h3>
      </div>
      <div class="card-body">
        <dl class="kv">
          <dt>Status</dt><dd>{{ ucwords(str_replace('_', ' ', $sr->billing_status)) }}</dd>
          <dt>Coverage</dt><dd>{{ ucwords(str_replace('_', ' ', $sr->coverage_type)) }}</dd>
          @if ($sr->coverage_override_reason)
            <dt>Reason</dt><dd>{{ $sr->coverage_override_reason }}</dd>
          @endif
          @if ($sr->client_po_reference)
            <dt>PO ref</dt><dd style="font-family:'DM Sans';letter-spacing:.03em">{{ $sr->client_po_reference }}</dd>
          @endif
        </dl>
      </div>
    </div>

    {{-- PEOPLE & SCHEDULE --}}
    <div class="card">
      <div class="card-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        <h3>People and schedule</h3>
      </div>
      <div class="card-body">
        <dl class="kv">
          <dt>Team lead</dt><dd class="{{ $sr->teamLead ? '' : 'empty' }}">{{ $sr->teamLead->name ?? 'Not set' }}</dd>
          <dt>Technician</dt><dd class="{{ $sr->assignedTech ? '' : 'empty' }}">{{ $sr->assignedTech->name ?? 'Not assigned' }}</dd>
          <dt>Arrival time</dt><dd class="{{ $sr->scheduled_eta_at ? '' : 'empty' }}">{{ $sr->scheduled_eta_at ? $sr->scheduled_eta_at->format('d M, H:i') : 'Not set' }}</dd>
          <dt>Job accepted</dt><dd class="{{ $sr->tech_accepted_at ? '' : 'empty' }}">{{ $sr->tech_accepted_at ? $sr->tech_accepted_at->format('d M, H:i') : 'Not yet' }}</dd>
          <dt>Geofence enforced</dt><dd>{{ $sr->enforce_geofence ? 'Yes' : 'No' }}</dd>
          <dt>Signature waiver</dt><dd>{{ $sr->allow_signature_waiver ? 'Allowed' : 'Not allowed' }}</dd>
        </dl>
      </div>
    </div>
  </div>
</div>


{{-- ============== APPROVE MODAL ============== --}}
<div class="modal-backdrop" id="approve-modal">
  <form method="POST" action="{{ route('service-requests.approve', $sr) }}" class="modal-card" id="approve-form">
    @csrf
    <div class="modal-head">
      <h2>Approve request {{ $sr->sr_number }}</h2>
      <p>Choose the coverage. In this release the manager picks it by hand.</p>
    </div>
    <div class="modal-body">
      <div class="field-group">
        <label class="field-lbl">Coverage <span class="req">*</span></label>
        @foreach ([
            'in_contract' => 'In contract', 'in_warranty' => 'In warranty',
            'goodwill' => 'Goodwill', 'needs_quote' => 'Needs a quote',
          ] as $value => $label)
          <label class="radio-row">
            <input type="radio" name="coverage" value="{{ $value }}" required>
            <span class="radio-dot"></span><span>{{ $label }}</span>
          </label>
        @endforeach
        <small class="err-msg" id="coverage-err" style="display:none">Choose a coverage type.</small>
      </div>
      <div class="field-group" style="margin-top:16px">
        <label for="note" class="field-lbl">Note <span class="opt">(optional)</span></label>
        <textarea name="note" id="note" class="note-area" maxlength="500" placeholder="e.g. Called client first — confirmed warranty valid"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="act-btn act-neutral" style="width:auto" onclick="document.getElementById('approve-modal').classList.remove('show')">Close</button>
      <button type="submit" class="act-btn act-primary" style="width:auto">Approve</button>
    </div>
  </form>
</div>


{{-- ============== CANCEL REQUEST MODAL ============== --}}
<div class="modal-backdrop" id="cancel-modal">
  <form method="POST" action="{{ route('service-requests.cancel', $sr) }}" class="modal-card" id="cancel-form" style="max-width:500px">
    @csrf
    <div class="modal-head">
      <h2>Cancel request {{ $sr->sr_number }}</h2>
      <p>This cannot be undone.</p>
    </div>

    <div class="modal-body">
      <div class="field-group">
        <label for="cancel_reason" class="field-lbl">Reason <span class="req">*</span></label>
        <textarea name="reason" id="cancel_reason" class="note-area" maxlength="500"
                  required autofocus placeholder="e.g. Client postponed to next quarter"></textarea>
        <small class="err-msg" id="cancel-err" style="display:none">Please enter a reason.</small>
      </div>
    </div>

    <div class="modal-foot">
      <button type="button" class="act-btn act-neutral" style="width:auto"
              onclick="document.getElementById('cancel-modal').classList.remove('show')">
        Close
      </button>
      <button type="submit" class="act-btn act-danger-solid" style="width:auto">
        Cancel request
      </button>
    </div>
  </form>
</div>


{{-- ============== REJECT REQUEST MODAL ============== --}}
<div class="modal-backdrop" id="reject-modal">
  <form method="POST" action="{{ route('service-requests.reject', $sr) }}" class="modal-card" id="reject-form" style="max-width:500px">
    @csrf
    <div class="modal-head">
      <h2>Reject request {{ $sr->sr_number }}</h2>
      <p>The request will be cancelled and the client told.</p>
    </div>

    <div class="modal-body">
      <div class="field-group">
        <label for="reject_reason" class="field-lbl">Reason <span class="req">*</span></label>
        <textarea name="reason" id="reject_reason" class="note-area" maxlength="500"
                  required autofocus placeholder="e.g. Not covered under current contract — needs separate PO"></textarea>
        <small class="err-msg" id="reject-err" style="display:none">Please enter a reason.</small>
      </div>
    </div>

    <div class="modal-foot">
      <button type="button" class="act-btn act-neutral" style="width:auto"
              onclick="document.getElementById('reject-modal').classList.remove('show')">
        Close
      </button>
      <button type="submit" class="act-btn act-danger-outline" style="width:auto">
        Reject
      </button>
    </div>
  </form>
</div>


{{-- ============== CHANGE PRIORITY MODAL ============== --}}
<div class="modal-backdrop" id="priority-modal">
  <form method="POST" action="{{ route('service-requests.change-priority', $sr) }}" class="modal-card" id="priority-form" style="max-width:500px">
    @csrf
    <div class="modal-head">
      <h2>Change priority</h2>
      <p>Raising a request to Emergency starts its SLA clock if it has not started.</p>
    </div>

    <div class="modal-body">
      <div class="field-group">
        @foreach (\App\Models\ServiceRequest::PRIORITIES as $value => $label)
          <label class="radio-row">
            <input type="radio" name="priority" value="{{ $value }}"
                   {{ $sr->priority === $value ? 'checked' : '' }} required>
            <span class="radio-dot"></span>
            <span>{{ $label }}</span>
          </label>
        @endforeach
      </div>
    </div>

    <div class="modal-foot">
      <button type="button" class="act-btn act-neutral" style="width:auto"
              onclick="document.getElementById('priority-modal').classList.remove('show')">
        Close
      </button>
      <button type="submit" class="act-btn act-primary" style="width:auto">
        Save
      </button>
    </div>
  </form>
</div>


{{-- ============== ASSIGN TECHNICIAN MODAL ============== --}}
<div class="modal-backdrop" id="assign-modal">
  <form method="POST" action="{{ route('service-requests.assign', $sr) }}" class="modal-card" id="assign-form">
    @csrf
    <div class="modal-head">
      <h2>{{ $sr->status === 'assigned' ? 'Reassign' : 'Assign' }} request {{ $sr->sr_number }}</h2>
      <p>{{ $sr->site->name ?? '—' }}@if ($sr->site?->city), {{ $sr->site->city }}@endif</p>
    </div>
    <div class="modal-body">
      <div class="field-group">
        <label for="technician_id" class="field-lbl">Technician <span class="req">*</span></label>
        <select name="technician_id" id="technician_id" class="modal-select" required>
          <option value="">Choose a technician</option>
          @foreach (($technicians ?? collect()) as $t)
            <option value="{{ $t->id }}" @selected((int) $sr->assigned_tech_user_id === $t->id)>{{ $t->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field-group" style="margin-top:16px">
        <label for="arrival_time" class="field-lbl">Arrival time <span class="req">*</span></label>
        <input type="datetime-local" name="arrival_time" id="arrival_time" class="modal-input" required
               value="{{ optional($sr->scheduled_eta_at)->format('Y-m-d\TH:i') ?: now()->addDay()->setTime(9,0)->format('Y-m-d\TH:i') }}">
      </div>
      <div style="margin-top:16px">
        <label class="check-row">
          <input type="checkbox" name="enforce_geofence" value="1" {{ $sr->enforce_geofence ? 'checked' : '' }}>
          <div>
            <div class="check-title">Enforce geofence</div>
            <div class="check-desc">The technician cannot punch in outside the site radius.</div>
          </div>
        </label>
        <label class="check-row">
          <input type="checkbox" name="allow_signature_waiver" value="1" {{ $sr->allow_signature_waiver ? 'checked' : '' }}>
          <div>
            <div class="check-title">Allow signature waiver</div>
            <div class="check-desc">The technician may use a code sent to a project contact instead of a signature.</div>
          </div>
        </label>
      </div>
      <div class="field-group" style="margin-top:16px">
        <label for="tech_note" class="field-lbl">Note to technician <span class="opt">(optional)</span></label>
        <textarea name="note" id="tech_note" class="note-area" maxlength="500" placeholder="e.g. Ask for Lena at the back entrance"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="act-btn act-neutral" style="width:auto" onclick="document.getElementById('assign-modal').classList.remove('show')">Close</button>
      <button type="submit" class="act-btn act-primary" style="width:auto">Assign</button>
    </div>
  </form>
</div>

<script>
(function () {
  // Approve modal
  const approveModal = document.getElementById('approve-modal');
  const approveForm  = document.getElementById('approve-form');
  const covErr       = document.getElementById('coverage-err');
  if (approveModal) {
    approveModal.addEventListener('click', e => { if (e.target === approveModal) approveModal.classList.remove('show'); });
    approveForm.addEventListener('submit', e => {
      if (!approveForm.querySelector('input[name="coverage"]:checked')) {
        e.preventDefault(); covErr.style.display = 'block';
      }
    });
    approveForm.querySelectorAll('input[name="coverage"]').forEach(r => r.addEventListener('change', () => covErr.style.display = 'none'));
  }

  // Assign modal
  const assignModal = document.getElementById('assign-modal');
  if (assignModal) {
    assignModal.addEventListener('click', e => { if (e.target === assignModal) assignModal.classList.remove('show'); });
  }

  // Cancel modal
  const cancelModal = document.getElementById('cancel-modal');
  const cancelForm  = document.getElementById('cancel-form');
  const cancelErr   = document.getElementById('cancel-err');
  const cancelTxt   = document.getElementById('cancel_reason');
  if (cancelModal) {
    cancelModal.addEventListener('click', e => { if (e.target === cancelModal) cancelModal.classList.remove('show'); });
    cancelForm.addEventListener('submit', e => {
      if (!cancelTxt.value.trim()) {
        e.preventDefault();
        cancelErr.style.display = 'block';
        cancelTxt.focus();
      }
    });
    cancelTxt.addEventListener('input', () => { cancelErr.style.display = 'none'; });
  }

  // Reject modal
  const rejectModal = document.getElementById('reject-modal');
  const rejectForm  = document.getElementById('reject-form');
  const rejectErr   = document.getElementById('reject-err');
  const rejectTxt   = document.getElementById('reject_reason');
  if (rejectModal) {
    rejectModal.addEventListener('click', e => { if (e.target === rejectModal) rejectModal.classList.remove('show'); });
    rejectForm.addEventListener('submit', e => {
      if (!rejectTxt.value.trim()) {
        e.preventDefault();
        rejectErr.style.display = 'block';
        rejectTxt.focus();
      }
    });
    rejectTxt.addEventListener('input', () => { rejectErr.style.display = 'none'; });
  }

  // Priority modal
  const priorityModal = document.getElementById('priority-modal');
  if (priorityModal) {
    priorityModal.addEventListener('click', e => { if (e.target === priorityModal) priorityModal.classList.remove('show'); });
  }

  // Esc closes any open modal
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop.show').forEach(m => m.classList.remove('show'));
    }
  });
})();
</script>
@endsection