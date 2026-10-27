@extends('layouts.technician')
@section('title', $job->sr_number ?? 'Job')

@section('appbar')
<div class="appbar">
  <button type="button" class="back" onclick="history.back()">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  </button>
  <h1>{{ $job->sr_number ?? 'Job' }}</h1>
  <span class="sent-chip">All sent</span>
</div>
@endsection

@section('content')
<style>
  .sr-head{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px;margin-bottom:12px}
  .sr-id{font-size:12.5px;color:var(--muted);font-weight:600}
  .sr-title{font-family:'Figtree';font-size:19px;font-weight:800;line-height:1.3;margin:4px 0 2px}
  .sr-client{color:var(--muted);font-size:14px}
  .sr-chips{display:flex;gap:6px;margin:10px 0 10px;flex-wrap:wrap}
  .sr-meta{font-size:13.5px;color:var(--ink);line-height:1.5}

  .sec-label{font-size:11px;font-weight:800;color:var(--subtle);letter-spacing:.14em;margin:16px 2px 8px;text-transform:uppercase}

  .where-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px}
  .where-name{font-family:'Figtree';font-size:16px;font-weight:800}
  .where-addr{color:var(--muted);font-size:13.5px;margin-top:3px}
  .maps-btn{display:inline-flex;align-items:center;gap:7px;margin-top:10px;padding:9px 14px;border:1px solid var(--accent);color:var(--accent);border-radius:999px;font-size:13.5px;font-weight:700;text-decoration:none}

  .who-row{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;margin-bottom:8px;display:flex;align-items:center;gap:12px}
  .who-row:last-child{margin-bottom:0}
  .who-name{font-family:'Figtree';font-size:15px;font-weight:800}
  .who-sub{font-size:12.5px;color:var(--muted);margin-top:1px}
  .who-btn{margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid var(--line);color:var(--accent);border-radius:999px;font-size:13px;font-weight:700;text-decoration:none}

  .access-card{background:var(--amber-soft);border:1px solid #F0D4A3;border-radius:14px;padding:14px 16px;display:flex;gap:10px;align-items:flex-start}
  .access-card svg{color:var(--amber);flex-shrink:0;margin-top:1px}
  .access-card .txt{font-size:13.5px;color:var(--amber);line-height:1.45}

  .job-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px}
  .job-text{font-size:14.5px;color:var(--ink);line-height:1.5;margin-bottom:8px}

  .proof-list{background:#fff;border:1px solid var(--line);border-radius:14px;padding:4px 0;overflow:hidden}
  .proof-item{display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #F2F1F6;font-size:14.5px}
  .proof-item:last-child{border-bottom:0}
  .proof-dot{width:22px;height:22px;border-radius:50%;background:var(--soft);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:transparent}
  .proof-done .proof-dot{background:var(--green);color:#fff}
  .proof-dot svg{width:12px;height:12px}

  .list-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;color:var(--muted);font-size:14px}
  .hist-list .hist{padding:12px 16px;font-size:13.5px;color:var(--ink);border-bottom:1px solid #F2F1F6}
  .hist-list .hist:last-child{border-bottom:0}

  .tiles{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
  .tile{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;text-decoration:none;color:var(--ink);display:block}
  .tile svg{width:22px;height:22px;color:var(--ink);margin-bottom:8px}
  .tile .t{font-family:'Figtree';font-size:16px;font-weight:800}
  .tile .s{font-size:12.5px;color:var(--muted);margin-top:2px}

  .action-bar{position:sticky;bottom:0;background:var(--bg);border-top:1px solid var(--line);padding:12px 16px;display:flex;gap:8px;margin:16px -16px -20px}
  .action-bar .btn{flex:1;padding:14px 10px}
  .action-bar .btn-dark{background:var(--ink);color:#fff;flex:2}
  .action-bar .btn-light{background:#fff;color:var(--ink);border-color:var(--line);flex:1}

  .inprog-chip{background:var(--green-soft);color:var(--green);padding:5px 12px;border-radius:999px;font-size:12.5px;font-weight:700}

  
.action-bar.is-on-hold {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
}

.hold-footer {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
}

.hold-status {
    box-sizing: border-box;
    width: 100%;
    padding: 14px 16px;
    border: 1px solid #F0C58D;
    border-radius: 18px;
    background: #FFF3E4;
    color: #333;
    font-size: 14px;
    line-height: 1.5;
}

.hold-status strong {
    display: block;
    color: #111;
    font-size: 15px;
}

.resume-btn {
    width: 100%;
    min-height: 64px;
    border-radius: 18px;
    font-size: 17px;
    font-weight: 800;
}



</style>

@php
  $isAssigned = $job->status === 'assigned';
  $isActive   = $job->status === 'in_progress';
  $isReview   = $job->status === 'pending_review';
  $isDone     = in_array($job->status, ['completed','cancelled']);
  $accepted   = ! empty($job->tech_accepted_at);
  $pri        = strtolower($job->priority ?? 'medium');
  $priClass   = match($pri) { 'emergency','critical' => 'chip-emerg', 'high' => 'chip-high', 'medium' => 'chip-med', default => 'chip-low' };
  $cat        = $job->category?->name;
  $rules      = $job->proof_rules_snapshot ?? [];

 $isOnHold = (bool) $job->is_on_hold;
@endphp

{{-- ===== HEAD ===== --}}
<div class="sr-head">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:4px">
    <span class="sr-id">{{ $job->sr_number ?? ('SR-'.$job->id) }}</span>
    <span class="chip {{ $priClass }}">{{ ucfirst($pri) }}</span>
  </div>
  <div class="sr-title">{{ $job->title ?? 'Service request' }}</div>
  <div class="sr-client">{{ $job->client?->name }}</div>

  <div class="sr-chips">
    @if ($isActive)
      <span class="inprog-chip">In progress</span>
    @elseif ($isReview)
      <span class="chip chip-med">Pending review</span>
    @elseif ($isAssigned)
      <span class="chip chip-assigned">Assigned</span>
      @if (! $accepted)<span class="chip chip-notaccepted">Not accepted</span>@endif
    @elseif ($isDone)
      <span class="chip chip-low">{{ ucfirst($job->status) }}</span>
    @endif
  </div>

  <div class="sr-meta">
    @if ($job->scheduled_eta_at)
      Arrival {{ \Carbon\Carbon::parse($job->scheduled_eta_at)->format('H:i') }}.
    @endif
    @if ($isActive && $job->sla_resolution_due_at)
      Finish by {{ \Carbon\Carbon::parse($job->sla_resolution_due_at)->format('H:i') }}.
    @elseif ($job->sla_response_due_at)
      Respond by {{ \Carbon\Carbon::parse($job->sla_response_due_at)->format('H:i') }}.
    @endif
  </div>
</div>

{{-- ===== TILES (only shown in-progress) ===== --}}
@if ($isActive)
  <div class="tiles">
 <a href="{{ route('technician.jobs.photos', $job) }}" class="tile">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
  <div class="t">Photos</div>
  <div class="s"><span id="c-before">{{ $counts['before'] }}</span> before, <span id="c-after">{{ $counts['after'] }}</span> after</div>
</a>

<a href="{{ route('technician.jobs.notes', $job) }}" class="tile">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
  <div class="t">Notes</div>
  <div class="s"><span id="c-notes">{{ $counts['notes'] }}</span> on this job</div>
</a>

<a href="{{ route('technician.jobs.expenses', $job) }}" class="tile">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
  <div class="t">Expenses</div>
  <div class="s"><span id="c-exp">{{ $counts['expenses'] }}</span> logged</div>
</a>

<a href="{{ route('technician.jobs.signature', $job) }}" class="tile">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
  <div class="t">Signature</div>
  <div class="s" id="c-sig">{{ $job->signed_at ? 'Captured' : 'Not yet' }}</div>
</a>
</div>
@endif

{{-- ===== WHERE ===== --}}
<div class="sec-label">Where</div>
<div class="where-card">
  <div class="where-name">{{ $job->site?->name ?? '—' }}</div>

  @if ($job->site?->address)
    <div class="where-addr">{{ $job->site->address }}</div>
  @endif

  @if ($job->site?->city)
    <div class="where-addr">{{ $job->site->city }}</div>
  @endif

  @if ($job->site?->lat && $job->site?->lng)
    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $job->site->lat }},{{ $job->site->lng }}" target="_blank" class="maps-btn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
      Open in Maps
    </a>
  @elseif ($job->site?->address || $job->site?->city)
    @php
      $q = urlencode(trim(($job->site?->address ?? '').' '.($job->site?->city ?? '').' '.($job->site?->name ?? '')));
    @endphp
    <a href="https://www.google.com/maps/search/?api=1&query={{ $q }}" target="_blank" class="maps-btn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
      Open in Maps
    </a>
  @endif
</div>

{{-- ===== WHO ===== --}}
{{-- ===== WHO ===== --}}
@php
  $clientContacts = $job->client?->contacts ?? collect();
  $hasAnyWho = $job->site?->contact_name || $clientContacts->count();
@endphp

@if ($hasAnyWho)
<div class="sec-label">Who</div>
<div>

  {{-- Site contact (if set on the site record) --}}
  @if ($job->site?->contact_name)
    <div class="who-row">
      <div style="flex:1;min-width:0">
        <div class="who-name">{{ $job->site->contact_name }}</div>
        <div class="who-sub">Site contact @if ($job->site->contact_phone) · {{ $job->site->contact_phone }} @endif</div>
      </div>
      @if ($job->site->contact_phone)
        <a href="tel:{{ $job->site->contact_phone }}" class="who-btn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Call
        </a>
      @endif
    </div>
  @endif

  {{-- Client contacts (loop) --}}
  @foreach ($clientContacts as $contact)
    <div class="who-row">
      <div style="flex:1;min-width:0">
        <div class="who-name">
          {{ $contact->name }}
          @if ($contact->is_primary)
            <span class="chip chip-med" style="font-size:10px;padding:2px 8px;margin-left:6px">Primary</span>
          @endif
        </div>
        <div class="who-sub">
          Client contact
          @if ($contact->phone) · {{ $contact->phone }}
          @elseif ($contact->email) · {{ $contact->email }}
          @endif
        </div>
      </div>

      @if ($contact->phone)
        <a href="tel:{{ $contact->phone }}" class="who-btn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Call
        </a>
      @elseif ($contact->email)
        <a href="mailto:{{ $contact->email }}" class="who-btn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          Email
        </a>
      @endif

      @if ($contact->whatsapp_number)
        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $contact->whatsapp_number) }}" target="_blank" class="who-btn" style="margin-left:0;color:#1D7A3A;border-color:#C8EBD9">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.33 4.97L2 22l5.26-1.38a9.9 9.9 0 0 0 4.78 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.08h-.01a8.22 8.22 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.26 8.26 0 1 1 15.3-4.31c0 4.55-3.7 8.17-8.31 8.17zm4.52-6.16c-.25-.12-1.47-.73-1.69-.81-.23-.08-.39-.12-.56.12-.16.25-.64.81-.78.98-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.24-.74-.66-1.23-1.47-1.37-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.41-.56-.42h-.47c-.17 0-.43.06-.65.31-.23.25-.86.84-.86 2.05 0 1.21.88 2.37 1 2.54.12.17 1.74 2.66 4.21 3.73.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.17-.48-.29z"/></svg>
          WA
        </a>
      @endif
    </div>
  @endforeach
</div>
@endif

{{-- ===== ACCESS ===== --}}
@if ($job->site?->access_instructions)
<div class="sec-label">Access</div>
<div class="access-card">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
  <div class="txt">{{ $job->site->access_instructions }}</div>
</div>
@endif

{{-- ===== THE JOB ===== --}}
<div class="sec-label">The job</div>
<div class="job-card">
  @if ($job->description)
    <div class="job-text">{{ $job->description }}</div>
  @endif
  @if ($cat)<span class="chip chip-low">{{ $cat }}</span>@endif
</div>

{{-- ===== PROOF NEEDED ===== --}}
<div class="sec-label">Proof needed</div>
<div class="proof-list" id="proof-list">
  @foreach ([
    'before_photo' => 'Before photo',
    'after_photo'  => 'After photo',
    'signature'    => 'Signature or code',
  ] as $k => $label)
    @php $done = $proofDone[$k] ?? false; @endphp
    <div class="proof-item {{ $done ? 'proof-done' : '' }}" id="proof-{{ $k }}">
      <div class="proof-dot">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      {{ $label }}
    </div>
  @endforeach
</div>

{{-- ===== NOTES ===== --}}
<div class="sec-label">Notes</div>
<div class="list-card">No notes yet.</div>

{{-- ===== HISTORY ===== --}}
<div class="sec-label">History</div>
<div class="list-card hist-list" style="padding:0">
  @if ($job->first_punch_in_at)
    <div class="hist">Punched in by {{ auth()->user()->name }}, {{ \Carbon\Carbon::parse($job->first_punch_in_at)->format('H:i') }}</div>
  @endif
  @if ($job->tech_accepted_at)
    <div class="hist">Accepted by {{ auth()->user()->name }}, {{ \Carbon\Carbon::parse($job->tech_accepted_at)->format('H:i') }}</div>
  @endif
  <div class="hist">Assigned to {{ auth()->user()->name }}{{ $job->created_at ? ', ' . $job->created_at->diffForHumans() : '' }}</div>
</div>

{{-- ===== YOUR WORK (in-progress only) ===== --}}
@if ($isActive)
  <div class="sec-label">Your work</div>
  <div class="list-card">Nothing yet.</div>
@endif

{{-- ===== STICKY ACTION BAR ===== --}}


<div class="action-bar {{ $isOnHold ? 'is-on-hold' : '' }}">
  @if ($isDone)
    <div class="btn btn-ghost" style="flex:1;cursor:default;opacity:.7">
      {{ ucfirst($job->status) }}
    </div>

  @elseif ($isReview)
    <div class="btn btn-ghost" style="flex:1;cursor:default;background:var(--green-soft);color:var(--green);border-color:transparent">
      ✓ Submitted for review
    </div>


@elseif ($isOnHold)
    <div class="hold-footer">
    <div class="hold-status">
    <div>
        On hold:
        {{ \App\Models\HoldReason::find($job->current_hold_reason_id)?->label
            ?? 'Job paused' }}
    </div>

    <strong
        id="hold-duration"
        data-held-at="{{ $job->hold_started_at
            ? \Carbon\Carbon::parse($job->hold_started_at)->toIso8601String()
            : '' }}">
        Waiting for hold start time
    </strong>
</div>

        <form method="POST"
              action="{{ route('technician.jobs.resume', $job) }}"
              style="margin:0;width:100%">
            @csrf
            <button type="submit" class="btn btn-primary resume-btn">
                ▶ &nbsp; Resume
            </button>
        </form>
    </div>


  @elseif ($isActive)
    <button type="button" class="btn btn-light"
            onclick="openSheet('sheet-hold')">
      <svg width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2.5"
           stroke-linecap="round" stroke-linejoin="round">
        <rect x="6" y="4" width="4" height="16"/>
        <rect x="14" y="4" width="4" height="16"/>
      </svg>
      Hold
    </button>

       <a href="{{ route('technician.jobs.punch-out-review', $job) }}"
       class="btn btn-dark"
       style="flex:2;text-align:center;text-decoration:none;display:inline-flex;align-items:center;justify-content:center">
      Punch out
    </a>

   @elseif ($isAssigned && ! $accepted)
    {{-- Not accepted yet --}}
    <button type="button" class="btn btn-light" onclick="openSheet('sheet-time')">
      Change time
    </button>
    <button type="button" class="btn btn-primary" onclick="openSheet('sheet-accept')" style="flex:2">
      Accept job
    </button>

  @else
    {{-- Accepted — ready to punch in --}}
    <button type="button" class="btn btn-light" onclick="openSheet('sheet-time')">
      Change time
    </button>
    <form method="POST"
          action="{{ route('technician.jobs.punch-in', $job) }}"
          style="flex:2;margin:0">
      @csrf
      <button type="submit" class="btn btn-primary" style="width:100%">
        Punch in
      </button>
    </form>
  @endif
</div>



{{-- ===== BOTTOM SHEETS ===== --}}
<script>
  function openSheet(id){ document.getElementById(id).classList.add('open'); document.getElementById('sheet-bd').classList.add('open'); }
  function closeSheets(){ document.querySelectorAll('.sheet').forEach(s=>s.classList.remove('open')); document.getElementById('sheet-bd').classList.remove('open'); }
</script>

<script>
(function () {
  const url = @json(route('technician.jobs.counts', $job));
  const el = id => document.getElementById(id);
  async function tick() {
    try {
      const r = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      if (! r.ok) return;
      const d = await r.json();
      if (el('c-before')) el('c-before').textContent = d.before;
      if (el('c-after'))  el('c-after').textContent  = d.after;
      if (el('c-notes'))  el('c-notes').textContent  = d.notes;
      if (el('c-exp'))    el('c-exp').textContent    = d.expenses;
      if (el('c-sig'))    el('c-sig').textContent    = d.signature ? 'Captured' : 'Not yet';
    } catch (e) { /* silent */ }
  }
  let timer = setInterval(() => { if (! document.hidden) tick(); }, 3000);
  document.addEventListener('visibilitychange', () => { if (! document.hidden) tick(); });
})();


</script>
<script>
(function () {
    const timer = document.getElementById('hold-duration');

    if (!timer) return;

    const heldAt = timer.dataset.heldAt;

    if (!heldAt) {
        timer.textContent = 'Waiting for hold start time';
        return;
    }

    const startTime = new Date(heldAt).getTime();

    if (Number.isNaN(startTime)) {
        timer.textContent = 'Invalid hold start time';
        return;
    }

    function updateHoldTimer() {
        const elapsed = Math.max(
            0,
            Math.floor((Date.now() - startTime) / 1000)
        );

        const hours = Math.floor(elapsed / 3600);
        const minutes = Math.floor((elapsed % 3600) / 60);
        const seconds = elapsed % 60;

        timer.textContent = hours > 0
            ? `${hours} hr ${minutes} min ${seconds} s`
            : `${minutes} min ${seconds} s`;
    }

    updateHoldTimer();
    setInterval(updateHoldTimer, 1000);
})();
</script>
@endsection

{{-- ===== OVERLAYS (sheets rendered inside .app, not .main) ===== --}}
@section('overlays')
  @include('technician.jobs._sheet_time',   ['job' => $job])
  @include('technician.jobs._sheet_hold',   ['job' => $job])
  @include('technician.jobs._sheet_accept', ['job' => $job])
@endsection