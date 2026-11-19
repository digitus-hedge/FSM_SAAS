@extends('layouts.admin')
@section('title', 'Vendor spend')

@section('content')
@include('admin.partials._page')

<div class="page-head">
  <div><h1>Vendor spend</h1><p>Approved expenses of vendor technicians, in {{ $currency }}. Your own technicians are not included.</p></div>
  <a href="{{ route('admin.vendor-spend.export', request()->query()) }}" class="btn btn-ghost">Export to Excel</a>
</div>

<form method="GET" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
  <select name="vendor" class="sel"><option value="">All vendors</option>@foreach ($vendors as $v)<option value="{{ $v->id }}" @selected(request('vendor') == $v->id)>{{ $v->name }}</option>@endforeach</select>
  <input type="month" name="month" class="inp" value="{{ request('month') }}">
  <button class="btn btn-ghost">Filter</button>
</form>

<div class="card">
  <table>
    <thead><tr><th>Vendor</th><th>Month</th><th>Client</th><th style="text-align:right">Spend ({{ $currency }})</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr><td>{{ $r->vendor }}</td><td>{{ $r->month }}</td><td>{{ $r->client ?? '—' }}</td><td style="text-align:right">{{ number_format((float) $r->total, 2) }}</td></tr>
    @empty
      <tr><td colspan="4" style="text-align:center;padding:40px;color:var(--muted)">No vendor expenses yet.</td></tr>
    @endforelse
    </tbody>
    @if ($rows->isNotEmpty())<tfoot><tr style="font-weight:700"><td colspan="3">Total</td><td style="text-align:right">{{ number_format((float) $total, 2) }}</td></tr></tfoot>@endif
  </table>
</div>
@endsection
