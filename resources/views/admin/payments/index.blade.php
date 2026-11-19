@extends('layouts.admin')
@section('title', 'Payments')

@section('content')
@php
  $base = strtoupper($tenant->base_currency);
  $tz   = $tenant->timezone ?: config('app.timezone');
@endphp
<style>
  .pl-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:auto}
  .pl-card table{width:100%;border-collapse:collapse;font-size:13px}
  .pl-card th{text-align:left;padding:12px 14px;font-size:11px;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;background:#FCFBF8;border-bottom:1px solid var(--line);white-space:nowrap}
  .pl-card td{padding:12px 14px;border-top:1px solid var(--line);vertical-align:middle}
  .pl-card tbody tr:first-child td{border-top:0}
  .pl-card tbody tr.row-link{cursor:pointer}
  .pl-card tbody tr.row-link:hover{background:#FDFCF9}
  .pl-num{text-align:right;white-space:nowrap}
  .pl-in{font-size:13px;padding:9px 14px;border-radius:999px;border:1px solid var(--line);background:#fff}
  .pl-btn{display:inline-flex;align-items:center;font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);text-decoration:none;cursor:pointer}
  .pl-chip{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;background:var(--red-soft);color:var(--red)}
  .pl-rev td{text-decoration:line-through;color:var(--muted)}
  .pl-rev td .pl-chip{text-decoration:none}
</style>

<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <div>
    <h1 style="font-family:var(--font-display);font-size:26px;font-weight:700">Payments</h1>
    <p style="color:var(--muted);font-size:13px;margin-top:3px">Every payment on every invoice. Totals are in {{ $base }} and leave out reversed payments.</p>
  </div>
  @if ($canExport)
    <a class="pl-btn" href="{{ route('admin.payments.export', request()->query()) }}">Export</a>
  @endif
</div>
@error('export') <div style="color:var(--red);font-size:13px;margin-bottom:12px">{{ $message }}</div> @enderror

<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
  <input type="date" name="from" class="pl-in" value="{{ $f['from'] }}" aria-label="From">
  <input type="date" name="to" class="pl-in" value="{{ $f['to'] }}" aria-label="To">
  <select name="client_id" class="pl-in">
    <option value="">All clients</option>
    @foreach ($clients as $c)<option value="{{ $c->id }}" @selected($f['client_id'] === $c->id)>{{ $c->name }}</option>@endforeach
  </select>
  <select name="method" class="pl-in">
    <option value="">All methods</option>
    @foreach (['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'card' => 'Card', 'other' => 'Other'] as $k => $l)
      <option value="{{ $k }}" @selected($f['method'] === $k)>{{ $l }}</option>
    @endforeach
  </select>
  <select name="currency" class="pl-in">
    <option value="">All currencies</option>
    @foreach ($currencies as $cur)<option value="{{ $cur }}" @selected($f['currency'] === $cur)>{{ $cur }}</option>@endforeach
  </select>
  <label style="font-size:13px;display:flex;gap:6px;align-items:center">
    <input type="checkbox" name="include_reversed" value="1" @checked($f['include_reversed'])> Include reversed
  </label>
  <button class="pl-btn" type="submit">Filter</button>
</form>

<div class="pl-card">
  @if ($payments->isEmpty())
    <div style="padding:50px 20px;text-align:center;color:var(--muted)">
      {{ $hasPayments ? 'No payments match.' : 'No payments recorded yet.' }}
    </div>
  @else
    <table>
      <thead>
        <tr><th>Payment</th><th>Date</th><th>Client</th><th>Invoice</th><th class="pl-num">Amount</th><th class="pl-num">Base amount ({{ $base }})</th><th>Method</th><th>Reference</th><th>Recorded by</th></tr>
      </thead>
      <tbody>
        @foreach ($payments as $p)
          <tr class="row-link {{ $p->reversed_at ? 'pl-rev' : '' }}" onclick="location.href='{{ route('admin.invoices.show', $p->invoice_id) }}'">
            <td>{{ $p->payment_number }}
              @if ($p->reversed_at)<span class="pl-chip" title="{{ $p->reversal_reason }}">Reversed</span>@endif
            </td>
            <td>{{ $p->received_at->copy()->setTimezone($tz)->format('d M Y') }}</td>
            <td>{{ $p->invoice?->client?->name }}</td>
            <td>{{ $p->invoice?->invoice_number }}</td>
            <td class="pl-num">{{ $p->currency_code }} {{ \App\Services\Currency\Money::format($p->amount, $p->currency_code) }}</td>
            <td class="pl-num">{{ \App\Services\Currency\Money::format($p->base_amount, $base) }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $p->method)) }}</td>
            <td>{{ $p->reference }}</td>
            <td>{{ $p->receivedBy?->name }}</td>
          </tr>
          @if ($p->reversed_at && $p->reversal_reason)
            <tr class="pl-rev"><td colspan="9" style="padding-top:0;font-size:12px">Reason: {{ $p->reversal_reason }}</td></tr>
          @endif
        @endforeach
      </tbody>
      <tfoot>
        <tr><td colspan="5" style="padding:14px;font-weight:700;border-top:2px solid var(--line)">Total</td>
            <td class="pl-num" style="padding:14px;font-weight:700;border-top:2px solid var(--line)">{{ $base }} {{ \App\Services\Currency\Money::format($totalBase, $base) }}</td>
            <td colspan="3" style="border-top:2px solid var(--line)"></td></tr>
      </tfoot>
    </table>
  @endif
</div>

<div style="margin-top:14px">{{ $payments->links() }}</div>
@endsection
