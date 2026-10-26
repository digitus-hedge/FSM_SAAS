@extends('layouts.technician')
@section('title', 'Customer signature')

@section('appbar')
<div class="appbar">
  <button type="button" class="back" onclick="history.back()"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></button>
  <h1>Customer signature</h1>
  <span class="sent-chip">All sent</span>
</div>
@endsection

@section('content')
<style>
  .sig-field{width:100%;font-family:'DM Sans';font-size:14.5px;padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;outline:none;margin-bottom:14px;box-sizing:border-box}
  .sig-field:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(47,91,234,.15)}
  .sig-field.has-error{border-color:var(--red);background:#FFF7F7}
  .err-inline{display:flex;align-items:center;gap:4px;font-size:12.5px;color:var(--red);margin:-10px 0 14px;font-weight:600}
  .err-inline::before{content:'⚠'}
</style>

@if ($errors->any())
  <div style="background:var(--red-soft);border:1px solid #F0C7C7;border-radius:12px;padding:12px 14px;margin-bottom:14px;color:var(--red);font-size:13.5px;font-weight:700">
    {{ $errors->first() }}
  </div>
@endif

{{-- ===== Existing signature (if captured) ===== --}}
@if ($existing)
  <div class="card" style="padding:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <div style="font-family:'Figtree';font-size:14px;font-weight:800">Captured</div>
      <span class="chip chip-ok">✓ Signed</span>
    </div>
    <div style="background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px;text-align:center">
      <img src="{{ asset('storage/'.$existing->storage_path) }}?v={{ $existing->updated_at->timestamp ?? '' }}"
           alt="Signature"
           style="max-width:100%;max-height:140px;display:inline-block">
    </div>
    <div style="margin-top:10px;font-size:13.5px">
      <strong>{{ $job->customer_signer_name }}</strong>
      @if ($job->customer_signer_phone) · {{ $job->customer_signer_phone }} @endif
    </div>
    <div style="font-size:12px;color:var(--muted);margin-top:2px">
      Signed {{ \Carbon\Carbon::parse($job->signed_at)->format('g:i A, D j M') }}
    </div>
  </div>

  <div style="font-size:11px;font-weight:800;color:var(--subtle);letter-spacing:.14em;margin:16px 2px 10px;text-transform:uppercase">
    Re-sign (replaces the captured signature)
  </div>
@endif

{{-- ===== Signature form ===== --}}
<form method="POST" action="{{ route('technician.jobs.signature.store', $job) }}" id="sig-form" novalidate>@csrf

  <label style="display:block;font-weight:800;font-size:14px;margin-bottom:6px">Signer name</label>
  <input type="text" name="signer_name"
         value="{{ old('signer_name', $job->customer_signer_name) }}"
         class="sig-field @error('signer_name') has-error @enderror">
  @error('signer_name')<span class="err-inline">{{ $message }}</span>@enderror

  <label style="display:block;font-weight:800;font-size:14px;margin-bottom:6px">Signer phone (optional)</label>
  <input type="text" name="signer_phone"
         value="{{ old('signer_phone', $job->customer_signer_phone) }}" placeholder="+971501234567"
         class="sig-field @error('signer_phone') has-error @enderror">
  @error('signer_phone')<span class="err-inline">{{ $message }}</span>@enderror

  <label style="display:block;font-weight:800;font-size:14px;margin-bottom:6px">Signature</label>
  <canvas id="pad" style="width:100%;height:220px;border:1px solid var(--line);border-radius:12px;background:#fff;touch-action:none;display:block"></canvas>
  @error('signature_data')<span class="err-inline" style="margin-top:4px">{{ $message }}</span>@enderror

  <input type="hidden" name="signature_data" id="sig-data">

  <div style="font-size:12.5px;color:var(--muted);margin:8px 2px 14px">Signed at the place and time of this phone.</div>

  <div style="display:flex;gap:10px">
    <button type="button" class="btn btn-ghost" onclick="clearPad()">Clear</button>
    <button type="button" class="btn btn-primary" onclick="savePadAndSubmit()">
      {{ $existing ? 'Replace signature' : 'Save signature' }}
    </button>
  </div>
</form>

<script>
(function () {
  const c = document.getElementById('pad');
  const ctx = c.getContext('2d');
  let drawing = false, last = null, dirty = false;

  function fit() {
    const r = c.getBoundingClientRect();
    c.width  = r.width  * devicePixelRatio;
    c.height = r.height * devicePixelRatio;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.scale(devicePixelRatio, devicePixelRatio);
    ctx.strokeStyle = '#1B1A24';
    ctx.lineWidth   = 2.2;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';
  }
  fit();
  window.addEventListener('resize', fit);

  function pos(e) { const r = c.getBoundingClientRect(); const p = e.touches ? e.touches[0] : e; return { x: p.clientX - r.left, y: p.clientY - r.top }; }
  function start(e) { drawing = true; last = pos(e); dirty = true; e.preventDefault(); }
  function move(e)  { if (!drawing) return; const p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; e.preventDefault(); }
  function end()    { drawing = false; }

  c.addEventListener('mousedown', start);
  c.addEventListener('mousemove', move);
  c.addEventListener('mouseup', end);
  c.addEventListener('mouseleave', end);
  c.addEventListener('touchstart', start, { passive: false });
  c.addEventListener('touchmove',  move,  { passive: false });
  c.addEventListener('touchend',   end);

  window.clearPad = function () { ctx.clearRect(0, 0, c.width, c.height); dirty = false; document.getElementById('sig-data').value = ''; };
  window.savePadAndSubmit = function () {
    document.getElementById('sig-data').value = dirty ? c.toDataURL('image/png') : '';
    document.getElementById('sig-form').submit();
  };
})();
</script>
@endsection