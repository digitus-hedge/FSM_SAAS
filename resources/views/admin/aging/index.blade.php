@extends('layouts.admin')
@section('title', 'Aging')

@section('content')
@php
  $base = strtoupper($tenant->base_currency);
  $fmt  = fn ($v) => \App\Services\Currency\Money::format($v, $base);
  $link = fn ($band, $client = null) => route('admin.aging.invoices', array_filter(['band' => $band, 'client_id' => $client]));
@endphp
<style>
  .ag-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:auto}
  .ag-card table{width:100%;border-collapse:collapse;font-size:13px}
  .ag-card th{text-align:right;padding:12px 14px;font-size:11px;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;background:#FCFBF8;border-bottom:1px solid var(--line);white-space:nowrap}
  .ag-card th:first-child,.ag-card td:first-child{text-align:left}
  .ag-card td{padding:12px 14px;border-top:1px solid var(--line);text-align:right;white-space:nowrap}
  .ag-card td a{color:inherit;text-decoration:none;border-bottom:1px dotted var(--muted)}
  .ag-card tfoot td{font-weight:700;border-top:2px solid var(--line)}
  .ag-in{font-size:13px;padding:9px 14px;border-radius:999px;border:1px solid var(--line);background:#fff}
  .ag-btn{display:inline-flex;align-items:center;font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);text-decoration:none;cursor:pointer}
</style>

<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <div>
    <h1 style="font-family:var(--font-display);font-size:26px;font-weight:700">Aging</h1>
    <p style="color:var(--muted);font-size:13px;margin-top:3px">As of {{ \Carbon\Carbon::parse($report['today'])->format('d M Y') }}. Unpaid balances in {{ $base }}, by days past the due date.
      @if (collect($report['rows'])->isNotEmpty()) Converted at each document's rate.@endif</p>
  </div>
  @if ($canExport)
    <a class="ag-btn" href="{{ route('admin.aging.export', array_filter(['client_id' => $clientId])) }}">Export</a>
  @endif
</div>

<form method="GET" style="margin-bottom:16px;display:flex;gap:10px">
  <select name="client_id" class="ag-in" onchange="this.form.submit()">
    <option value="">All clients</option>
    @foreach ($clients as $c)<option value="{{ $c->id }}" @selected($clientId === $c->id)>{{ $c->name }}</option>@endforeach
  </select>
</form>

<div class="ag-card">
  @if (empty($report['rows']))
    <div style="padding:50px 20px;text-align:center;color:var(--muted)">No unpaid invoices.</div>
  @else
    <table>
      <thead>
        <tr><th>Client</th>@foreach ($bands as $label)<th>{{ $label }}</th>@endforeach<th>Total</th></tr>
      </thead>
      <tbody>
        @foreach ($report['rows'] as $row)
          <tr>
            <td>{{ $row['client'] }}</td>
            @foreach ($bands as $key => $label)
              <td>@if ((float) $row['bands'][$key] > 0)<a href="{{ $link($key, $row['client_id']) }}">{{ $fmt($row['bands'][$key]) }}</a>@else {{ $fmt($row['bands'][$key]) }} @endif</td>
            @endforeach
            <td><a href="{{ $link('all', $row['client_id']) }}">{{ $fmt($row['total']) }}</a></td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr><td>Total</td>@foreach ($bands as $key => $label)<td>{{ $fmt($report['totals'][$key]) }}</td>@endforeach<td>{{ $fmt($report['totals']['total']) }}</td></tr>
      </tfoot>
    </table>
  @endif
</div>
@endsection
