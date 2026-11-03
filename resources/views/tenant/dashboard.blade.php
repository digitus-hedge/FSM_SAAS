@extends('layouts.admin')
@section('title', 'Company dashboard')

@section('content')
<style>
  .dash-head{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:22px;flex-wrap:wrap;gap:16px}
  .dash-head h1{font-family:'Figtree';font-size:34px;font-weight:800;margin:0;color:#0F0F17}
  .dash-head p{color:#5F5D6E;font-size:14px;margin-top:6px}

  .range-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .range-sel{appearance:none;font-family:'DM Sans';font-size:13px;font-weight:600;padding:10px 34px 10px 16px;border-radius:12px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
  .range-info{color:#5F5D6E;font-size:13px}
  .btn-refresh{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:13px;font-weight:700;padding:9px 16px;border-radius:12px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;cursor:pointer;text-decoration:none}
  .btn-refresh:hover{border-color:#0F0F17}


  .btn-clear{display:inline-flex;align-items:center;gap:6px;font-family:'DM Sans';font-size:12.5px;font-weight:600;padding:9px 14px;border-radius:999px;border:1px dashed #E5E4EC;background:transparent;color:#5F5D6E;cursor:pointer;text-decoration:none;transition:all .12s}
.btn-clear:hover{border-color:#C2381B;color:#C2381B;background:#FDECE7}

  .kpi-grid{display:grid;grid-template-columns:repeat(4, 1fr);gap:14px;margin-bottom:18px}
  @media (max-width:1100px){ .kpi-grid{grid-template-columns:repeat(2,1fr)} }
  @media (max-width:600px){ .kpi-grid{grid-template-columns:1fr} }

  .kpi{background:#fff;border:1px solid #E5E4EC;border-radius:16px;padding:18px 20px}
  .kpi .lbl{color:#5F5D6E;font-size:12.5px;font-weight:600;margin-bottom:8px}
  .kpi .val{font-family:'Figtree';font-size:32px;font-weight:800;color:#0F0F17;line-height:1.1}
  .kpi .sub{color:#8B89A0;font-size:12px;margin-top:6px}

  .two-col{display:grid;grid-template-columns:420px 1fr;gap:14px;margin-bottom:16px}
  @media (max-width:960px){ .two-col{grid-template-columns:1fr} }

  .card{background:#fff;border:1px solid #E5E4EC;border-radius:16px;padding:20px}
  .card h3{font-family:'Figtree';font-size:16px;font-weight:700;margin:0 0 16px;color:#0F0F17}

  .status-row{display:grid;grid-template-columns:110px 1fr 30px;align-items:center;gap:12px;padding:7px 0;font-size:13.5px}
  .status-row .label{color:#5F5D6E;font-weight:500}
  .status-row .bar{height:10px;background:#F3F4F8;border-radius:999px;overflow:hidden}
  .status-row .fill{height:100%;background:#315BF5;border-radius:999px;transition:width .5s ease}
  .status-row .num{color:#0F0F17;font-weight:700;text-align:right}

  .chart-wrap{position:relative;height:220px}

  .sla-table{width:100%;border-collapse:collapse;font-size:13.5px}
  .sla-table thead th{text-align:left;padding:12px 14px;font-weight:600;font-size:11px;color:#5F5D6E;text-transform:uppercase;letter-spacing:.06em;background:#FAFAFC;border-bottom:1px solid #EEEDF3}
  .sla-table tbody td{padding:14px;border-bottom:1px solid #F2F1F6;vertical-align:middle;color:#0F0F17}
  .sla-table tbody tr:last-child td{border-bottom:0}

  .pri-pill{display:inline-block;padding:3px 11px;border-radius:999px;font-size:12px;font-weight:600}
  .pri-emergency{background:#FDECE7;color:#C2381B}
  .pri-critical{background:#FFEDD5;color:#C2410C}
  .pri-high{background:#FFF4CC;color:#8A5A00}
  .pri-medium{background:#DBEAFE;color:#1E40AF}
  .pri-low{background:#F3F4F8;color:#5F5D6E}

  .foot-note{color:#8B89A0;font-size:12px;margin-top:16px}

  .date-range{display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid #E5E4EC;border-radius:12px;padding:4px 6px}
.date-inp{font-family:'DM Sans';font-size:13px;font-weight:600;padding:6px 10px;border-radius:8px;border:1px solid transparent;background:transparent;color:#0F0F17;outline:none;min-width:130px;cursor:pointer}
.date-inp:focus{border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.12)}
.date-to{color:#8B89A0;font-size:12.5px;font-weight:500;padding:0 2px}
.btn-apply{font-family:'DM Sans';font-size:12.5px;font-weight:700;padding:7px 14px;border-radius:9px;border:0;background:#2F5BEA;color:#fff;cursor:pointer;transition:background .12s}
.btn-apply:hover{background:#2749C8}

/* Make the dropdown look like the mockup */
.range-sel{min-width:160px}
.range-sel:focus{border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.12);outline:none}
.btn-clear{border-color: #C2381B;
    color: #C2381B;}

.status-row-link {
  display: block;
  text-decoration: none;
  color: inherit;
  padding: 2px 6px;
  margin: 0 -6px;
  border-radius: 8px;
  transition: background .12s;
}
.status-row-link:hover {
  background: #FAFAFC;
}
.status-row-link:hover .label {
  color: #2F5BEA;
  font-weight: 600;
}
.status-row-link:hover .num {
  color: #2F5BEA;
}
.status-row-link:hover .fill {
  background: #2749C8;
}
</style>

<div class="dash-head">
  <div>
    <h1>Company Dashboard</h1>
    <p>How the company is doing.</p>
  </div>
</div>

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

@endphp

<form method="GET" class="range-bar" id="range-form" style="margin-bottom:22px">
  <select name="range" id="range-sel" class="range-sel">
    <option value="today"     @selected($range==='today')>Today</option>
    <option value="last7"     @selected($range==='last7')>Last 7 days</option>
    <option value="last30"    @selected($range==='last30')>Last 30 days</option>
    <option value="thismonth" @selected($range==='thismonth')>This month</option>
    <option value="lastmonth" @selected($range==='lastmonth')>Last month</option>
    <option value="custom"    @selected($range==='custom')>Custom range</option>
  </select>

  {{-- Custom date pickers --}}
  <div id="custom-dates" class="date-range" style="display:{{ $range === 'custom' ? 'inline-flex' : 'none' }}">
    <input type="date" name="start" value="{{ $range === 'custom' ? $from->format('Y-m-d') : '' }}"
           class="date-inp" max="{{ now()->format('Y-m-d') }}" placeholder="Start">
    <span class="date-to">to</span>
    <input type="date" name="end" value="{{ $range === 'custom' ? $to->format('Y-m-d') : '' }}"
           class="date-inp" max="{{ now()->format('Y-m-d') }}" placeholder="End">
    <button type="submit" class="btn-apply">Apply</button>
  </div>

  <span class="range-info">From {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</span>

  <button type="submit" class="btn-refresh">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
    Refresh
  </button>

  {{-- Clear filter — shows only when NOT the default (last 30 days) --}}
  @if ($isFiltered)
    <a href="{{ route('tenant.dashboard') }}" class="btn-clear">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"/>
        <line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
      Clear filter
    </a>
  @endif
</form>

{{-- ============== KPI TILES ============== --}}
{{-- ============== KPI TILES (Row 1) ============== --}}
<div class="kpi-grid">
  <div class="kpi">
    <div class="lbl">Open requests</div>
    <div class="val">{{ $openRequests }}</div>
    <div class="sub">right now</div>
  </div>
  <!-- <div class="kpi">
    <div class="lbl">New in period</div>
    <div class="val">{{ $createdInPeriod }}</div>
    <div class="sub">created</div>
  </div> -->
  <div class="kpi">
    <div class="lbl">Completed</div>
    <div class="val">{{ $completed }}</div>
    <div class="sub">in the period</div>
  </div>
  <div class="kpi">
    <div class="lbl">SLA compliance</div>
    <div class="val">{{ $slaPct }}%</div>
    <div class="sub">{{ $slaMet }} of {{ $slaCompleted }}</div>
  </div>

   <div class="kpi">
    <div class="lbl">Response time</div>
    <div class="val">{{ \App\Http\Controllers\Tenant\DashboardController::fmtMinutes($responseMin) }}</div>
    <div class="sub">avg from create to accept</div>
  </div>


</div>

{{-- ============== KPI TILES (Row 2) ============== --}}
<div class="kpi-grid">
 
  <div class="kpi">
    <div class="lbl">Resolution time</div>
    <div class="val">{{ \App\Http\Controllers\Tenant\DashboardController::fmtMinutes($resolutionMin) }}</div>
    <div class="sub">avg from start to done</div>
  </div>
  <div class="kpi">
    <div class="lbl">Review time</div>
    <div class="val">{{ \App\Http\Controllers\Tenant\DashboardController::fmtMinutes($reviewMin) }}</div>
    <div class="sub">avg QC pass time</div>
  </div>
  <div class="kpi">
    <div class="lbl">First-time fix</div>
    <div class="val">{{ $firstFixPct }}%</div>
    <div class="sub">no rework</div>
  </div>

   <div class="kpi">
    <div class="lbl">Customer rating</div>
    <div class="val">{{ $customerRating !== null ? number_format($customerRating,1).' stars' : '—' }}</div>
    <div class="sub">{{ $nps !== null ? 'NPS '.$nps : 'No ratings yet' }}</div>
  </div>

</div>

{{-- ============== KPI TILES (Row 3 — small) ============== --}}



{{-- ============== STATUS BARS + CHART ============== --}}
<div class="two-col">
 <div class="card">
  <h3>Open requests by status <span style="font-size:11px;color:#8B89A0;font-weight:500;margin-left:6px">right now</span></h3>
  @forelse ($byStatus as $r)
    @php $stage = $statusToStage[$r['label']] ?? 'all'; @endphp
    <a href="{{ route('service-requests.index', ['stage' => $stage]) }}" class="status-row-link">
      <div class="status-row">
        <div class="label">{{ $r['label'] }}</div>
        <div class="bar"><div class="fill" style="width: {{ ($r['count'] / $byStatusMax) * 100 }}%"></div></div>
        <div class="num">{{ $r['count'] }}</div>
      </div>
    </a>
  @empty
    <div style="color:#8B89A0;font-size:13px;padding:20px 0;text-align:center">No open requests.</div>
  @endforelse
</div>

  <div class="card">
    <h3>Created and completed each day</h3>
    <div class="chart-wrap">
      <canvas id="daily-chart"></canvas>
    </div>
    <div style="color:#8B89A0;font-size:11px;margin-top:8px">
      <span style="display:inline-block;width:10px;height:10px;background:#0F0F17;border-radius:2px;vertical-align:middle;margin-right:5px"></span>Created
      &nbsp;&nbsp;
      <span style="display:inline-block;width:10px;height:10px;background:#00866F;border-radius:2px;vertical-align:middle;margin-right:5px"></span>Completed
    </div>
  </div>
</div>

{{-- ============== SLA BY PRIORITY ============== --}}
<div class="card">
  <h3>SLA by priority</h3>
  <table class="sla-table">
    <thead>
      <tr>
        <th>Priority</th>
        <th>Completed</th>
        <th>Met</th>
        <th>Percent</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($slaByPriority as $r)
        <tr>
          <td><span class="pri-pill pri-{{ $r['priority'] }}">{{ $r['label'] }}</span></td>
          <td>{{ $r['completed'] }}</td>
          <td>{{ $r['met'] }}</td>
          <td>{{ $r['pct'] }}%</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div class="foot-note">Updated {{ now()->format('H:i') }}. Figures are refreshed on each page load.</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  const ctx = document.getElementById('daily-chart');
  if (!ctx) return;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: @json($chartLabels),
      datasets: [
        {
          label: 'Created',
          data: @json($chartCreated),
          backgroundColor: '#0F0F17',
          borderRadius: 3,
          barPercentage: 0.75,
          categoryPercentage: 0.65,
        },
        {
          label: 'Completed',
          data: @json($chartComplete),
          backgroundColor: '#00866F',
          borderRadius: 3,
          barPercentage: 0.75,
          categoryPercentage: 0.65,
        },
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { intersect: false, mode: 'index' } },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#8B89A0', font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
        y: { grid: { color: '#F2F1F6' }, ticks: { color: '#8B89A0', font: { size: 11 }, precision: 0 }, beginAtZero: true }
      }
    }
  });
})();



(function () {
  const sel = document.getElementById('range-sel');
  const custom = document.getElementById('custom-dates');
  const form = document.getElementById('range-form');

  sel.addEventListener('change', () => {
    if (sel.value === 'custom') {
      custom.style.display = 'inline-flex';
      // Don't auto-submit when switching to custom — let user pick dates first
      custom.querySelector('input[name="start"]').focus();
    } else {
      custom.style.display = 'none';
      form.submit();   // auto-submit for preset ranges
    }
  });
})();

</script>
@endsection