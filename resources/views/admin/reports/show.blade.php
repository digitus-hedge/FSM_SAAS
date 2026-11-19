@extends('layouts.admin')
@section('title', $title)

@section('content')
@include('admin.partials._page')

<div class="page-head">
  <div><h1>{{ $title }}</h1><p>{{ $month }}@if ($monthToDate) · Month to date, {{ now()->format('j M Y') }}.@endif</p></div>
  <div style="display:flex;gap:8px">
    <a href="{{ route('admin.reports.index') }}" class="btn btn-ghost">Back</a>
    @if ($mayExport)
      <a href="{{ route('admin.reports.pdf', [$report, 'month' => $month]) }}" class="btn btn-ghost">PDF</a>
      <a href="{{ route('admin.reports.excel', [$report, 'month' => $month]) }}" class="btn btn-ghost">Excel</a>
    @endif
  </div>
</div>

<div class="card">
  <table>
    <thead><tr>@foreach ($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($rows as $row)
      <tr>@foreach ($row as $cell)<td>{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>@endforeach</tr>
    @empty
      <tr><td colspan="{{ count($headings) }}" style="text-align:center;padding:40px;color:var(--muted)">No data yet.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
