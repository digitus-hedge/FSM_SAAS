@extends('layouts.admin')
@section('title', 'Company dashboard')

@section('content')
@php
  // ─── Dynamic tenant theme ───────────────────────────────────────
  $brand = $tenant->primary_color ?? null;
  if (! is_string($brand) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $brand)) $brand = '#E11D5C';
  $font = $tenant->font_family ?? null;
  if (! is_string($font) || ! preg_match('/^[A-Za-z0-9 ]{2,40}$/', $font)) $font = 'Figtree';

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
  $openTotal = collect($byStatus)->sum('count');

  // Last 14 days of completions for the banner mini-bars
  $miniBars = array_slice($chartComplete, -14);
  $miniMax  = max(1, max($miniBars ?: [0]));
@endphp

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ urlencode($font) }}:wght@400;500;600;700;800&display=swap">

<style>
.dx{
  --brand: {{ $brand }};
  --font: '{{ $font }}';
  --brand-soft: color-mix(in srgb, var(--brand) 12%, #fff);
  --brand-mid:  color-mix(in srgb, var(--brand) 35%, #fff);
  --brand-deep: color-mix(in srgb, var(--brand) 70%, #000);
  --card:#fff; --soft:#F6F5F3; --line:#ECEBE8;
  --ink:#16161A; --muted:#7A7883; --faint:#B7B5BD;
  --good:#16A34A; --good-soft:#E8F6EE; --warn:#B45309; --warn-soft:#FDF2DF; --bad:#DC2626; --bad-soft:#FDECEC;
  font-family: var(--font), 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
  color: var(--ink);
  display:flex; flex-direction:column; gap:18px;
}
.dx *{box-sizing:border-box}
.dx .tnum{font-variant-numeric:tabular-nums}
.dx button,.dx input,.dx select{font-family:inherit}
.dx :focus-visible{outline:2px solid var(--brand);outline-offset:2px}

/* Header + filter bar */
.dx-top{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap}
.dx-eyebrow{font-size:11.5px;letter-spacing:.9px;text-transform:uppercase;color:var(--muted);font-weight:700}
.dx-title{font-size:34px;font-weight:800;letter-spacing:-.8px;color:var(--faint);line-height:1.1;margin:4px 0 0}
.dx-filter{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.dx-sel{appearance:none;font-size:13px;font-weight:600;padding:9px 34px 9px 16px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);cursor:pointer;min-width:160px;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%237A7883' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center}
.dx-dates{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid var(--line);border-radius:999px;padding:4px 4px 4px 10px}
.dx-date{font-size:13px;font-weight:600;padding:5px 6px;border:0;background:transparent;color:var(--ink);outline:none;cursor:pointer}
.dx-to{color:var(--muted);font-size:12.5px}
.dx-apply{font-size:12.5px;font-weight:700;padding:7px 14px;border-radius:999px;border:0;background:var(--brand);color:#fff;cursor:pointer}
.dx-info{color:var(--muted);font-size:13px}
.dx-iconbtn{width:36px;height:36px;border-radius:50%;border:1px solid var(--line);background:#fff;display:grid;place-items:center;cursor:pointer;color:var(--ink);text-decoration:none}
.dx-iconbtn:hover{border-color:var(--ink)}
.dx-clear{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:8px 14px;border-radius:999px;border:1px solid var(--bad);color:var(--bad);text-decoration:none}
.dx-clear:hover{background:var(--bad-soft)}

/* Cards */
.dx-card{background:var(--card);border:1px solid var(--line);border-radius:18px}
.dx-pad{padding:18px 20px}
.dx-h3{font-size:14.5px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dx-h3 small{font-size:11.5px;color:var(--muted);font-weight:500}
.dx-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px}

/* Hero row */
.dx-hero{display:grid;grid-template-columns:minmax(0,1.5fr) repeat(4,minmax(0,1fr));gap:14px;align-items:stretch}
.dx-billed .lbl{font-size:13.5px;font-weight:600}
.dx-big{font-size:48px;font-weight:800;letter-spacing:-1.6px;line-height:1.05;margin:8px 0 10px}
.dx-big small{font-size:20px;color:var(--faint);letter-spacing:-.3px;font-weight:700;margin-left:6px}
.dx-pills{display:flex;gap:8px;flex-wrap:wrap}
.dx-pill{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700}
.dx-pill.soft{background:var(--brand-soft);color:var(--brand)}
.dx-pill.solid{background:var(--brand);color:#fff}
.dx-vs{color:var(--muted);font-size:12.5px;margin-top:10px}

.dx-kpi{padding:16px;display:flex;flex-direction:column;justify-content:center;gap:6px;min-width:0}
.dx-kpi .l{font-size:12.5px;color:var(--muted);font-weight:600;display:flex;justify-content:space-between;gap:6px}
.dx-kpi .n{font-size:26px;font-weight:800;letter-spacing:-.5px;line-height:1.1}
.dx-kpi .s{font-size:12px;color:var(--muted)}
.dx-kpi.center{text-align:center;align-items:center}
.dx-kpi.center .l{justify-content:center}
.dx-kpi.dark{background:var(--ink);border-color:var(--ink);color:#fff}
.dx-kpi.dark .l,.dx-kpi.dark .s{color:rgba(255,255,255,.7)}
.dx-kpi.ring{border:2px solid var(--brand)}
.dx-kpi.ring .s{color:var(--brand);font-weight:700}

/* SLA card */
.dx-sla{padding:16px;text-align:center}
.dx-sla .n{font-size:24px;font-weight:800;margin:4px 0}
.dx-sla .meta{font-size:12px;font-weight:700}
.dx-track{height:6px;border-radius:999px;background:var(--soft);overflow:hidden}
.dx-track i{display:block;height:100%;border-radius:999px;background:var(--brand)}
.dx-sla .dx-track{max-width:340px;margin:10px auto 0}
.good{color:var(--good)} .warn{color:var(--warn)} .bad{color:var(--bad)}

/* Time strip */
.dx-strip{padding:12px 14px;display:flex;align-items:center;gap:26px;flex-wrap:wrap}
.dx-strip .it{display:flex;align-items:center;gap:10px}
.dx-strip .ic{width:30px;height:30px;border-radius:50%;background:var(--brand-soft);color:var(--brand);display:grid;place-items:center;flex:none}
.dx-strip b{font-size:15px;font-weight:800;display:block}
.dx-strip span{color:var(--muted);font-size:12px}
.dx-btn-dark{margin-left:auto;background:var(--ink);color:#fff;border-radius:999px;padding:9px 18px;font-weight:700;font-size:13px;text-decoration:none}

/* Two columns */
.dx-two{display:grid;grid-template-columns:minmax(0,420px) minmax(0,1fr);gap:14px}
.dx-status{display:block;text-decoration:none;color:inherit;border-radius:10px;padding:6px 8px;margin:0 -8px;transition:background .12s}
.dx-status:hover{background:var(--soft)}
.dx-status .row{display:grid;grid-template-columns:30px 1fr auto;gap:10px;align-items:center}
.dx-status .dot{width:28px;height:28px;border-radius:8px;background:var(--brand-soft);color:var(--brand);display:grid;place-items:center;font-size:11px;font-weight:800}
.dx-status .nm{font-weight:600;font-size:13.5px}
.dx-status .num{font-weight:800;font-size:14px}
.dx-status .dx-track{margin:6px 0 2px 40px;height:4px}
.dx-status:hover .nm,.dx-status:hover .num{color:var(--brand)}
.dx-empty{color:var(--muted);font-size:13px;padding:24px 0;text-align:center}
.dx-chart{position:relative;height:240px}
.dx-legend{display:flex;gap:16px;font-size:12px;color:var(--muted)}
.dx-legend i{display:inline-block;width:10px;height:10px;border-radius:3px;vertical-align:middle;margin-right:6px}

/* SLA table */
.dx-tblwrap{overflow-x:auto}
.dx-tbl{width:100%;border-collapse:collapse;min-width:560px}
.dx-tbl th{text-align:left;font-size:11px;letter-spacing:.7px;text-transform:uppercase;color:var(--muted);font-weight:700;padding:12px 20px;border-bottom:1px solid var(--line)}
.dx-tbl td{padding:13px 20px;border-bottom:1px solid var(--line);font-weight:600;font-size:13.5px;vertical-align:middle}
.dx-tbl tr:last-child td{border-bottom:0}
.dx-tbl .pct{display:flex;align-items:center;gap:10px}
.dx-tbl .pct .dx-track{flex:1;max-width:160px;height:5px}
.dx-tbl .pct b{min-width:52px;font-weight:800}
.pri{display:inline-block;padding:3px 11px;border-radius:999px;font-size:12px;font-weight:700}
.pri-emergency{background:#FDECE7;color:#C2381B}
.pri-critical{background:#FFEDD5;color:#C2410C}
.pri-high{background:#FFF4CC;color:#8A5A00}
.pri-medium{background:#DBEAFE;color:#1E40AF}
.pri-low{background:#F3F4F8;color:#5F5D6E}

/* Brand banner */
.dx-deep{border-radius:20px;padding:22px 26px;color:#fff;display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;
  background:linear-gradient(110deg,var(--brand),var(--brand-deep))}
.dx-deep h4{font-size:12.5px;letter-spacing:.9px;text-transform:uppercase;font-weight:800;margin:0 0 10px}
.dx-deep .stats{display:flex;gap:28px;flex-wrap:wrap}
.dx-deep .stats span{display:block;font-size:11.5px;opacity:.85}
.dx-deep .stats b{font-size:22px;font-weight:800}
.dx-deep .stats small{font-size:13px;opacity:.85;margin-left:3px}
.dx-mini{display:flex;gap:5px;align-items:flex-end;height:70px;flex:1;max-width:520px;min-width:200px}
.dx-mini i{flex:1;background:rgba(255,255,255,.28);border-radius:5px 5px 2px 2px;min-height:4px}
.dx-mini i.on{background:#fff}

.dx-foot{color:var(--muted);font-size:12px}

@media (max-width:1180px){.dx-hero{grid-template-columns:repeat(2,minmax(0,1fr))}.dx-billed{grid-column:1/-1}}
@media (max-width:960px){.dx-two{grid-template-columns:1fr}}
@media (max-width:560px){.dx-big{font-size:36px}.dx-title{font-size:26px}.dx-hero{grid-template-columns:1fr}.dx-btn-dark{margin-left:0}}
@media (prefers-reduced-motion:reduce){.dx *{transition:none!important}}
</style>

<div class="dx">

  {{-- ============== HEADER + FILTER ============== --}}
  <div class="dx-top">
    <div>
      <div class="dx-eyebrow">{{ $tenant->name }} · Company report</div>
      <h1 class="dx-title">Company dashboard</h1>
    </div>

    <form method="GET" class="dx-filter" id="range-form">
      <select name="range" id="range-sel" class="dx-sel" aria-label="Date range">
        <option value="today"     @selected($range==='today')>Today</option>
        <option value="last7"     @selected($range==='last7')>Last 7 days</option>
        <option value="last30"    @selected($range==='last30')>Last 30 days</option>
        <option value="thismonth" @selected($range==='thismonth')>This month</option>
        <option value="lastmonth" @selected($range==='lastmonth')>Last month</option>
        <option value="custom"    @selected($range==='custom')>Custom range</option>
      </select>

      <div id="custom-dates" class="dx-dates" style="display:{{ $range === 'custom' ? 'inline-flex' : 'none' }}">
        <input type="date" name="start" value="{{ $range === 'custom' ? $from->format('Y-m-d') : '' }}"
               class="dx-date" max="{{ now()->format('Y-m-d') }}" aria-label="Start date">
        <span class="dx-to">to</span>
        <input type="date" name="end" value="{{ $range === 'custom' ? $to->format('Y-m-d') : '' }}"
               class="dx-date" max="{{ now()->format('Y-m-d') }}" aria-label="End date">
        <button type="submit" class="dx-apply">Apply</button>
      </div>

      <button type="submit" class="dx-iconbtn" title="Refresh" aria-label="Refresh">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      </button>

      @if ($isFiltered)
        <a href="{{ route('tenant.dashboard') }}" class="dx-clear">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Clear filter
        </a>
      @endif
    </form>
  </div>

  {{-- ============== HERO + KPI CARDS ============== --}}
  <section class="dx-hero">
    <div class="dx-billed">
      <div class="lbl">Completed in period</div>
      <div class="dx-big tnum">{{ number_format($completed) }}<small>jobs</small></div>
      <div class="dx-pills">
        <span class="dx-pill soft">{{ $completionPct }}% of created</span>
        <span class="dx-pill solid">+{{ number_format($createdInPeriod) }} created</span>
      </div>
      <div class="dx-vs">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · {{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}</div>
    </div>

    <div class="dx-card dx-kpi">
      <div class="l">Open requests <span>●</span></div>
      <div class="n tnum">{{ number_format($openRequests) }}</div>
      <div class="s">created in period, still open</div>
    </div>

    <div class="dx-card dx-kpi dark">
      <div class="l">Created <span>↗</span></div>
      <div class="n tnum">{{ number_format($createdInPeriod) }}</div>
      <div class="s">new requests in period</div>
    </div>

    <div class="dx-card dx-kpi center">
      <div class="l">Response time</div>
      <div class="n tnum">{{ $fmt($responseMin) }}</div>
      <div class="s">create → accept</div>
    </div>

    <div class="dx-card dx-kpi center ring">
      <div class="l">First-time fix</div>
      <div class="n tnum">{{ $firstFixPct }}%</div>
      <div class="s">no rework</div>
    </div>
  </section>

  {{-- ============== SLA RATE ============== --}}
  @php $slaClass = $slaCompleted === 0 ? '' : ($slaPct >= 90 ? 'good' : ($slaPct >= 75 ? 'warn' : 'bad')); @endphp
  <div class="dx-card dx-sla">
    <div class="dx-eyebrow" style="text-transform:none;letter-spacing:.2px;font-weight:600">SLA compliance</div>
    <div class="n tnum">{{ $slaCompleted > 0 ? $slaPct.'%' : '—' }}</div>
    <div class="meta {{ $slaClass }}">
      {{ $slaCompleted > 0 ? "{$slaMet} met · {$slaMissed} missed of {$slaCompleted}" : 'No SLA-tracked jobs completed in this period' }}
    </div>
    <div class="dx-track"><i style="width:{{ $slaCompleted > 0 ? min(100, $slaPct) : 0 }}%"></i></div>
  </div>

  {{-- ============== TIME STRIP ============== --}}
  <div class="dx-card dx-strip">
    <div class="it">
      <span class="ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/></svg></span>
      <div><b class="tnum">{{ $fmt($resolutionMin) }}</b><span>Resolution · start → done</span></div>
    </div>
    <div class="it">
      <span class="ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/></svg></span>
      <div><b class="tnum">{{ $fmt($reviewMin) }}</b><span>Review · QC pass time</span></div>
    </div>
    <div class="it">
      <span class="ic"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg></span>
      <div>
        <b>{{ $customerRating !== null ? number_format($customerRating, 1).' stars' : '—' }}</b>
        <span>{{ $nps !== null ? 'Rating · NPS '.$nps : 'Rating · no ratings yet' }}</span>
      </div>
    </div>
    <a href="{{ route('service-requests.index') }}" class="dx-btn-dark">All requests</a>
  </div>

  {{-- ============== STATUS + DAILY CHART ============== --}}
  <section class="dx-two">
    <div class="dx-card dx-pad">
      <div class="dx-head"><h3 class="dx-h3">Open requests by status <small>{{ $openTotal }} total</small></h3></div>
      @forelse ($byStatus as $r)
        @php $stage = $statusToStage[$r['label']] ?? 'all'; @endphp
        <a href="{{ route('service-requests.index', ['stage' => $stage]) }}" class="dx-status">
          <div class="row">
            <span class="dot">{{ strtoupper(substr($r['label'], 0, 1)) }}</span>
            <span class="nm">{{ $r['label'] }}</span>
            <span class="num tnum">{{ $r['count'] }}</span>
          </div>
          <div class="dx-track"><i style="width:{{ ($r['count'] / $byStatusMax) * 100 }}%"></i></div>
        </a>
      @empty
        <div class="dx-empty">No open requests in this period.</div>
      @endforelse
    </div>

    <div class="dx-card dx-pad">
      <div class="dx-head">
        <h3 class="dx-h3">Created and completed each day</h3>
        <div class="dx-legend">
          <span><i style="background:var(--ink)"></i>Created</span>
          <span><i style="background:var(--brand)"></i>Completed</span>
        </div>
      </div>
      <div class="dx-chart"><canvas id="daily-chart" aria-label="Requests created and completed per day"></canvas></div>
    </div>
  </section>

  {{-- ============== SLA BY PRIORITY ============== --}}
  <section class="dx-card">
    <div class="dx-pad" style="padding-bottom:4px"><h3 class="dx-h3">SLA by priority</h3></div>
    <div class="dx-tblwrap">
      <table class="dx-tbl">
        <thead><tr><th>Priority</th><th>Completed</th><th>Met</th><th>Met rate</th></tr></thead>
        <tbody>
          @foreach ($slaByPriority as $r)
            @php $c = $r['completed'] === 0 ? '' : ($r['pct'] >= 90 ? 'good' : ($r['pct'] >= 75 ? 'warn' : 'bad')); @endphp
            <tr>
              <td><span class="pri pri-{{ $r['priority'] }}">{{ $r['label'] }}</span></td>
              <td class="tnum">{{ $r['completed'] }}</td>
              <td class="tnum">{{ $r['met'] }}</td>
              <td>
                <div class="pct">
                  <b class="tnum {{ $c }}">{{ $r['completed'] > 0 ? $r['pct'].'%' : '—' }}</b>
                  <div class="dx-track"><i style="width:{{ min(100, $r['pct']) }}%"></i></div>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  {{-- ============== PERIOD SUMMARY BANNER ============== --}}
  <section class="dx-deep">
    <div>
      <h4>Period summary · {{ $from->format('d M') }} – {{ $to->format('d M Y') }}</h4>
      <div class="stats">
        <div><span>Completed</span><b class="tnum">{{ number_format($completed) }}</b><small>/{{ number_format($createdInPeriod) }}</small></div>
        <div><span>SLA met/missed</span><b class="tnum">{{ $slaCompleted > 0 ? $slaPct.'%' : '—' }}</b><small>{{ $slaCompleted > 0 ? "{$slaMet}/{$slaMissed}" : '' }}</small></div>
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
  const ctx = document.getElementById('daily-chart');
  if (!ctx || !window.Chart) return;
  const root  = document.querySelector('.dx');
  const css   = getComputedStyle(root);
  const brand = css.getPropertyValue('--brand').trim() || @json($brand);
  const ink   = css.getPropertyValue('--ink').trim()   || '#16161A';
  const muted = css.getPropertyValue('--muted').trim() || '#7A7883';
  const line  = css.getPropertyValue('--line').trim()  || '#ECEBE8';
  const font  = css.fontFamily;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: @json($chartLabels),
      datasets: [
        { label: 'Created',   data: @json($chartCreated),  backgroundColor: ink,   borderRadius: 4, barPercentage: .75, categoryPercentage: .65 },
        { label: 'Completed', data: @json($chartComplete), backgroundColor: brand, borderRadius: 4, barPercentage: .75, categoryPercentage: .65 },
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { intersect: false, mode: 'index', backgroundColor: ink, titleFont: { family: font }, bodyFont: { family: font } }
      },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { color: muted, font: { size: 10, family: font }, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
        y: { grid: { color: line }, border: { display: false }, ticks: { color: muted, font: { size: 11, family: font }, precision: 0 }, beginAtZero: true }
      }
    }
  });
})();

(function () {
  const sel = document.getElementById('range-sel');
  const custom = document.getElementById('custom-dates');
  const form = document.getElementById('range-form');
  if (!sel) return;
  sel.addEventListener('change', () => {
    if (sel.value === 'custom') {
      custom.style.display = 'inline-flex';
      custom.querySelector('input[name="start"]').focus();
    } else {
      custom.style.display = 'none';
      form.submit();
    }
  });
})();
</script>
@endsection