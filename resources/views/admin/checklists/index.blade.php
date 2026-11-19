@extends('layouts.admin')
@section('title', 'Checklists')

@section('content')
@include('admin.partials._page')

<div class="page-head">
  <div>
    <h1>Checklists</h1>
    <p>Forms technicians fill in on a job. {{ $limit === null ? 'No limit on your plan.' : 'Your plan allows ' . $limit . ' active checklists.' }}</p>
  </div>
  <a href="{{ route('admin.checklists.create') }}" class="btn btn-primary">New checklist</a>
</div>

<div class="card">
  <table>
    <thead><tr><th>Name</th><th>Published</th><th>Status</th><th>Used by</th><th>Updated</th><th></th></tr></thead>
    <tbody>
    @forelse ($templates as $t)
      @php($pub = $t->versions->firstWhere('status', 'published'))
      <tr>
        <td><a href="{{ route('admin.checklists.show', $t) }}" style="font-weight:600;color:var(--ink)">{{ $t->name }}</a>
          @if ($t->description)<div class="hint">{{ $t->description }}</div>@endif</td>
        <td>{{ $pub ? 'Version ' . $pub->version_no : ($t->versions->firstWhere('status', 'draft') ? 'Draft only' : 'None') }}</td>
        <td><span class="pill {{ $t->status === 'active' ? 'pill-ok' : '' }}">{{ ucfirst($t->status) }}</span></td>
        <td>{{ count($categories[$t->id] ?? []) }} categories, {{ count($assets[$t->id] ?? []) }} assets</td>
        <td>{{ $t->updated_at?->format('j M Y') }}</td>
        <td style="text-align:right;white-space:nowrap">
          <form method="POST" action="{{ route('admin.checklists.duplicate', $t) }}" style="display:inline">@csrf<button class="btn btn-ghost">Duplicate</button></form>
          @if ($t->status === 'active')
            <form method="POST" action="{{ route('admin.checklists.archive', $t) }}" style="display:inline">@csrf<button class="btn btn-ghost">Archive</button></form>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="6" style="text-align:center;padding:50px;color:var(--muted)">No checklists yet.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
