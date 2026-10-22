@extends('layouts.admin')
@section('title', 'Business Hours')
@section('hide_layout_errors', true)

@section('content')
<style>
  .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px}
  .page-head h1{font-family:'Figtree';font-size:26px;font-weight:700;letter-spacing:-.3px}
  .page-head p{color:var(--muted);font-size:13px;margin-top:3px}

  .btn{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer;transition:background .15s}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:#F0B700}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{background:var(--soft)}

  .form-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;max-width:960px}

  .intro{padding:20px 24px;background:#FCFBF8;border-bottom:1px solid var(--line);display:flex;gap:14px;align-items:flex-start}
  .intro-ico{flex-shrink:0;width:36px;height:36px;background:var(--yellow-soft);color:var(--amber);display:flex;align-items:center;justify-content:center;border-radius:10px}
  .intro-ico svg{width:18px;height:18px}
  .intro-text{font-size:12px;color:var(--muted);line-height:1.55}
  .intro-text strong{color:var(--ink);font-weight:600}

  /* ---- Column header strip ---- */
  .col-head{
    display:grid;grid-template-columns:220px 150px 140px 140px 1fr;gap:20px;align-items:center;
    padding:12px 24px;background:#FCFBF8;border-bottom:1px solid var(--line);
    font-size:10px;font-weight:700;color:var(--subtle);text-transform:uppercase;letter-spacing:.08em;
  }
  .col-head .ch-dur{text-align:right}

  /* ---- Day row ---- */
  .day-row{
    display:grid;grid-template-columns:220px 150px 140px 140px 1fr;gap:20px;align-items:center;
    padding:16px 24px;border-bottom:1px solid var(--line);transition:background .12s;
  }
  .day-row:last-of-type{border-bottom:0}
  .day-row:hover{background:#FDFCF9}
  .day-row.is-closed{background:#FCFBF8}
  .day-row.is-closed .time-input{opacity:.4;pointer-events:none}
  .day-row.is-closed .time-field label{opacity:.5}

  .day-label{display:flex;align-items:center;gap:12px;min-width:0}
  .day-dot{
    width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
    font-family:'Figtree';font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
    background:#FCFBF8;color:var(--subtle);border:1px solid var(--line);
  }
  .day-row:not(.is-closed) .day-dot{background:var(--yellow-soft);color:var(--amber);border-color:transparent}
  .day-name{font-family:'Figtree';font-size:14px;font-weight:600;color:var(--ink);min-width:0}
  .day-name .sub{display:block;font-size:11px;color:var(--muted);font-weight:400;margin-top:2px;font-family:'DM Sans';white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

  /* ---- Toggle (fixed width so neighbors never jitter) ---- */
  .toggle-wrap{display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none}
  .toggle-wrap input{position:absolute;opacity:0;pointer-events:none}
  .toggle-wrap .track{position:relative;width:36px;height:20px;background:#D7D4CB;border-radius:999px;transition:background .15s;flex-shrink:0}
  .toggle-wrap .track::after{content:'';position:absolute;top:2px;left:2px;width:16px;height:16px;background:#fff;border-radius:50%;transition:transform .15s;box-shadow:0 1px 2px rgba(0,0,0,.15)}
  .toggle-wrap input:checked ~ .track{background:var(--yellow)}
  .toggle-wrap input:checked ~ .track::after{transform:translateX(16px)}
  .toggle-wrap .lbl{font-size:12px;color:var(--muted);font-weight:600;letter-spacing:.02em}
  .toggle-wrap input:checked ~ .lbl{color:var(--ink)}

  /* ---- Time inputs (aligned baseline) ---- */
  .time-field{display:flex;flex-direction:column;gap:4px;min-width:0}
  .time-field label{font-size:10px;font-weight:600;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em}
  .time-input{
    width:100%;font-family:'DM Sans';font-size:13px;padding:8px 12px;border-radius:8px;
    border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s, box-shadow .15s;
    font-variant-numeric:tabular-nums;
  }
  .time-input:focus{border-color:var(--yellow);box-shadow:0 0 0 3px rgba(255,198,26,.15)}

  /* ---- Duration (right-aligned) ---- */
  .duration{font-size:12px;color:var(--muted);font-variant-numeric:tabular-nums;text-align:right;justify-self:end;min-width:120px}
  .duration strong{color:var(--ink);font-weight:600;font-size:13px}
  .duration .closed-tag{display:inline-block;background:var(--soft);color:var(--muted);padding:4px 10px;border-radius:999px;font-weight:600;font-size:10px;text-transform:uppercase;letter-spacing:.06em}
  .duration .invalid{color:var(--red);font-weight:600}

  .err-row{padding:6px 24px 14px 24px;background:#FFF7F7;border-bottom:1px solid #FEE2E2}
  .err-row small{display:block;color:var(--red);font-size:11px;font-weight:500;margin-top:2px}

  .quick-actions{display:flex;gap:8px;padding:14px 24px;background:#FCFBF8;border-top:1px solid var(--line);border-bottom:1px solid var(--line);flex-wrap:wrap}
  .chip{display:inline-flex;align-items:center;gap:5px;font-family:'DM Sans';font-size:11px;font-weight:600;padding:6px 12px;border-radius:999px;border:1px dashed var(--line);background:transparent;color:var(--muted);cursor:pointer;transition:all .12s}
  .chip:hover{border-color:var(--yellow);color:var(--amber);background:#FFFCF2}

  .form-actions{display:flex;gap:10px;align-items:center;justify-content:flex-end;padding:20px 24px;border-top:1px solid var(--line)}

  /* ---- Mobile stacking ---- */
  @media (max-width:720px){
    .col-head{display:none}
    .day-row{
      grid-template-columns:1fr 1fr;grid-template-rows:auto auto auto;
      gap:12px;padding:16px 18px;
    }
    .day-row .day-label{grid-column:1 / -1}
    .day-row .toggle-wrap{grid-column:1 / -1;padding-bottom:4px;border-bottom:1px dashed var(--line)}
    .day-row .duration{grid-column:1 / -1;text-align:left;justify-self:start;min-width:0;margin-top:4px}
    .intro{padding:16px 18px}
  }
</style>

<div class="page-head">
  <div>
    <h1>Business Hours</h1>
    <p>Operating window for your team — the SLA scheduler uses these to pause response and resolution timers outside business hours.</p>
  </div>
</div>

<form method="POST" action="{{ route('admin.tenant-business-hours.update') }}" class="form-card" id="bh-form">
  @csrf @method('PUT')

  <div class="intro">
    <span class="intro-ico">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
      </svg>
    </span>
    <div class="intro-text">
      <strong>How this is used:</strong> Service requests created outside business hours still raise, but the SLA clock
      doesn't start running until the next business day. The scheduler also uses this grid together with
      <a href="{{ route('admin.tenant-holidays.index') }}" style="color:var(--amber);font-weight:600">Holidays</a>
      to work out response and resolution deadlines.
    </div>
  </div>

  <div class="quick-actions">
    <button type="button" class="chip" data-preset="weekdays">Mon–Fri 9–6</button>
    <button type="button" class="chip" data-preset="247">24 × 7</button>
    <button type="button" class="chip" data-preset="halfsat">Mon–Fri 9–6, Sat half-day</button>
    <button type="button" class="chip" data-preset="clear">Mark all closed</button>
  </div>

  <div class="col-head">
    <div>Day</div>
    <div>Status</div>
    <div>Opens</div>
    <div>Closes</div>
    <div class="ch-dur">Daily window</div>
  </div>

  @php
    // Display order: Mon → Sun
    $order = [1, 2, 3, 4, 5, 6, 0];
  @endphp

  @foreach ($order as $wd)
    @php $row = $hours[$wd] ?? null; @endphp
    @continue(!$row)

    @php
      $isOpen = old("hours.$wd.is_open", $row->is_open) ? true : false;
      $opens  = old("hours.$wd.opens_at",  optional($row->opens_at)->format('H:i') ?? '09:00');
      $closes = old("hours.$wd.closes_at", optional($row->closes_at)->format('H:i') ?? '18:00');
      $hasErr = $errors->has("hours.$wd.opens_at") || $errors->has("hours.$wd.closes_at");
    @endphp

    <div class="day-row {{ $isOpen ? '' : 'is-closed' }}" data-wd="{{ $wd }}">
      <input type="hidden" name="hours[{{ $wd }}][weekday]" value="{{ $wd }}">
      <input type="hidden" name="hours[{{ $wd }}][is_open]" value="{{ $isOpen ? '1' : '0' }}" class="hidden-isopen">

      <div class="day-label">
        <span class="day-dot">{{ \App\Models\TenantBusinessHour::SHORT_LABELS[$wd] }}</span>
        <div class="day-name">
          {{ $weekdays[$wd] }}
          <span class="sub">
            @if ($wd === 0) Default: closed
            @elseif ($wd === 6) Default: half-day
            @else Default: full working day
            @endif
          </span>
        </div>
      </div>

      <label class="toggle-wrap">
        <input type="checkbox" {{ $isOpen ? 'checked' : '' }} class="toggle-open">
        <span class="track"></span>
        <span class="lbl toggle-text">{{ $isOpen ? 'Open' : 'Closed' }}</span>
      </label>

      <div class="time-field">
        <label>Opens</label>
        <input type="time" name="hours[{{ $wd }}][opens_at]" value="{{ $opens }}" class="time-input input-opens">
      </div>

      <div class="time-field">
        <label>Closes</label>
        <input type="time" name="hours[{{ $wd }}][closes_at]" value="{{ $closes }}" class="time-input input-closes">
      </div>

      <div class="duration">
        <span class="dur-text"></span>
      </div>
    </div>

    @if ($hasErr)
      <div class="err-row">
        @error("hours.$wd.opens_at")  <small>{{ $message }}</small> @enderror
        @error("hours.$wd.closes_at") <small>{{ $message }}</small> @enderror
      </div>
    @endif
  @endforeach

  <div class="form-actions">
    <a href="{{ route('admin.tenant-business-hours.index') }}" class="btn btn-ghost">Reset</a>
    <button type="submit" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      Save business hours
    </button>
  </div>
</form>

<script>
(function () {
  const rows = document.querySelectorAll('.day-row');

  function syncRow(row) {
    const isOpen = row.querySelector('.toggle-open').checked;
    row.classList.toggle('is-closed', !isOpen);
    row.querySelector('.hidden-isopen').value = isOpen ? '1' : '0';
    row.querySelector('.toggle-text').textContent = isOpen ? 'Open' : 'Closed';
    updateDuration(row);
  }

  function updateDuration(row) {
    const dur = row.querySelector('.dur-text');
    const isOpen = row.querySelector('.toggle-open').checked;
    if (!isOpen) {
      dur.innerHTML = '<span class="closed-tag">Closed</span>';
      return;
    }
    const o = row.querySelector('.input-opens').value;
    const c = row.querySelector('.input-closes').value;
    if (!o || !c) { dur.textContent = ''; return; }
    const [oh, om] = o.split(':').map(Number);
    const [ch, cm] = c.split(':').map(Number);
    const mins = (ch * 60 + cm) - (oh * 60 + om);
    if (mins <= 0) { dur.innerHTML = '<span class="invalid">Invalid range</span>'; return; }
    const h = Math.floor(mins / 60), m = mins % 60;
    dur.innerHTML = `<strong>${h}h${m ? ' '+m+'m' : ''}</strong> open`;
  }

  rows.forEach(row => {
    row.querySelector('.toggle-open').addEventListener('change', () => syncRow(row));
    row.querySelector('.input-opens').addEventListener('input', () => updateDuration(row));
    row.querySelector('.input-closes').addEventListener('input', () => updateDuration(row));
    syncRow(row);
  });

  const presets = {
    weekdays: wd => wd >= 1 && wd <= 5 ? ['09:00','18:00',true] : [null,null,false],
    '247':    wd => ['00:00','23:59',true],
    halfsat:  wd => {
      if (wd >= 1 && wd <= 5) return ['09:00','18:00',true];
      if (wd === 6)           return ['09:00','13:00',true];
      return [null, null, false];
    },
    clear:    wd => [null,null,false],
  };

  document.querySelectorAll('.chip[data-preset]').forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.dataset.preset;
      const fn  = presets[key];
      rows.forEach(row => {
        const wd = parseInt(row.dataset.wd, 10);
        const [open, close, isOpen] = fn(wd);
        row.querySelector('.toggle-open').checked = !!isOpen;
        if (open)  row.querySelector('.input-opens').value  = open;
        if (close) row.querySelector('.input-closes').value = close;
        syncRow(row);
      });
    });
  });
})();
</script>
@endsection