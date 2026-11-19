@extends('layouts.admin')
@section('title', 'Daily summaries')

@section('content')
@include('admin.partials._page')

<div class="page-head"><div><h1>Daily summaries</h1>
  <p>One message each morning for each role, by email and in-app. Nothing is sent on a quiet day.@unless ($canEdit) You can look at the settings but not change them.@endunless</p></div></div>

@foreach ($roles as $role => $cfg)
  <form method="POST" action="{{ route('admin.summary-settings.update', $role) }}" class="card pad" style="margin-bottom:12px">
    @csrf
    <h3 style="margin-bottom:8px">{{ ucwords(str_replace('_', ' ', $role)) }}</h3>
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
      <div class="field"><label>Send at (company time)</label><input type="time" name="send_time" class="inp" value="{{ $cfg['send_time'] }}" @disabled(!$canEdit)></div>
      <div class="field"><label>Days</label>
        @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $n => $d)
          <label style="display:inline-block;margin-right:8px"><input type="checkbox" name="days[]" value="{{ $n }}" @checked(in_array($n, $cfg['days'])) @disabled(!$canEdit)> {{ $d }}</label>
        @endforeach
      </div>
      <div class="field"><label>Content</label>
        @foreach ($cfg['blocks_all'] as $key => $label)
          <label style="display:block"><input type="checkbox" name="blocks[]" value="{{ $key }}" @checked(in_array($key, $cfg['blocks'])) @disabled(!$canEdit)> {{ $label }}</label>
        @endforeach
      </div>
      <div class="field"><label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($cfg['active']) @disabled(!$canEdit)> Active</label></div>
    </div>
    @if ($canEdit)<button class="btn btn-primary">Save</button>@endif
  </form>
@endforeach
@endsection
