@extends('layouts.admin')
@section('title', 'Message log')
@section('content')
@php $canRetry = \App\Services\Permissions\PermissionLevel::canChange(auth()->user(), 'notification.manage'); @endphp
<style>
  .ml-in{padding:8px 10px;border:1px solid var(--line);border-radius:8px;font-size:13px}
  .ml-chip{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:700}
  .ml-sent{background:var(--green-soft);color:var(--green)} .ml-failed{background:var(--red-soft);color:var(--red)} .ml-queued{background:var(--soft);color:var(--muted)}
  .ml-btn{background:var(--yellow);border:0;border-radius:999px;padding:6px 14px;font-weight:600;font-size:12px;cursor:pointer}
  .ml-table th{text-align:left;padding:10px 14px;color:var(--muted);font-weight:600;border-bottom:1px solid var(--line)}
  .ml-table td{padding:10px 14px;border-bottom:1px solid var(--line);vertical-align:top}
</style>

<h1 style="font:700 24px var(--font-display);letter-spacing:-.3px;margin-bottom:6px">Message log</h1>
<p style="color:var(--muted);margin-bottom:18px">Every email the system tried to send for your company. A failed message can be sent again when it can be built again. {{ $failed }} failed.</p>

@if (session('success'))<div style="background:var(--green-soft);color:var(--green);padding:10px 14px;border-radius:10px;margin-bottom:12px">{{ session('success') }}</div>@endif
@if (session('error'))<div style="background:var(--red-soft);color:var(--red);padding:10px 14px;border-radius:10px;margin-bottom:12px">{{ session('error') }}</div>@endif

<form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
  <select name="status" class="ml-in"><option value="">All statuses</option>
    @foreach (\App\Models\MessageLog::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
  </select>
  <select name="channel" class="ml-in"><option value="">All channels</option>
    @foreach (\App\Models\MessageLog::CHANNELS as $k => $v)<option value="{{ $k }}" @selected(request('channel') === $k)>{{ $v }}</option>@endforeach
  </select>
  <select name="event" class="ml-in"><option value="">All events</option>
    @foreach ($events as $e)<option value="{{ $e }}" @selected(request('event') === $e)>{{ $e }}</option>@endforeach
  </select>
  <input type="text" name="q" value="{{ request('q') }}" placeholder="Recipient" class="ml-in">
  <input type="date" name="from" value="{{ request('from') }}" class="ml-in">
  <input type="date" name="to" value="{{ request('to') }}" class="ml-in">
  <button type="submit" style="background:var(--yellow);border:0;padding:9px 18px;border-radius:999px;font-weight:600;cursor:pointer">Filter</button>
</form>

<div style="background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden">
  <table class="ml-table" style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr><th>When</th><th>Event</th><th>Recipient</th><th>Channel</th><th>Status</th><th>Tries</th><th>Error</th><th></th></tr></thead>
    <tbody>
      @forelse ($logs as $l)
        <tr>
          <td>{{ $l->created_at->copy()->setTimezone($tz)->format('d M Y H:i') }}</td>
          <td>{{ $l->event_key }}</td>
          <td>{{ $l->recipient_address }}</td>
          <td>{{ \App\Models\MessageLog::CHANNELS[$l->channel] ?? $l->channel }}</td>
          <td><span class="ml-chip ml-{{ $l->status }}">{{ \App\Models\MessageLog::STATUSES[$l->status] ?? $l->status }}</span></td>
          <td>{{ $l->attempts }}</td>
          <td style="max-width:280px">{{ $l->failure_reason }}</td>
          <td>
            @if ($l->isFailed() && $canRetry)
              @if ($logger->isRetryable($l))
                <form method="POST" action="{{ route('admin.message-log.retry', $l) }}">@csrf<button class="ml-btn" type="submit">Retry</button></form>
              @else
                <span style="color:var(--subtle);font-size:12px">Send a new one from its screen</span>
              @endif
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:40px">No messages match.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div style="margin-top:14px">{{ $logs->links() }}</div>
@endsection
