@extends('layouts.admin')
@section('title', ucfirst($slot) . ' checklist')

@section('content')
@include('admin.partials._page')
<style>
  .sec{font-weight:700;margin:14px 0 6px}
  .chk-field{border:1px solid var(--line);border-radius:10px;padding:12px;margin-bottom:10px;background:#fff}
  .txt{width:100%;padding:8px;border:1px solid var(--line);border-radius:8px}
  .inp,.sel{padding:8px;border:1px solid var(--line);border-radius:8px}
  .sub,.muted{color:var(--muted);font-size:12px}
</style>

<div class="page-head"><div><h1>{{ ucfirst($slot) }} checklist</h1><p>{{ $sr->sr_number }} · {{ $sr->site?->name }}</p></div>
  <a href="{{ route('service-requests.show', $sr) }}" class="btn btn-ghost">Back</a></div>

<form method="POST" action="{{ route('service-requests.depot.checklist.save', [$sr, $slot]) }}" enctype="multipart/form-data" class="card pad" style="max-width:720px">
  @csrf
  @if ($result)<div class="pill {{ $result === 'ok' ? 'pill-ok' : 'pill-amber' }}" style="margin-bottom:8px">{{ $result === 'ok' ? 'All good' : 'Issues found' }}</div>@endif
  @include('technician.jobs._checklist_form', ['editable' => true, 'draftKey' => 'depot-' . $slot . '-' . $sr->id, 'eventUuid' => (string) \Illuminate\Support\Str::uuid()])
  <button class="btn btn-primary" style="margin-top:10px">Save checklist</button>
</form>
@endsection
