@extends('layouts.technician')
@section('title', 'All my jobs')
@section('content')
<div class="tabs">
    <a href="{{ route('technician.jobs.all', ['tab' => 'active']) }}" class="{{ $tab === 'active' ? 'active' : '' }}">Active</a>
    <a href="{{ route('technician.jobs.all', ['tab' => 'review']) }}" class="{{ $tab === 'review' ? 'active' : '' }}">Review</a>
    <a href="{{ route('technician.jobs.all', ['tab' => 'done']) }}" class="{{ $tab === 'done' ? 'active' : '' }}">Done</a>
</div>
<form method="GET" style="margin-bottom:14px">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="q" value="{{ $search }}" placeholder="Search job number, title…" style="width:100%;padding:11px 12px;border-radius:10px;border:1px solid var(--line);font:inherit">
</form>
@if ($jobs->isEmpty())
    <div class="empty">Nothing here.</div>
@else
    @foreach ($jobs as $job)
        @include('technician.jobs._card', ['job' => $job])
    @endforeach
    <div style="margin-top:10px">{{ $jobs->links() }}</div>
@endif
@endsection