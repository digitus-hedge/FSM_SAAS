@extends('layouts.admin')
@section('title', 'Dispatch calendar')

@section('content')
<style>
  .cal-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:14px}
  .cal-head h1{margin:0 0 4px;font-size:22px}
  .cal-head p{margin:0;color:var(--muted);font-size:13px}
  .cal-nav{display:flex;gap:8px;align-items:center}
  .cal-nav a{font-size:12px;font-weight:600;color:var(--ink);text-decoration:none;border:1px solid var(--line);background:#fff;padding:7px 14px;border-radius:999px}
  .cal-nav a:hover{background:var(--soft)}
  .cal-nav .cur{background:var(--yellow-soft);color:var(--amber);border-color:var(--yellow)}
  .cal-scroll{overflow-x:auto;background:#fff;border:1px solid var(--line);border-radius:14px}
  .cal-table{width:100%;min-width:980px;border-collapse:collapse;table-layout:fixed}
  .cal-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:10px;border-bottom:1px solid var(--line);background:#FAFAFB;text-align:left}
  .cal-table th.today{color:var(--amber)}
  .cal-table th.tech{width:150px}
  .cal-table td{vertical-align:top;border-bottom:1px solid var(--line);border-left:1px solid var(--line);padding:6px;height:92px}
  .cal-table tr:last-child td{border-bottom:0}
  .cal-table td.tech{border-left:0;font-size:13px;font-weight:700;padding:10px;background:#FAFAFB}
  .cal-table td.tech small{display:block;font-weight:400;color:var(--muted);font-size:11px}
  .cal-cell.over{background:var(--yellow-soft);box-shadow:inset 0 0 0 2px var(--amber)}
  .cal-card{display:block;text-decoration:none;color:var(--ink);background:#fff;border:1px solid var(--line);border-left:3px solid var(--amber);border-radius:8px;padding:5px 7px;margin-bottom:5px;font-size:11px;line-height:1.35;cursor:grab}
  .cal-card.locked{cursor:default;border-left-color:var(--subtle);background:var(--soft)}
  .cal-card.emergency{border-left-color:var(--red);background:var(--red-soft)}
  .cal-card.drag{opacity:.4}
  .cal-card b{font-size:11px}
  .cal-card span{display:block;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .cal-off{font-size:10.5px;font-weight:600;color:var(--muted);background:#EEEEF2;border-radius:6px;padding:3px 7px;margin-bottom:5px}
  .cal-empty{padding:28px;text-align:center;color:var(--muted);font-size:13px}
  .cal-key{font-size:12px;color:var(--muted);margin-top:10px}
  .cal-links a{color:var(--amber);font-weight:600;text-decoration:none;border-bottom:1px solid var(--yellow);font-size:13px;margin-left:14px}
  .cal-modal{position:fixed;inset:0;background:rgba(27,26,36,.45);display:none;align-items:center;justify-content:center;z-index:60;padding:16px}
  .cal-modal.show{display:flex}
  .cal-box{background:#fff;border-radius:16px;width:100%;max-width:400px;padding:22px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
  .cal-box h2{font-size:17px;margin-bottom:4px}
  .cal-box .sub{font-size:12px;color:var(--muted);margin-bottom:14px}
  .cal-box label{font-size:12px;font-weight:600;display:block;margin-bottom:4px}
  .cal-box input{width:100%;font-size:13px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);margin-bottom:12px}
  .cal-box .err{color:var(--red);font-size:12px;font-weight:600;margin-bottom:10px;display:none}
  .cal-box .row{display:flex;justify-content:flex-end;gap:8px}
  .cal-btn{font-size:13px;font-weight:600;padding:9px 18px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer}
  .cal-btn.pri{background:var(--amber);border-color:var(--amber);color:#fff}
</style>

<div class="cal-head">
  <div>
    <h1>Dispatch calendar</h1>
    <p>{{ $days[0]->format('d M') }} to {{ $days[6]->format('d M Y') }}. Drag an assigned job to another day for the same technician, then set the time. Each job is counted as {{ $jobMin }} minutes.</p>
  </div>
  <div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px">
    <div class="cal-nav">
      <a href="{{ route('dispatch.calendar', ['week' => $prevWeek]) }}">&larr; Previous</a>
      <a href="{{ route('dispatch.calendar', ['week' => $thisWeek]) }}" class="{{ $isThis ? 'cur' : '' }}">This week</a>
      <a href="{{ route('dispatch.calendar', ['week' => $nextWeek]) }}">Next &rarr;</a>
    </div>
    <span class="cal-links">
      <a href="{{ route('dispatch.workload') }}">Workload</a>
      <a href="{{ route('dispatch.time-off') }}">Time off</a>
    </span>
  </div>
</div>

<div class="cal-scroll">
@if ($techs->isEmpty())
  <div class="cal-empty">No active technicians or team leads yet.</div>
@else
  <table class="cal-table">
    <thead>
      <tr>
        <th class="tech">Technician</th>
        @foreach ($days as $d)
          <th class="{{ $d->isToday() ? 'today' : '' }}">{{ $d->format('D d M') }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
    @foreach ($techs as $t)
      <tr>
        <td class="tech">{{ $t->name }}@if ($t->role?->code === 'team_lead')<small>Team lead</small>@endif</td>
        @foreach ($days as $d)
          @php $key = $d->format('Y-m-d'); @endphp
          <td class="cal-cell" data-tech="{{ $t->id }}" data-date="{{ $key }}">
            @if (isset($off[$t->id][$key]))<div class="cal-off">{{ $off[$t->id][$key] }}</div>@endif
            @foreach (($grid[$t->id][$key] ?? []) as $j)
              @php $movable = $j->status === 'assigned'; @endphp
              <a href="{{ route('service-requests.show', $j) }}"
                 class="cal-card {{ $movable ? '' : 'locked' }} {{ $j->priority === 'emergency' ? 'emergency' : '' }}"
                 @if ($movable) draggable="true" @else draggable="false" title="Already started, cannot be moved" @endif
                 data-id="{{ $j->id }}" data-tech="{{ $t->id }}" data-time="{{ $j->scheduled_eta_at->format('H:i') }}" data-sr="{{ $j->sr_number }}">
                <b>{{ $j->scheduled_eta_at->format('H:i') }} · {{ $j->sr_number }}</b>
                <span>{{ $j->site->name ?? '—' }}</span>
                <span>{{ $j->title }}</span>
              </a>
            @endforeach
          </td>
        @endforeach
      </tr>
    @endforeach
    </tbody>
  </table>
@endif
</div>
<div class="cal-key">Red jobs are emergencies. Grey jobs have started and cannot be moved. A grey chip marks time off.</div>

<div class="cal-modal" id="cal-modal">
  <div class="cal-box">
    <h2 id="cal-title">Move job</h2>
    <div class="sub" id="cal-sub"></div>
    <label for="cal-time">Time</label>
    <input type="time" id="cal-time" required>
    <label for="cal-reason">Reason <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
    <input type="text" id="cal-reason" maxlength="255">
    <div class="err" id="cal-err"></div>
    <div class="row">
      <button type="button" class="cal-btn" id="cal-cancel">Cancel</button>
      <button type="button" class="cal-btn pri" id="cal-ok">Move job</button>
    </div>
  </div>
</div>

<script>
(function () {
  const csrf   = @json(csrf_token());
  const urlTpl = @json(route('dispatch.calendar.reschedule', ['service_request' => '__ID__']));
  const modal = document.getElementById('cal-modal');
  const timeIn = document.getElementById('cal-time');
  const reasonIn = document.getElementById('cal-reason');
  const errBox = document.getElementById('cal-err');
  const okBtn = document.getElementById('cal-ok');
  let dragged = null, pending = null;

  function clearOver() { document.querySelectorAll('.cal-cell.over').forEach(c => c.classList.remove('over')); }

  document.querySelectorAll('.cal-card[draggable="true"]').forEach(function (c) {
    c.addEventListener('dragstart', function (e) { dragged = c; e.dataTransfer.setData('text/plain', c.dataset.id); c.classList.add('drag'); });
    c.addEventListener('dragend', function () { c.classList.remove('drag'); dragged = null; clearOver(); });
  });

  document.querySelectorAll('.cal-cell').forEach(function (cell) {
    cell.addEventListener('dragover', function (e) {
      if (!dragged || cell.dataset.tech !== dragged.dataset.tech) return;
      e.preventDefault(); cell.classList.add('over');
    });
    cell.addEventListener('dragleave', function () { cell.classList.remove('over'); });
    cell.addEventListener('drop', function (e) {
      if (!dragged || cell.dataset.tech !== dragged.dataset.tech) return;
      e.preventDefault(); clearOver();
      pending = { id: dragged.dataset.id, sr: dragged.dataset.sr, date: cell.dataset.date, confirm: false };
      document.getElementById('cal-title').textContent = 'Move ' + pending.sr;
      document.getElementById('cal-sub').textContent = 'New day: ' + pending.date;
      timeIn.value = dragged.dataset.time; reasonIn.value = ''; errBox.style.display = 'none';
      modal.classList.add('show'); timeIn.focus();
    });
  });

  document.getElementById('cal-cancel').addEventListener('click', function () { modal.classList.remove('show'); pending = null; });
  modal.addEventListener('click', function (e) { if (e.target === modal) { modal.classList.remove('show'); pending = null; } });

  function showErr(msg) { errBox.textContent = msg; errBox.style.display = 'block'; }

  function send() {
    if (!pending || !timeIn.value) { showErr('Choose a time.'); return; }
    okBtn.disabled = true;
    fetch(urlTpl.replace('__ID__', pending.id), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: JSON.stringify({ new_eta: pending.date + 'T' + timeIn.value, reason: reasonIn.value || null, confirm_clash: pending.confirm ? 1 : 0 })
    }).then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
      .then(function (res) {
        okBtn.disabled = false;
        if (res.status === 200) { window.location.reload(); return; }
        if (res.status === 409 && res.body.needs_confirm) {
          const ask = (window.Swal)
            ? Swal.fire({ icon: 'warning', title: 'Another job nearby', text: res.body.message, showCancelButton: true, confirmButtonText: 'Move anyway', cancelButtonText: 'Cancel', reverseButtons: true }).then(r => r.isConfirmed)
            : Promise.resolve(window.confirm(res.body.message));
          ask.then(function (yes) { if (yes) { pending.confirm = true; send(); } });
          return;
        }
        const msg = (res.body && res.body.errors) ? Object.values(res.body.errors)[0][0] : ((res.body && res.body.message) || 'Could not move the job.');
        showErr(msg);
      })
      .catch(function () { okBtn.disabled = false; showErr('Could not reach the server. Try again.'); });
  }
  okBtn.addEventListener('click', function () { if (pending) { pending.confirm = false; send(); } });
})();
</script>
@endsection
