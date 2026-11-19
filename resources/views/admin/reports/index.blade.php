@extends('layouts.admin')
@section('title', 'Reports')

@section('content')
@include('admin.partials._page')

<div class="page-head"><div><h1>Reports</h1><p>Choose a month. Each report opens on screen, and downloads as PDF or Excel.</p></div></div>

<form method="GET" id="period" style="margin-bottom:14px"><input type="month" name="month" class="inp" value="{{ $month }}" max="{{ $month }}"></form>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px">
  @foreach ($reports as $key => $label)
    <div class="card pad">
      <h3 style="margin-bottom:10px">{{ $label }}</h3>
      <a class="btn btn-primary" href="{{ route('admin.reports.show', $key) }}" onclick="this.href='{{ route('admin.reports.show', $key) }}?month=' + document.querySelector('[name=month]').value">Open</a>
      @if ($mayExport)
        <a class="btn btn-ghost" href="{{ route('admin.reports.pdf', $key) }}" onclick="this.href='{{ route('admin.reports.pdf', $key) }}?month=' + document.querySelector('[name=month]').value">PDF</a>
        <a class="btn btn-ghost" href="{{ route('admin.reports.excel', $key) }}" onclick="this.href='{{ route('admin.reports.excel', $key) }}?month=' + document.querySelector('[name=month]').value">Excel</a>
      @endif
    </div>
  @endforeach
  <div class="card pad"><h3>Completion certificate</h3><p class="hint">Open a completed request and choose "Completion certificate".</p></div>
</div>

<h3 style="margin:22px 0 8px">Recent downloads</h3>
<div class="card">
  <table><thead><tr><th>When</th><th>Who</th><th>Report</th></tr></thead><tbody>
  @forelse ($recent as $r)
    <tr><td>{{ $r->created_at->format('j M Y H:i') }}</td><td>{{ $r->actor_name }}</td><td>{{ ($r->new_values['report'] ?? '') . ' (' . ($r->new_values['format'] ?? '') . ')' }}</td></tr>
  @empty
    <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--muted)">Nothing downloaded yet.</td></tr>
  @endforelse
  </tbody></table>
</div>
@endsection
