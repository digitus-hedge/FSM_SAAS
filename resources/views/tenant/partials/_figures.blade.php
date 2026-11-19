{{-- Reports-Summaries-Preferences spec RPT-03 to RPT-07: the new figures. $figures is null when the plan or the permission is missing. --}}
@if (! empty($figures))
<div style="margin-top:22px">
  <h3 style="margin-bottom:10px">Operations figures</h3>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px">
    @php
      $tile = function ($label, $value, $sub = null) {
          return compact('label', 'value', 'sub');
      };
      $f = $figures;
      $tiles = [
          $tile('Technician utilisation', $f['utilisation'] ? $f['utilisation']['percent'] . '%' : null, $f['utilisation'] ? $f['utilisation']['booked'] . ' of ' . $f['utilisation']['capacity'] . ' minutes' : null),
          $tile('Maintenance on time', ($f['maintenance']['percent'] ?? null) !== null ? $f['maintenance']['percent'] . '%' : ($f['maintenance'] ? '—' : null), $f['maintenance'] ? $f['maintenance']['on_time'] . ' on time, ' . $f['maintenance']['late'] . ' late, ' . $f['maintenance']['open_overdue'] . ' open and overdue' : null),
          $tile('Maintenance against reactive', $f['pm_vs_reactive'] ? $f['pm_vs_reactive']['percent_maintenance'] . '% maintenance' : null, $f['pm_vs_reactive'] ? $f['pm_vs_reactive']['maintenance'] . ' maintenance, ' . $f['pm_vs_reactive']['reactive'] . ' reactive' : null),
          $tile('Callbacks', ($f['callbacks']['percent'] ?? null) !== null ? $f['callbacks']['percent'] . '%' : ($f['callbacks'] ? '0%' : null), $f['callbacks'] ? $f['callbacks']['reopened'] . ' reopened of ' . $f['callbacks']['completed'] . ' completed (window ' . $f['callbacks']['window_days'] . ' days)' : null),
          $tile('First-time fix', $f['first_time_fix'] ? $f['first_time_fix']['percent'] . '%' : null, $f['first_time_fix'] ? $f['first_time_fix']['first_time'] . ' of ' . $f['first_time_fix']['completed'] : null),
          $tile('Vendor spend', $f['vendor_spend'] ? number_format($f['vendor_spend']['total'], 2) . ' ' . $f['vendor_spend']['currency'] : null, $f['vendor_spend'] ? $f['vendor_spend']['lines'] . ' expense lines' : null),
      ];
    @endphp
    @foreach ($tiles as $t)
      <div class="card" style="padding:14px">
        <div style="font-size:12px;color:var(--muted)">{{ $t['label'] }}</div>
        <div style="font-size:22px;font-weight:700;margin-top:4px">{{ $t['value'] ?? 'No data yet' }}</div>
        @if ($t['sub'])<div style="font-size:12px;color:var(--muted);margin-top:4px">{{ $t['sub'] }}</div>@endif
      </div>
    @endforeach
    <div class="card" style="padding:14px">
      <div style="font-size:12px;color:var(--muted)">Contract use</div>
      @forelse ($f['contract_use'] ?? [] as $c)
        <div style="font-size:13px;margin-top:4px">{{ $c['contract'] }}: {{ $c['used'] }} of {{ $c['max'] }} ({{ $c['percent'] }}%)</div>
      @empty
        <div style="font-size:22px;font-weight:700;margin-top:4px">No data yet</div>
      @endforelse
    </div>
  </div>
</div>
@endif
