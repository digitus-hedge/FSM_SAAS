@extends('reports.pdf.layout')
@section('body')
<div class="sub">{{ $month }}@if ($monthToDate) · Month to date.@endif</div>
<table>
  <thead><tr>@foreach ($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
  <tbody>
  @forelse ($rows as $row)
    <tr>@foreach ($row as $cell)<td>{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>@endforeach</tr>
  @empty
    <tr><td colspan="{{ count($headings) }}">No data yet.</td></tr>
  @endforelse
  </tbody>
</table>
@endsection
