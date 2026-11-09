@extends('layouts.admin')
@section('title','Quotes')

@section('content')
<style>
  .page-h1{font-family:var(--font-display);font-size:28px;font-weight:800;margin-bottom:6px}
  .page-sub{color:var(--muted);font-size:14px;margin-bottom:22px}
  .tab-strip{display:flex;gap:0;border-bottom:1px solid var(--line);margin-bottom:22px;}
  .tab-strip a{padding:12px 20px;color:var(--muted);text-decoration:none;font-weight:600;border-bottom:2px solid transparent;position:relative;top:1px;white-space:nowrap}
  .tab-strip a.active{color:var(--ink);border-bottom-color:var(--ink)}
  .tab-strip a .n{color:var(--muted);font-weight:400;margin-left:4px}
  .tab-strip a.active .n{color:var(--ink)}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  table.q{width:100%;border-collapse:collapse;font-size:13.5px}
  table.q th{text-align:left;padding:14px 16px;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line)}
  table.q td{padding:18px 16px;border-bottom:1px solid #F2F1F6;vertical-align:middle}
  table.q tr:last-child td{border-bottom:0}
  table.q tbody tr{cursor:pointer;transition:background .1s}
  table.q tbody tr:hover{background:#F8F7FB}
  .qnum{font-family:var(--font-display);font-weight:800}
  .total{font-family:var(--font-display);font-weight:700;white-space:nowrap}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:700}
  .p-draft{background:#F0EFF5;color:#5A5869}
  .p-sent{background:#EDE9FE;color:#5B3BB8}
  .p-accepted{background:#DDF7F1;color:#00866F}
  .p-rejected{background:#FDECE7;color:#C2381B}
  .p-expired{background:#FFF4CC;color:#8A5A00}
  .p-superseded{background:#F0EFF5;color:#777}
  .empty{padding:60px 20px;text-align:center;color:var(--muted)}


/* ===== Modern select styling for the simulator pickers ===== */
.sim-picker select{
  appearance:none;
  -webkit-appearance:none;
  -moz-appearance:none;
  width:100%;
  padding:11px 36px 11px 14px;
  border:1px solid var(--line);
  border-radius:12px;
  font-family:inherit;
  font-size:13.5px;
  font-weight:600;
  color:var(--ink);
  background:#fff;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235F5D6E' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat:no-repeat;
  background-position:right 14px center;
  background-size:12px;
  cursor:pointer;
  transition:border-color .15s, box-shadow .15s;
  line-height:1.2;
}
.sim-picker select:hover{
  border-color:#C7C4D5;
}
.sim-picker select:focus{
  outline:none;
  border-color:var(--yellow);
  box-shadow:0 0 0 3px color-mix(in srgb, var(--yellow) 15%, transparent);
}

/* Smaller, tighter labels for the pickers */
.sim-picker .lbl{
  font-size:10.5px;
  font-weight:800;
  color:var(--muted);
  text-transform:uppercase;
  letter-spacing:.08em;
  margin:4px 2px 0;
}

/* Space between the two picker rows */
.sim-picker{
  padding:12px 14px;
  gap:4px;
}

 .sim-body{flex:1;background:#F0EFF5;overflow:hidden;padding:10px;min-height:0}
  .sim-body iframe{width:100%;height:100%;border:0;border-radius:12px;background:#fff}
  .sim-empty{color:var(--muted);text-align:center;padding:40px 20px;background:#fff;border-radius:12px;font-size:13.5px}
</style>

@php
  $currencyCode = auth()->user()->tenant?->currency_code ?? 'AED';
@endphp

<h1 class="page-h1">Quotes</h1>
<p class="page-sub">Quotes for requests that were sent for a quote.</p>

<div class="tab-strip">
  <a href="{{ route('admin.quotes.index',['tab'=>'draft']) }}"    class="{{ $tab==='draft'?'active':'' }}">Draft    <span class="n">{{ $counts['draft'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'sent']) }}"     class="{{ $tab==='sent'?'active':'' }}">Sent     <span class="n">{{ $counts['sent'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'accepted']) }}" class="{{ $tab==='accepted'?'active':'' }}">Accepted <span class="n">{{ $counts['accepted'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'rejected']) }}" class="{{ $tab==='rejected'?'active':'' }}">Rejected <span class="n">{{ $counts['rejected'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'expired']) }}"  class="{{ $tab==='expired'?'active':'' }}">Expired  <span class="n">{{ $counts['expired'] }}</span></a>
  <a href="{{ route('admin.quotes.index',['tab'=>'all']) }}"      class="{{ $tab==='all'?'active':'' }}">All      <span class="n">{{ $counts['all'] }}</span></a>
</div>

<div class="card">
  @if ($items->isEmpty())
    <div class="empty">
      <div style="font-family:var(--font-display);font-weight:700;font-size:16px;color:var(--ink);margin-bottom:4px">Nothing here.</div>
      <div>No quotes in this tab.</div>
    </div>
  @else
    <table class="q">
      <thead>
        <tr>
          <th>Number</th>
          <th>Request</th>
          <th>Client</th>
          <th>Total</th>
          <th>Valid until</th>
          <th>Status</th>
          <th>Updated</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($items as $q)
          @php
            $openUrl = ($q->status === 'draft' && $q->source === 'builder')
                ? route('admin.quotes.edit', $q)
                : route('admin.quotes.show', $q);
          @endphp
          <tr onclick="window.location='{{ $openUrl }}'">
            <td class="qnum">{{ $q->quote_number }}</td>
            <td>{{ $q->serviceRequest?->sr_number ?? '—' }}</td>
            <td>{{ $q->client?->name ?? '—' }}</td>
            <td class="total">{{ $currencyCode }} {{ number_format($q->total, 2) }}</td>
            <td>{{ $q->valid_until?->format('d M Y') ?? '—' }}</td>
            <td><span class="pill p-{{ $q->status }}">{{ ucfirst($q->status) }}</span></td>
            <td style="color:var(--muted)">{{ $q->updated_at->diffForHumans() }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

@endsection