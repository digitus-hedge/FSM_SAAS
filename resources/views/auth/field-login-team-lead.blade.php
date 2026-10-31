@extends('layouts.field-auth', ['title' => 'Team Lead sign-in', 'action' => route('field.login.attempt'), 'tenant' => $tenant])
@section('body')
<div class="center" style="padding:44px 6px 10px">
    <div class="mk" style="width:60px;height:60px;border-radius:18px;background:var(--ink);color:var(--bg);display:grid;place-items:center;font:700 22px Figtree;margin:0 auto 16px">{{ strtoupper(substr($tenant->name ?? 'AF', 0, 2)) }}</div>
    <h1 style="font-size:28px">{{ $tenant->name }}</h1>
    <p class="muted" style="margin:6px 0 24px">Team Lead sign-in. This phone stays signed in for 30 days.</p>
</div>
@if ($errors->any())<div class="err" style="text-align:center;margin-bottom:14px">{{ $errors->first() }}</div>@endif
<div class="field">
    <label for="identifier">Phone or email</label>
    <input class="inp" type="text" id="identifier" name="identifier" value="{{ old('identifier') }}" autocomplete="username" autofocus required>
</div>
<div class="field">
    <label for="password">Password</label>
    <input class="inp" type="password" id="password" name="password" autocomplete="current-password" required>
</div>
<button type="submit" class="btn primary block">Sign in</button>
<p class="legal">Having trouble signing in? Contact your office admin.</p>
@endsection