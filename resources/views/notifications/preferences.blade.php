@extends('layouts.admin')
@section('title', 'Notification settings')

@section('content')
@include('admin.partials._page')

<div class="page-head"><div><h1>Notification settings</h1>
  <p>Choose how each message reaches you. Messages marked "always on" cannot be turned off or delayed.</p></div></div>

<form method="POST" action="{{ route('notifications.preferences.update') }}" class="card pad">
  @csrf
  <div class="field"><label><input type="hidden" name="daily_summary_on" value="0"><input type="checkbox" name="daily_summary_on" value="1" @checked($user->daily_summary_on)> Send me the daily summary</label></div>
  <div class="field"><label>Daily digest time</label><input type="time" name="digest_daily_time" class="inp" value="{{ substr((string) ($user->digest_daily_time ?? '08:00:00'), 0, 5) }}"></div>

  <table>
    <thead><tr><th>Message</th><th>Email</th><th>In-app</th><th>Browser</th></tr></thead>
    <tbody>
    @foreach ($events as $e)
      <tr>
        <td>{{ $e['key'] }} @if ($e['critical'])<span class="pill pill-amber">Always on</span>@endif</td>
        @foreach (['email', 'in_app', 'push'] as $ch)
          <td>
            <select name="prefs[{{ $e['key'] }}][{{ $ch }}]" class="sel">
              <option value="instant" @selected($e['choice'][$ch] === 'instant')>At once</option>
              @if ($ch === 'email' && ! $e['critical'])
                <option value="hourly_digest" @selected($e['choice'][$ch] === 'hourly_digest')>Hourly digest</option>
                <option value="daily_digest" @selected($e['choice'][$ch] === 'daily_digest')>Daily digest</option>
              @endif
              <option value="off" @selected($e['choice'][$ch] === 'off')>Off</option>
            </select>
          </td>
        @endforeach
      </tr>
    @endforeach
    </tbody>
  </table>
  <button class="btn btn-primary" style="margin-top:12px">Save</button>
</form>

<div class="card pad" style="margin-top:14px">
  <strong>Browser notifications</strong>
  <p class="hint">Shows a message on your screen while the app is open. Nothing is sent to a third party.</p>
  <button type="button" class="btn btn-ghost" id="allow-browser">Allow browser notifications</button>
  <span id="allow-state" class="hint"></span>
</div>
<script>
(function () {
  var b = document.getElementById('allow-browser'), s = document.getElementById('allow-state');
  if (!('Notification' in window)) { b.style.display = 'none'; s.textContent = 'This browser cannot show notifications.'; return; }
  function show() { s.textContent = Notification.permission === 'granted' ? 'Allowed.' : (Notification.permission === 'denied' ? 'Blocked in your browser settings.' : ''); }
  b.addEventListener('click', function () { Notification.requestPermission().then(show); }); show();
})();
</script>
@endsection
