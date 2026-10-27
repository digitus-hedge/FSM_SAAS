@extends('layouts.technician')
@section('title', 'Punch out')

@section('appbar')
<div class="appbar">
  <button type="button" class="back" onclick="history.back()">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
  </button>
  <h1>Punch out</h1>
  <span class="sent-chip">All sent</span>
</div>
@endsection

@section('content')
<style>
  .po-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:4px 0;overflow:hidden;margin-bottom:14px}
  .po-head{font-family:'Figtree';font-size:16px;font-weight:800;color:var(--ink);padding:16px 16px 10px}

  .po-row{display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #F2F1F6;font-size:14.5px;color:var(--ink)}
  .po-row:last-child{border-bottom:0}

  .po-dot{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .po-dot svg{width:14px;height:14px}
  .po-dot.pending{background:#FDE3E3;color:#D22E2E}
  .po-dot.done{background:#D9F2E3;color:#1D7A3A}

  .po-label{flex:1;min-width:0}
  .po-open{padding:8px 16px;border:1px solid var(--line);border-radius:999px;font-size:13px;font-weight:700;color:var(--ink);text-decoration:none;background:#fff;flex-shrink:0}
  .po-open:hover{background:#F8F7FB}

  .po-foot{font-size:13.5px;color:var(--muted);padding:0 2px;line-height:1.5}

  .po-actions{position:sticky;bottom:0;background:var(--bg);border-top:1px solid var(--line);padding:12px 16px;margin:16px -16px -20px}
  .po-actions .btn{width:100%;padding:16px;font-size:16px;font-weight:800;border-radius:14px}
  .po-actions .btn-ready{background:var(--ink);color:#fff;border:0;cursor:pointer}
  .po-actions .btn-disabled{background:#B4B4B4;color:#fff;border:0;cursor:not-allowed}

  .po-alert{background:#FDECEC;border:1px solid #F2C2C2;color:#A62525;padding:12px 14px;border-radius:12px;margin-bottom:12px;font-size:13.5px}
</style>

@php
  $items = [
      [
          'key'   => 'before',
          'label' => $has['before'] ? 'Before photo added.' : 'Add a Before photo.',
          'done'  => $has['before'],
          'url'   => route('technician.jobs.photos', ['job' => $job, 'kind' => 'before']),
      ],
      [
          'key'   => 'after',
          'label' => $has['after'] ? 'After photo added.' : 'Add an After photo.',
          'done'  => $has['after'],
          'url'   => route('technician.jobs.photos', ['job' => $job, 'kind' => 'after']),
      ],
      [
          'key'   => 'signature',
          'label' => $has['signature'] ? 'Signature captured.' : 'Get a signature or a code.',
          'done'  => $has['signature'],
          'url'   => route('technician.jobs.signature', $job),
      ],
  ];
@endphp

@if (session('error'))
  <div class="po-alert">{{ session('error') }}</div>
@endif

{{-- ===== WHAT IS STILL NEEDED ===== --}}
<div class="po-card">
  <div class="po-head">What is still needed</div>

  @foreach ($items as $item)
    <div class="po-row" id="po-row-{{ $item['key'] }}" data-done="{{ $item['done'] ? '1' : '0' }}">
      <div class="po-dot {{ $item['done'] ? 'done' : 'pending' }}">
        @if ($item['done'])
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        @else
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        @endif
      </div>
      <div class="po-label"
           data-done-label="{{ $item['key'] === 'before' ? 'Before photo added.' : ($item['key'] === 'after' ? 'After photo added.' : 'Signature captured.') }}"
           data-pending-label="{{ $item['key'] === 'before' ? 'Add a Before photo.' : ($item['key'] === 'after' ? 'Add an After photo.' : 'Get a signature or a code.') }}">
        {{ $item['label'] }}
      </div>
      <a href="{{ $item['url'] }}" class="po-open">Open</a>
    </div>
  @endforeach
</div>

<div class="po-foot">
  Work time is worked out from punch in to punch out, less any time on hold.
</div>

{{-- ===== STICKY ACTION ===== --}}
<div class="po-actions">
  <form id="po-form" method="POST" action="{{ route('technician.jobs.punch-out', $job) }}" style="margin:0;{{ $ready ? '' : 'display:none' }}">
    @csrf
    <button type="submit" class="btn btn-ready">Punch out</button>
  </form>

  <button type="button" id="po-disabled" class="btn btn-disabled" disabled aria-disabled="true" style="{{ $ready ? 'display:none' : '' }}">
    Punch out
  </button>
</div>








<script>
(function () {
  const url = @json(route('technician.jobs.counts', $job));

  const rows = {
    before:    document.getElementById('po-row-before'),
    after:     document.getElementById('po-row-after'),
    signature: document.getElementById('po-row-signature'),
  };
  const formEl    = document.getElementById('po-form');
  const disabledEl = document.getElementById('po-disabled');

  const checkSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
  const xSvg     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

  function applyRow(row, done) {
    if (! row) return;
    if (row.dataset.done === (done ? '1' : '0')) return; // nothing to change
    row.dataset.done = done ? '1' : '0';

    const dot = row.querySelector('.po-dot');
    const label = row.querySelector('.po-label');
    if (done) {
      dot.classList.remove('pending');
      dot.classList.add('done');
      dot.innerHTML = checkSvg;
      label.textContent = label.dataset.doneLabel;
    } else {
      dot.classList.remove('done');
      dot.classList.add('pending');
      dot.innerHTML = xSvg;
      label.textContent = label.dataset.pendingLabel;
    }
  }

  async function tick() {
    try {
      const r = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
      if (! r.ok) return;
      const d = await r.json();

      const hasBefore = (d.before    || 0) > 0;
      const hasAfter  = (d.after     || 0) > 0;
      const hasSig    = !! d.signature;

      applyRow(rows.before,    hasBefore);
      applyRow(rows.after,     hasAfter);
      applyRow(rows.signature, hasSig);

      const ready = hasBefore && hasAfter && hasSig;
      if (ready) {
        formEl.style.display = '';
        disabledEl.style.display = 'none';
      } else {
        formEl.style.display = 'none';
        disabledEl.style.display = '';
      }
    } catch (e) { /* silent */ }
  }

  // Poll every 3 seconds
  setInterval(() => { if (! document.hidden) tick(); }, 3000);
  // Fire immediately on page focus (coming back from the photo/signature page)
  document.addEventListener('visibilitychange', () => { if (! document.hidden) tick(); });
  window.addEventListener('pageshow', tick);  // iOS Safari back-forward cache
})();
</script>

@endsection