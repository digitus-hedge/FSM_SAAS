@extends('layouts.admin')
@section('title', $code . ' rates')

@section('content')
@php $base = strtoupper($tenant->base_currency); @endphp
<style>
  .cr-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-bottom:20px}
  .cr-card table{width:100%;border-collapse:collapse;font-size:13px}
  .cr-card th{text-align:left;padding:12px 16px;font-size:11px;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;background:#FCFBF8;border-bottom:1px solid var(--line)}
  .cr-card td{padding:12px 16px;border-top:1px solid var(--line)}
  .cr-card tbody tr:first-child td{border-top:0}
  .cr-btn{display:inline-flex;align-items:center;font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);text-decoration:none;cursor:pointer}
  .cr-btn-primary{background:var(--yellow);border-color:var(--yellow)}
  .cr-input{font-size:13px;padding:10px 14px;border-radius:999px;border:1px solid var(--line);background:#fff}
  .cr-err{color:var(--red);font-size:12px;margin-top:6px}
</style>

<a href="{{ route('admin.currencies.index') }}" style="color:var(--muted);font-size:13px;text-decoration:none">← Currencies and rates</a>
<h1 style="font-family:var(--font-display);font-size:26px;font-weight:700;margin:6px 0 20px">{{ $code }} rates</h1>

<form method="POST" action="{{ route('admin.currencies.rates.store', $code) }}" style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;margin-bottom:22px">
  @csrf
  <div>
    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Date</label>
    <input type="date" name="rate_date" class="cr-input" max="{{ $today }}" value="{{ old('rate_date', $today) }}" required>
    @error('rate_date') <div class="cr-err">{{ $message }}</div> @enderror
  </div>
  <div>
    <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">1 {{ $code }} = ? {{ $base }}</label>
    <input type="text" name="rate" inputmode="decimal" class="cr-input" value="{{ old('rate') }}" placeholder="e.g. 3.6725" required>
    @error('rate') <div class="cr-err">{{ $message }}</div> @enderror
  </div>
  <div style="align-self:flex-end"><button type="submit" class="cr-btn cr-btn-primary">Add a rate for a date</button></div>
</form>

<div class="cr-card">
  @if ($history->isEmpty())
    <div style="padding:40px 20px;text-align:center;color:var(--muted)">No rates stored for {{ $code }} yet.</div>
  @else
    <table>
      <thead><tr><th>Date</th><th>Rate (1 {{ $code }} in {{ $base }})</th><th>Source</th></tr></thead>
      <tbody>
        @foreach ($history as $h)
          <tr>
            <td>{{ $h->rate_date->format('d M Y') }}</td>
            <td>{{ rtrim(rtrim(number_format((float) $h->rate, 8, '.', ''), '0'), '.') }}</td>
            <td>{{ $h->source === 'manual' ? 'Entered by hand' : 'Provider' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
