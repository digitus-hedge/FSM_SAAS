{{-- Dispatch step 1: ranked technician suggestions inside the assign modal. Suggestion only; the dropdown below stays. --}}
<style>
  .sg-box{border:1px solid var(--line);border-radius:12px;margin-bottom:16px;overflow:hidden}
  .sg-head{display:flex;justify-content:space-between;align-items:center;padding:9px 14px;background:#FAFAFB;border-bottom:1px solid var(--line);font-size:12px;font-weight:600}
  .sg-head span{color:var(--muted);font-weight:400}
  .sg-list{max-height:230px;overflow:auto}
  .sg-item{display:block;width:100%;text-align:left;background:#fff;border:0;border-bottom:1px solid var(--line);padding:10px 14px;cursor:pointer;font-family:var(--font-body)}
  .sg-item:last-child{border-bottom:0}
  .sg-item:hover{background:var(--yellow-soft)}
  .sg-item.sel{background:var(--yellow-soft);box-shadow:inset 3px 0 0 var(--amber)}
  .sg-top{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--ink)}
  .sg-score{margin-left:auto;font-size:12px;font-weight:700;color:var(--amber)}
  .sg-pill{font-size:10px;font-weight:600;padding:1px 8px;border-radius:999px}
  .sg-pill.green{background:var(--green-soft);color:var(--green)}
  .sg-pill.amber{background:var(--yellow-soft);color:var(--amber)}
  .sg-pill.red{background:var(--red-soft);color:var(--red)}
  .sg-why{font-size:11px;color:var(--muted);margin-top:3px;line-height:1.5}
  .sg-empty{padding:14px;font-size:12px;color:var(--muted)}
</style>

<div class="sg-box" id="sg-box">
  <div class="sg-head">Suggested technicians <span>Best match first. Click one to select, or choose anyone below.</span></div>
  <div class="sg-list" id="sg-list"></div>
</div>

<script>
(function () {
  const initial = @json($suggestions ?? []);
  const list    = document.getElementById('sg-list');
  const select  = document.getElementById('technician_id');
  const arrival = document.getElementById('arrival_time');
  const url     = @json(route('service-requests.suggestions', $sr));
  if (!list || !select) return;

  function render(items) {
    list.textContent = '';
    if (!items.length) {
      const e = document.createElement('div'); e.className = 'sg-empty';
      e.textContent = 'No active technicians to suggest.'; list.appendChild(e); return;
    }
    items.forEach(function (t) {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'sg-item';
      b.dataset.id = t.id;
      const top = document.createElement('div'); top.className = 'sg-top';
      const nm = document.createElement('span'); nm.textContent = t.name + (t.is_lead ? ' (Team Lead)' : '');
      const pill = document.createElement('span'); pill.className = 'sg-pill ' + t.workload_band;
      pill.textContent = 'Load ' + Math.round(t.workload_pct) + '%';
      const sc = document.createElement('span'); sc.className = 'sg-score'; sc.textContent = t.total + ' / 100';
      top.appendChild(nm); top.appendChild(pill); top.appendChild(sc);
      const why = document.createElement('div'); why.className = 'sg-why'; why.textContent = t.reasons.join(' · ');
      b.appendChild(top); b.appendChild(why);
      b.addEventListener('click', function () { select.value = String(t.id); mark(); });
      list.appendChild(b);
    });
    mark();
  }
  function mark() {
    list.querySelectorAll('.sg-item').forEach(function (b) { b.classList.toggle('sel', b.dataset.id === select.value); });
  }

  let timer = null;
  function refresh() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      if (!arrival.value) return;
      fetch(url + '?arrival=' + encodeURIComponent(arrival.value), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(render)
        .catch(function () {});
    }, 300);
  }

  select.addEventListener('change', mark);
  arrival.addEventListener('change', refresh);
  render(initial);
})();
</script>
