@extends('layouts.admin')
@section('title', 'Aging invoices')

@section('content')
@php $base = strtoupper($tenant->base_currency); @endphp
<style>
  .ag-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:auto}
  .ag-card table{width:100%;border-collapse:collapse;font-size:13px}
  .ag-card th{text-align:left;padding:12px 14px;font-size:11px;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;background:#FCFBF8;border-bottom:1px solid var(--line)}
  .ag-card td{padding:12px 14px;border-top:1px solid var(--line)}
  .ag-r{text-align:right !important}
</style>

<a href="{{ route('admin.aging.index', array_filter(['client_id' => $client?->id])) }}" style="color:var(--muted);font-size:13px;text-decoration:none">← Aging</a>
<h1 style="font-family:var(--font-display);font-size:26px;font-weight:700;margin:6px 0 4px">{{ $client?->name ?? 'All clients' }}: {{ $bandLabel }}</h1>
<p style="color:var(--muted);font-size:13px;margin-bottom:18px">Unpaid invoices behind this figure.</p>

<div class="ag-card">
  @if ($invoices->isEmpty())
    <div style="padding:50px 20px;text-align:center;color:var(--muted)">No unpaid invoices.</div>
  @else
    <table>
      <thead><tr><th>Invoice</th><th>Client</th><th>Due date</th><th class="ag-r">Days overdue</th><th class="ag-r">Balance</th><th class="ag-r">Balance ({{ $base }})</th></tr></thead>
      <tbody>
        @foreach ($invoices as $inv)
          <tr>
            <td><a href="{{ route('admin.invoices.show', $inv) }}" style="color:inherit">{{ $inv->invoice_number }}</a></td>
            <td>{{ $inv->client?->name }}</td>
            <td>{{ $inv->due_date?->format('d M Y') ?? 'No due date' }}</td>
            <td class="ag-r">{{ max(0, $inv->days_past_due) }}</td>
            <td class="ag-r">{{ $inv->currency_code }} {{ \App\Services\Currency\Money::format($inv->balance, $inv->currency_code) }}</td>
            <td class="ag-r">{{ \App\Services\Currency\Money::format($inv->base_balance, $base) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
