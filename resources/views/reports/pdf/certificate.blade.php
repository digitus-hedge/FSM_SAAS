@extends('reports.pdf.layout')
@section('body')
<table>
  <tr><th style="width:30%">Request</th><td>{{ $sr->sr_number }}</td></tr>
  <tr><th>Client</th><td>{{ $sr->client?->name }}</td></tr>
  <tr><th>Site</th><td>{{ $sr->service_mode === 'depot' && $sr->home_site_id ? (\App\Models\ProjectSite::withoutGlobalScopes()->find($sr->home_site_id)?->name) : $sr->site?->name }}</td></tr>
  <tr><th>Service</th><td>{{ $sr->category?->name }}</td></tr>
  <tr><th>Work</th><td>{{ $sr->title }}</td></tr>
  <tr><th>Technician</th><td>{{ $sr->assignedTech?->name }}</td></tr>
  <tr><th>Completed</th><td>{{ $sr->completed_at?->format('j M Y') }}</td></tr>
  <tr><th>Checklist</th><td>{{ $sr->checklist_result ? ($sr->checklist_result === 'ok' ? 'All good' : 'Issues found') : 'None' }}</td></tr>
  <tr><th>Assets</th><td>
    @if ($assets->isNotEmpty())
      @foreach ($assets as $a){{ $a->asset?->asset_code }} {{ $a->asset?->name }} ({{ str_replace('_', ' ', $a->inspection_status) }})@if (! $loop->last), @endif @endforeach
    @else {{ $sr->primaryAsset?->name ?? '—' }} @endif
  </td></tr>
  <tr><th>Customer signature</th><td>{{ $signed ? 'Signed' . ($signer ? ' by ' . $signer : '') : 'Not signed' }}</td></tr>
</table>
@endsection
