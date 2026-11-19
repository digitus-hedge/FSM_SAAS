@extends('layouts.admin')
@section('title', 'Company dashboard')

@section('content')
@php
  $isFiltered = $range !== 'last30';

  $statusToStage = [
    'Pending'           => 'needs',
    'Quoted'            => 'quoted',
    'Approved'          => 'assign',
    'Assigned'          => 'assigned',
    'In Progress'       => 'progress',
    'Pending Review'    => 'review',
    'Awaiting Invoice'  => 'invoice',
    'Rework'            => 'rework',
  ];

  $fmt = fn ($m) => \App\Http\Controllers\Tenant\DashboardController::fmtMinutes($m);
  $completionPct = $createdInPeriod > 0 ? round(($completed / $createdInPeriod) * 100, 1) : 0;
  $slaMissed = max(0, $slaCompleted - $slaMet);
  $byStatusRows = collect($byStatus)->values();
  $openTotal = $byStatusRows->sum('count');

  // Greeting + what is waiting (from the open-by-status counts in this view)
  $hour  = (int) now()->format('G');
  $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
  $first = \Illuminate\Support\Str::of(auth()->user()->name ?? '')->before(' ')->toString() ?: 'there';
  $byLabel   = $byStatusRows->pluck('count', 'label');
  $toApprove = (int) ($byLabel['Pending'] ?? 0);
  $toAssign  = (int) ($byLabel['Approved'] ?? 0);
  $toReview  = (int) ($byLabel['Pending Review'] ?? 0);
  $waiting   = $toApprove + $toAssign + $toReview;
  $isCompanyAdmin = auth()->user()?->role?->name === 'Company Admin';

  // Donut for the status split
  $statusColor = [
    'Pending'          => 'var(--bar2)',
    'Quoted'           => '#8E6BBF',
    'Approved'         => 'var(--bar)',
    'Assigned'         => 'var(--acc)',
    'In Progress'      => '#5B7DB1',
    'Pending Review'   => '#E8B100',
    'Awaiting Invoice' => '#E58A3A',
    'Rework'           => '#C8283A',
  ];
  $stops = []; $acc = 0;
  foreach ($byStatusRows as $r) {
      $p = $openTotal > 0 ? ($r['count'] / $openTotal) * 100 : 0;
      $stops[] = ($statusColor[$r['label']] ?? 'var(--track)') . ' ' . round($acc, 2) . '% ' . round($acc + $p, 2) . '%';
      $acc += $p;
  }
  $conic = $stops ? 'conic-gradient(' . implode(',', $stops) . ')' : 'conic-gradient(var(--track) 0 100%)';

  $miniBars = array_slice($chartComplete, -14);
  $miniMax  = max(1, max($miniBars ?: [0]));
  $slaClass = $slaCompleted === 0 ? '' : ($slaPct >= 90 ? 'good' : ($slaPct >= 75 ? 'warn' : 'bad'));

  $chips = ['today' => 'Today', 'last7' => '7 days', 'last30' => '30 days', 'thismonth' => 'This month', 'lastmonth' => 'Last month'];
@endphp

<style>
.dx{display:flex;flex-direction:column;gap:16px;color:var(--ink);--bar2:color-mix(in srgb,var(--bar) 38%,#fff)}
.dx *{box-sizing:border-box}
.dx .tnum{font-variant-numeric:tabular-nums}
.dx :focus-visible{outline:2px solid var(--acc);outline-offset:2px}
.dx .good{color:var(--green)} .dx .warn{color:#C4731F} .dx .bad{color:var(--red)}
.dx-pad{padding:18px 22px}
.dx-h3{font-size:15px;font-weight:700;margin:0}
.dx-sub{color:var(--muted);font-size:12.5px;margin-top:3px}
.dx-eyebrow{font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--acc)}

/* hero */
.dx-hero{display:flex;gap:26px;align-items:stretch;padding:24px 26px;background:var(--surface)}
.dx .card,.dx-hero,.dx-strip,.dx-glance{background:var(--surface)}
.dx-hero .l{flex:1;min-width:0}
.dx-hero h1{font-size:30px;font-weight:700;letter-spacing:-.6px;margin:6px 0 0;line-height:1.15}
.dx-hero p{margin:8px 0 18px;color:var(--muted);line-height:1.5}
.dx-hero p b{color:#8A5A00;font-weight:600}
.dx-acts{display:flex;gap:10px;flex-wrap:wrap}
.dx-b{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:999px;font-weight:600;font-size:13px;text-decoration:none;white-space:nowrap;border:0;cursor:pointer;font-family:inherit}
.dx-b.p{background:var(--btn);color:var(--btntx);box-shadow:0 6px 18px var(--btnsh)}
.dx-b.g{background:var(--surface);color:var(--acc);box-shadow:0 2px 10px rgba(31,41,80,.12)}
.dx-b:hover{transform:translateY(-1px)}
.dx-glance{width:400px;flex:none;background:var(--surface);border:1px solid var(--ln);border-radius:14px;padding:18px 20px}
.dx-glance .t{font-weight:600;margin-bottom:12px}.dx-glance .t span{color:var(--muted);font-weight:400}
.dx-glance .g{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.dx-glance .v{font-size:24px;font-weight:600}.dx-glance .k{color:var(--muted);font-size:12px}

/* filters */
.dx-fbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.dx-chip{padding:9px 16px;border-radius:99px;background:var(--surface);box-shadow:0 2px 10px rgba(31,41,80,.09);color:var(--muted);font-weight:500;text-decoration:none;font-size:13px;border:0;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px}
.dx-chip:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(31,41,80,.14)}
.dx-chip.on{background:var(--acct);color:var(--acc);font-weight:600}
.dx-dates{display:inline-flex;align-items:center;gap:6px;background:var(--surface);box-shadow:0 2px 10px rgba(31,41,80,.09);border-radius:99px;padding:4px 4px 4px 14px}
.dx-date{font-size:13px;font-weight:600;padding:5px 6px;border:0;background:transparent;color:var(--ink);outline:none;font-family:inherit}
.dx-to{color:var(--muted);font-size:12.5px}
.dx-fnote{margin-left:auto;color:var(--muted);font-size:13px;display:flex;gap:10px;align-items:center}
.dx-fnote a{color:var(--ink);font-weight:600;text-decoration:none}

/* kpis */
.dx-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}
.dx-kpi{padding:16px 18px;min-width:0}
.dx-kpi .t{color:var(--muted);display:flex;justify-content:space-between;align-items:center;gap:6px;font-size:12.5px}
.dx-kpi .ico{width:26px;height:26px;border-radius:9px;background:var(--acct);color:var(--acc);display:grid;place-items:center;flex:none}
.dx-kpi .ico svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.dx-kpi .vr{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;margin:8px 0 4px}
.dx-kpi .v{font-size:28px;font-weight:700;letter-spacing:-.5px;line-height:1.1}
.dx-kpi .s{color:var(--muted);font-size:12px}
.dx-d{font-size:11.5px;font-weight:600;padding:2px 8px;border-radius:99px;white-space:nowrap}
.dx-d.up{background:var(--green-soft);color:var(--green)}
.dx-d.dn{background:var(--red-soft);color:var(--red)}

/* strip */
.dx-strip{padding:12px 16px;display:flex;align-items:center;gap:26px;flex-wrap:wrap}
.dx-strip .it{display:flex;align-items:center;gap:10px}
.dx-strip .ic{width:30px;height:30px;border-radius:50%;background:var(--acct);color:var(--acc);display:grid;place-items:center;flex:none}
.dx-strip b{font-size:15px;font-weight:700;display:block}
.dx-strip span.s{color:var(--muted);font-size:12px}
.dx-strip .all{margin-left:auto}

/* two cols */
.dx-two{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.5fr);gap:14px}
.dx-donut{display:flex;gap:22px;align-items:center;margin-top:16px;flex-wrap:wrap}
.dx-ring{position:relative;width:190px;height:190px;border-radius:50%;flex:none}
.dx-ring .in{position:absolute;inset:30px;border-radius:50%;background:var(--surface);display:flex;flex-direction:column;align-items:center;justify-content:center}
.dx-ring .n{font-size:30px;font-weight:700;line-height:1.1}.dx-ring small{font-size:12px;color:var(--muted)}
.dx-leg{flex:1;min-width:170px;display:grid;gap:4px}
.dx-leg a{display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;padding:5px 8px;margin:0 -8px;border-radius:9px}
.dx-leg a:hover{background:var(--fill)}
.dx-leg i{width:8px;height:8px;border-radius:50%;flex:none}
.dx-leg span{flex:1;font-size:13px}.dx-leg b{font-weight:600;font-size:13px}
.dx-empty{color:var(--muted);font-size:13px;padding:24px 0;text-align:center}
.dx-chartwrap{position:relative;height:270px;margin-top:16px}
.dx-seg{display:inline-flex;background:var(--fill);border-radius:99px;padding:4px;box-shadow:inset 0 0 0 1px var(--ln)}
.dx-seg button{border:0;background:none;padding:8px 18px;border-radius:99px;font:600 13px/1 inherit;color:var(--muted);cursor:pointer;transition:background .2s,color .2s,box-shadow .2s}
.dx-seg button.on{background:var(--surface);color:var(--acc);box-shadow:0 2px 10px rgba(31,41,80,.12)}
.dx-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}

/* sla by priority */
.dx-prio{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:22px;margin-top:16px}
.dx-prio .r{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:8px;font-size:13.5px}
.dx-bar{height:8px;border-radius:9px;background:var(--track);overflow:hidden}
.dx-bar i{display:block;height:100%;background:var(--bar);border-radius:9px}

/* banner */
.dx-deep{border-radius:20px;padding:22px 26px;color:#fff;display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;background:linear-gradient(110deg,var(--acc),color-mix(in srgb,var(--acc) 70%,#000))}
.dx-deep h4{font-size:12px;letter-spacing:.9px;text-transform:uppercase;font-weight:700;margin:0 0 10px}
.dx-deep .stats{display:flex;gap:28px;flex-wrap:wrap}
.dx-deep .stats span{display:block;font-size:11.5px;opacity:.85}
.dx-deep .stats b{font-size:22px;font-weight:700}.dx-deep .stats small{font-size:13px;opacity:.85;margin-left:3px}
.dx-mini{display:flex;gap:5px;align-items:flex-end;height:70px;flex:1;max-width:520px;min-width:200px}
.dx-mini i{flex:1;background:rgba(255,255,255,.28);border-radius:5px 5px 2px 2px;min-height:4px}
.dx-mini i.on{background:#fff}
.dx-foot{color:var(--muted);font-size:12px}

@media (max-width:1280px){.dx-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:1100px){.dx-hero{flex-wrap:wrap}.dx-glance{width:100%}.dx-two{grid-template-columns:1fr}}
@media (max-width:640px){.dx-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.dx-hero h1{font-size:24px}.dx-fnote{margin-left:0}.dx-glance .g{grid-template-columns:repeat(2,1fr)}}
</style>

<div class="dx">

  {{-- ============== HERO ============== --}}
  <section class="card dx-hero">
    <div class="l">
      <div class="dx-eyebrow">{{ $tenant->name }} · Company report · <span>● Live</span></div>
      <h1>{{ $greet }}, {{ $first }}</h1>
      <p>
        @if ($waiting > 0)
          <b>{{ $waiting }} {{ \Illuminate\Support\Str::plural('request', $waiting) }}</b> need action in this period: {{ $toApprove }} to approve, {{ $toAssign }} to assign, {{ $toReview }} to review.
        @else
          Nothing is waiting for action in this period.
        @endif
      </p>
      <div class="dx-acts">
        @if ($isCompanyAdmin)
          <a class="dx-b p" href="{{ route('admin.operations-queue.index') }}">Open operations queue</a>
        @endif
        <a class="dx-b g" href="{{ route('service-requests.index') }}">All requests</a>
        @if ($isCompanyAdmin)
          <a class="dx-b g" href="{{ route('admin.expenses-review.index') }}">Expenses to review</a>
        @endif
      </div>
    </div>
    <div class="dx-glance">
      <div class="t">This period <span>{{ $from->format('d M') }} – {{ $to->format('d M Y') }}</span></div>
      <div class="g">
        <div><div class="v tnum">{{ number_format($createdInPeriod) }}</div><div class="k">created</div></div>
        <div><div class="v tnum">{{ number_format($completed) }}</div><div class="k">completed</div></div>
        <div><div class="v tnum">{{ number_format($openRequests) }}</div><div class="k">still open</div></div>
        <div><div class="v tnum">{{ $completionPct }}%</div><div class="k">done</div></div>
      </div>
    </div>
  </section>

  {{-- ============== FILTER ============== --}}
  <form method="GET" id="range-form" class="dx-fbar">
    @foreach ($chips as $key => $label)
      <a class="dx-chip {{ $range === $key ? 'on' : '' }}" href="{{ route('tenant.dashboard', ['range' => $key]) }}">{{ $label }}</a>
    @endforeach
    <input type="hidden" name="range" value="custom">
    <span class="dx-dates">
      <input type="date" name="start" value="{{ $range === 'custom' ? $from->format('Y-m-d') : '' }}" class="dx-date" max="{{ now()->format('Y-m-d') }}" aria-label="Start date">
      <span class="dx-to">to</span>
      <input type="date" name="end" value="{{ $range === 'custom' ? $to->format('Y-m-d') : '' }}" class="dx-date" max="{{ now()->format('Y-m-d') }}" aria-label="End date">
      <button type="submit" class="dx-b p" style="padding:7px 14px">Apply</button>
    </span>
    <span class="dx-fnote">
      <span>{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}</span>
      @if ($isFiltered)<a href="{{ route('tenant.dashboard') }}">Reset</a>@endif
    </span>
  </form>

  {{-- ============== KPI CARDS ============== --}}
  <section class="dx-kpis">
    @php
      $ico = [
        'file'   => '<path d="M6 3h8l4 4v14H6zM14 3v4h4M9 12h6M9 16h6"/>',
        'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check'  => '<path d="M5 12l5 5 9-10"/>',
        'bell'   => '<path d="M6 16V11a6 6 0 0112 0v5l2 2H4zM10 21h4"/>',
        'wrench' => '<path d="M14 6a4 4 0 005 5l-9 9-3-3 9-9a4 4 0 01-2-2z"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z"/>',
      ];
      $kpis = [
        ['Created',         number_format($createdInPeriod),                          'new in period',                 'file',  '',        $deltas['created']],
        ['Open requests',   number_format($openRequests),                             'created in period, still open', 'clock', '',        null],
        ['Completed',       number_format($completed),                                $completionPct . '% of created', 'check', '',        $deltas['completed']],
        ['Response time',   $fmt($responseMin),                                       'create → accept',               'bell',  '',        $deltas['response']],
        ['First-time fix',  $firstFixPct . '%',                                       'no rework',                     'wrench','',        $deltas['firstfix']],
        ['SLA compliance',  $slaCompleted > 0 ? $slaPct . '%' : '—',                  $slaCompleted > 0 ? "{$slaMet} met · {$slaMissed} missed" : 'no tracked jobs', 'shield', $slaClass, $deltas['sla']],
      ];
    @endphp
    @foreach ($kpis as [$t, $v, $s, $i, $c, $dl])
      <div class="card dx-kpi">
        <div class="t">{{ $t }}<span class="ico"><svg viewBox="0 0 24 24">{!! $ico[$i] !!}</svg></span></div>
        <div class="vr">
          <span class="v tnum {{ $c }}">{{ $v }}</span>
          @if ($dl)
            <span class="dx-d {{ $dl['good'] ? 'up' : 'dn' }}" title="Compared with the previous {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}">{{ $dl['up'] ? '▲' : '▼' }} {{ $dl['text'] }}</span>
          @endif
        </div>
        <div class="s">{{ $s }}</div>
      </div>
    @endforeach
  </section>

  {{-- ============== TIME STRIP ============== --}}
  <div class="card dx-strip">
    <div class="it">
      <span class="ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/></svg></span>
      <div><b class="tnum">{{ $fmt($resolutionMin) }}</b><span class="s">Resolution · start → done</span></div>
    </div>
    <div class="it">
      <span class="ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/></svg></span>
      <div><b class="tnum">{{ $fmt($reviewMin) }}</b><span class="s">Review · QC pass time</span></div>
    </div>
    <div class="it">
      <span class="ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg></span>
      <div>
        <b>{{ $customerRating !== null ? number_format($customerRating, 1).' stars' : '—' }}</b>
        <span class="s">{{ $nps !== null ? 'Rating · NPS '.$nps : 'Rating · no ratings yet' }}</span>
      </div>
    </div>
    <a href="{{ route('service-requests.index') }}" class="dx-b g all">All requests</a>
  </div>

  {{-- ============== STATUS + DAILY CHART ============== --}}
  <section class="dx-two">
    <div class="card dx-pad">
      <h3 class="dx-h3">Open requests by status</h3>
      <div class="dx-sub">{{ $openTotal }} total in this period</div>
      @if ($byStatusRows->isEmpty())
        <div class="dx-empty">No open requests in this period.</div>
      @else
        <div class="dx-donut">
          <div class="dx-ring" style="background:{{ $conic }}"><div class="in"><span class="n tnum">{{ $openTotal }}</span><small>open</small></div></div>
          <div class="dx-leg">
            @foreach ($byStatusRows as $r)
              @php $stage = $statusToStage[$r['label']] ?? 'all'; @endphp
              <a href="{{ route('service-requests.index', ['stage' => $stage]) }}">
                <i style="background:{{ $statusColor[$r['label']] ?? 'var(--track)' }}"></i><span>{{ $r['label'] }}</span><b class="tnum">{{ $r['count'] }}</b>
              </a>
            @endforeach
          </div>
        </div>
      @endif
    </div>

    <div class="card dx-pad">
      <div class="dx-head">
        <div><h3 class="dx-h3" id="trend-title">Requests per month</h3><div class="dx-sub" id="trend-sub">Created and completed, last 12 months</div></div>
        <div class="dx-seg" role="group" aria-label="Chart interval">
          <button type="button" class="on" data-k="monthly">Monthly</button>
          <button type="button" data-k="weekly">Weekly</button>
        </div>
      </div>
      <div class="dx-chartwrap"><canvas id="trend-chart" aria-label="Requests created and completed per month or week"></canvas></div>
    </div>
  </section>

  {{-- ============== SLA BY PRIORITY ============== --}}
  <section class="card dx-pad">
    <div class="dx-head">
      <div><h3 class="dx-h3">SLA by priority</h3><div class="dx-sub">Response and resolution met</div></div>
      <span class="pill-ok" style="background:var(--green-soft);color:var(--green);padding:4px 12px;border-radius:99px;font-size:12px;font-weight:600">{{ $slaCompleted > 0 ? $slaPct . '% overall' : 'No tracked jobs' }}</span>
    </div>
    <div class="dx-prio">
      @foreach ($slaByPriority as $r)
        @php $c = $r['completed'] === 0 ? '' : ($r['pct'] >= 90 ? 'good' : ($r['pct'] >= 75 ? 'warn' : 'bad')); @endphp
        <div>
          <div class="r" title="{{ $r['met'] }} met of {{ $r['completed'] }} completed"><span>{{ $r['label'] }}</span><b class="tnum {{ $c }}">{{ $r['completed'] > 0 ? $r['pct'] . '%' : '—' }}</b></div>
          <div class="dx-bar"><i style="width:{{ min(100, $r['pct']) }}%"></i></div>
        </div>
      @endforeach
    </div>
  </section>

  {{-- ============== PERIOD SUMMARY BANNER ============== --}}
  <section class="dx-deep">
    <div>
      <h4>Period summary · {{ $from->format('d M') }} – {{ $to->format('d M Y') }}</h4>
      <div class="stats">
        <div><span>Completed</span><b class="tnum">{{ number_format($completed) }}</b><small>/{{ number_format($createdInPeriod) }}</small></div>
        <div><span>SLA met/missed</span><b class="tnum">{{ $slaCompleted > 0 ? $slaPct . '%' : '—' }}</b><small>{{ $slaCompleted > 0 ? "{$slaMet}/{$slaMissed}" : '' }}</small></div>
        <div><span>First-time fix</span><b class="tnum">{{ $firstFixPct }}%</b></div>
      </div>
    </div>
    <div class="dx-mini" title="Completed per day (last {{ count($miniBars) }} days of range)">
      @foreach ($miniBars as $i => $v)
        <i class="{{ $i === count($miniBars) - 1 ? 'on' : '' }}" style="height:{{ max(6, ($v / $miniMax) * 100) }}%"></i>
      @endforeach
    </div>
  </section>

  <div class="dx-foot">Updated {{ now()->format('H:i') }}. Figures refresh on each page load.</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  const cv = document.getElementById('trend-chart');
  if (!cv || !window.Chart) return;
  const T = @json($trend);
  const wrap = cv.parentElement;

  // Resolve a CSS variable to a real colour (canvas cannot read color-mix / var()).
  function col(v) {
    const s = document.createElement('span');
    s.style.color = 'var(' + v + ')'; s.style.display = 'none';
    wrap.appendChild(s);
    const c = getComputedStyle(s).color; s.remove(); return c;
  }
  function palette() { return { created: col('--bar2'), done: col('--bar'), muted: col('--muted'), line: col('--ln'), ink: col('--ink') }; }

  let p = palette(), key = 'monthly';
  const chart = new Chart(cv, {
    type: 'bar',
    data: {
      labels: T[key].labels,
      datasets: [
        { label: 'Created',   data: T[key].created,   backgroundColor: p.created, borderRadius: 6, borderSkipped: 'bottom', barPercentage: .9, categoryPercentage: .8 },
        { label: 'Completed', data: T[key].completed, backgroundColor: p.done,    borderRadius: 6, borderSkipped: 'bottom', barPercentage: .9, categoryPercentage: .8 },
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          intersect: false, mode: 'index', backgroundColor: p.ink,
          callbacks: { title: (items) => T[key].full[items[0].dataIndex] }
        }
      },
      scales: {
        x: { grid: { display: false }, border: { color: p.line }, ticks: { color: p.muted, font: { size: 12 }, maxRotation: 0 } },
        y: { display: false, beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });

  // Monthly / Weekly toggle
  const titles = { monthly: ['Requests per month', 'Created and completed, last 12 months'], weekly: ['Requests per week', 'Created and completed, last 12 weeks'] };
  document.querySelectorAll('.dx-seg button').forEach(function (b) {
    b.addEventListener('click', function () {
      key = b.dataset.k;
      document.querySelectorAll('.dx-seg button').forEach(function (x) { x.classList.toggle('on', x === b); });
      document.getElementById('trend-title').textContent = titles[key][0];
      document.getElementById('trend-sub').textContent = titles[key][1];
      chart.data.labels = T[key].labels;
      chart.data.datasets[0].data = T[key].created;
      chart.data.datasets[1].data = T[key].completed;
      chart.update();
    });
  });

  // Re-colour when the theme dock changes the theme.
  window.addEventListener('aw-theme', function () {
    p = palette();
    chart.data.datasets[0].backgroundColor = p.created;
    chart.data.datasets[1].backgroundColor = p.done;
    chart.options.scales.x.ticks.color = p.muted;
    chart.options.scales.x.border.color = p.line;
    chart.options.plugins.tooltip.backgroundColor = p.ink;
    chart.update();
  });
})();
</script>
@endsection