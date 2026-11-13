@extends('layouts.admin')
@section('title', 'Notifications')

@section('content')
<style>
  .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px;gap:12px;flex-wrap:wrap}
  .page-head h1{font-family:var(--font-display);font-size:26px;font-weight:700;letter-spacing:-.3px}
  .page-head p{color:var(--muted);font-size:13px;margin-top:3px}
  .btn{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-body);font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);cursor:pointer}
  .btn:hover{background:var(--soft)}
  .nlist{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  .nrow{display:flex;gap:14px;align-items:flex-start;padding:16px 20px;border-top:1px solid var(--line);text-decoration:none;color:inherit}
  .nrow:first-child{border-top:0}
  .nrow:hover{background:#FDFCF9}
  .nrow.unread{background:#FFFCF2}
  .ndot{width:9px;height:9px;border-radius:50%;background:var(--amber);margin-top:6px;flex-shrink:0}
  .nrow:not(.unread) .ndot{background:transparent}
  .ntitle{font-weight:600;font-size:14px}
  .nrow:not(.unread) .ntitle{font-weight:500}
  .nbody{color:var(--muted);font-size:13px;margin-top:2px}
  .ntime{color:var(--subtle);font-size:11px;margin-top:4px}
  .nempty{padding:60px 20px;text-align:center;color:var(--muted);font-size:13px}
  .pager{margin-top:18px}
</style>

<div class="page-head">
  <div>
    <h1>Notifications</h1>
    <p>{{ $unread ? $unread . ' unread' : 'You are all caught up.' }}</p>
  </div>
  @if ($unread)
    <form method="POST" action="{{ route('notifications.read-all') }}">
      @csrf
      <button class="btn" type="submit">Mark all as read</button>
    </form>
  @endif
</div>

<div class="nlist">
  @forelse ($items as $n)
    <a href="{{ route('notifications.open', $n) }}" class="nrow {{ $n->read_at ? '' : 'unread' }}">
      <span class="ndot"></span>
      <div>
        <div class="ntitle">{{ $n->title }}</div>
        @if ($n->body)<div class="nbody">{{ $n->body }}</div>@endif
        <div class="ntime">{{ $n->created_at->diffForHumans() }}</div>
      </div>
    </a>
  @empty
    <div class="nempty">No notifications yet.</div>
  @endforelse
</div>

@if ($items->hasPages())
  <div class="pager">{{ $items->links() }}</div>
@endif
@endsection
