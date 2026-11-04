@extends('layouts.technician')
@section('title', 'All my jobs')
@section('content')

<div class="tabs" role="tablist">
    <a href="{{ route('technician.jobs.all', ['tab' => 'active', 'q' => $search]) }}" aria-selected="{{ $tab === 'active' ? 'true' : 'false' }}">Active</a>
    <a href="{{ route('technician.jobs.all', ['tab' => 'review', 'q' => $search]) }}" aria-selected="{{ $tab === 'review' ? 'true' : 'false' }}">Review</a>
    <a href="{{ route('technician.jobs.all', ['tab' => 'done', 'q' => $search]) }}" aria-selected="{{ $tab === 'done' ? 'true' : 'false' }}">Done</a>
</div>

<form method="GET" style="display:flex;gap:8px;margin-bottom:16px">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search job number, title..." class="inp" style="flex:1">
    <button type="submit" class="btn sm">Search</button>
</form>

@if ($jobs->isEmpty())
    <div class="card center" style="padding:40px 20px;margin-top:8px">
        <div style="width:54px;height:54px;border-radius:999px;background:var(--tint);color:var(--accent);display:grid;place-items:center;margin:0 auto 14px">
            @include('technician._icon', ['name'=>'list','size'=>24])
        </div>
        <h3 style="margin-bottom:6px">Nothing here</h3>
        <p class="sub" style="margin:0">
            @if ($search)
                No jobs match &ldquo;{{ $search }}&rdquo; in this tab.
            @else
                No {{ $tab }} jobs right now.
            @endif
        </p>
    </div>
@else
    <div class="muted" style="font-size:13px;margin-bottom:10px">{{ $jobs->total() }} job{{ $jobs->total() === 1 ? '' : 's' }}</div>

    @foreach ($jobs as $job)
        @include('technician.jobs._card', ['job' => $job])
    @endforeach

    @if ($jobs->hasPages())
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:6px">
        @if ($jobs->onFirstPage())
            <span class="btn sm" style="opacity:.4;pointer-events:none">Prev</span>
        @else
            <a href="{{ $jobs->previousPageUrl() }}" class="btn sm">Prev</a>
        @endif

        <span class="muted" style="font-size:13px">Page {{ $jobs->currentPage() }} of {{ $jobs->lastPage() }}</span>

        @if ($jobs->hasMorePages())
            <a href="{{ $jobs->nextPageUrl() }}" class="btn sm">Next</a>
        @else
            <span class="btn sm" style="opacity:.4;pointer-events:none">Next</span>
        @endif
    </div>
    @endif
@endif
@endsection