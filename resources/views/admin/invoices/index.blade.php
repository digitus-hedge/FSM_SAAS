@extends('layouts.admin')
@section('title','Invoices')
@section('content')
<style>
.page-h1{font-family:'Figtree';font-size:28px;font-weight:800;margin-bottom:6px;letter-spacing:-.4px}
.page-sub{color:var(--muted);font-size:14px;margin-bottom:22px}
.tab-strip{display:flex;gap:0;border-bottom:1px solid var(--line);margin-bottom:22px;overflow-x:auto}
.tab-strip a{padding:12px 20px;color:var(--muted);text-decoration:none;font-weight:600;border-bottom:2px solid transparent;position:relative;top:1px;white-space:nowrap;font-size:13.5px}
.tab-strip a.active{color:var(--ink);border-bottom-color:var(--ink)}
.tab-strip a .n{color:var(--muted);font-weight:400;margin-left:4px;font-size:12px}
.tab-strip a.active .n{color:var(--ink)}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
table.inv{width:100%;border-collapse:collapse;font-size:13.5px}
table.inv th{text-align:left;padding:14px 16px;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line)}
table.inv td{padding:18px 16px;border-bottom:1px solid #F2F1F6;vertical-align:middle;font-variant-numeric:tabular-nums}
table.inv tr:last-child td{border-bottom:0}
table.inv tbody tr{cursor:pointer;transition:background .1s}
table.inv tbody tr:hover{background:#F8F7FB}
.inv-num{font-family:'JetBrains Mono',Consolas,monospace;font-weight:800;font-size:12.5px}
.total{font-family:'Figtree';font-weight:700;white-space:nowrap}
.pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.p-draft{background:#F0EFF5;color:#5A5869}
.p-pending_approval{background:#EDE9FE;color:#5B3BB8}
.p-approved{background:#E3EBF4;color:#2B5A8C}
.p-sent,.p-partially_paid{background:#FFF4CC;color:#8A5A00}
.p-paid{background:#DDF7F1;color:#00866F}
.p-overdue{background:#FDECE7;color:#C2381B}
.p-written_off,.p-void{background:#F0EFF5;color:#777;text-decoration:line-through}
.p-sr-awaiting{background:#FFF4CC;color:#8A5A00}
.p-sr-completed{background:#DDF7F1;color:#00866F}
.empty{padding:60px 20px;text-align:center;color:var(--muted)}
.btn-y{background:#FFC61A;color:#1B1A24;font-weight:700;font-size:12.5px;padding:7px 14px;border-radius:999px;border:0;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block}
.btn-y:hover{background:#F0B700}
</style>

<h1 class="page-h1">Invoices</h1>
<p class="page-sub">Customer copies. The legal tax invoice is issued from your own accounting suite where the law requires it.</p>

<div class="tab-strip">
  <a href="{{ route('admin.invoices.index',['tab'=>'toinvoice']) }}" class="{{ $tab==='toinvoice'?'active':'' }}">To invoice      <span class="n">{{ $counts['toinvoice'] }}</span></a>
  <a href="{{ route('admin.invoices.index',['tab'=>'draft']) }}"     class="{{ $tab==='draft'?'active':'' }}">Draft            <span class="n">{{ $counts['draft'] }}</span></a>
  <a href="{{ route('admin.invoices.index',['tab'=>'pending']) }}"   class="{{ $tab==='pending'?'active':'' }}">Pending approval <span class="n">{{ $counts['pending'] }}</span></a>
  <a href="{{ route('admin.invoices.index',['tab'=>'approved']) }}"  class="{{ $tab==='approved'?'active':'' }}">Approved         <span class="n">{{ $counts['approved'] }}</span></a>
  <a href="{{ route('admin.invoices.index',['tab'=>'sent']) }}"      class="{{ $tab==='sent'?'active':'' }}">Sent             <span class="n">{{ $counts['sent'] }}</span></a>
  <a href="{{ route('admin.invoices.index',['tab'=>'overdue']) }}"   class="{{ $tab==='overdue'?'active':'' }}">Overdue          <span class="n">{{ $counts['overdue'] }}</span></a>
  <a href="{{ route('admin.invoices.index',['tab'=>'paid']) }}"      class="{{ $tab==='paid'?'active':'' }}">Paid             <span class="n">{{ $counts['paid'] }}</span></a>
  <a href="{{ route('admin.invoices.index',['tab'=>'all']) }}"       class="{{ $tab==='all'?'active':'' }}">All              <span class="n">{{ $counts['all'] }}</span></a>
</div>

<div class="card">
  @if ($items->isEmpty())
    <div class="empty">
      <div style="font-family:'Figtree';font-weight:700;font-size:16px;color:var(--ink);margin-bottom:4px">Nothing here.</div>
      <div>{{ $tab==='toinvoice' ? 'Nothing needs an invoice right now.' : 'No invoices in this tab.' }}</div>
    </div>
  @elseif ($tab==='toinvoice')
    <table class="inv">
      <thead><tr><th>Request</th><th>Client</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @foreach ($items as $r)
          <tr>
            <td><b class="inv-num">{{ $r->sr_number }}</b><br><span style="color:var(--muted);font-size:12.5px">{{ $r->title ?? '—' }}</span></td>
            <td>{{ $r->client?->name ?? '—' }}</td>
            <td><span class="pill p-sr-{{ strtolower(str_replace(' ','-',$r->status)) }}">{{ $r->status }}</span></td>
            <td style="text-align:right"><a href="{{ route('admin.invoices.build', $r) }}" class="btn-y">Make invoice</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <table class="inv">
      <thead>
        <tr><th>Number</th><th>Request</th><th>Client</th><th>Total</th><th>Balance</th><th>Due date</th><th>Status</th></tr>
      </thead>
      <tbody>
        @foreach ($items as $i)
          <tr onclick="window.location='{{ route('admin.invoices.show', $i) }}'">
            <td class="inv-num">{{ $i->invoice_number }}</td>
            <td>{{ $i->serviceRequest?->sr_number ?? '—' }}</td>
            <td>{{ $i->client?->name ?? '—' }}</td>
            <td class="total">AED {{ number_format($i->total, 2) }}</td>
            <td class="total" style="color:{{ $i->balance > 0 ? 'var(--amber)' : 'var(--muted)' }}">
              {{ $i->balance > 0 ? 'AED ' . number_format($i->balance, 2) : '—' }}
            </td>
            <td>{{ $i->due_date?->format('d M Y') ?? '—' }}</td>
            <td><span class="pill p-{{ $i->status }}">{{ ucfirst(str_replace('_',' ',$i->status)) }}</span></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection