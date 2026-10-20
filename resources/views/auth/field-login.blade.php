@extends('layouts.auth', [
    'title' => 'Field View sign-in',
    'action' => route('field.login.attempt'),
    'tenant' => $tenant,
    'accentHex' => $tenant->primary_color ?? '#2F5BEA',
])

@section('body')
    <h1>Field View</h1>
    <p class="lede">Technicians: sign in with your phone or email.</p>

    @if ($errors->any())
        <div class="err">Email, phone or password is incorrect.</div>
    @endif

    <label class="field">Phone or email
        <input type="text" name="identifier" value="{{ old('identifier') }}"
               inputmode="email" autocomplete="username" autofocus required>
    </label>
    <label class="field">Password <span style="font-weight:400;color:var(--muted)">(optional)</span>
        <input type="password" name="password" autocomplete="current-password">
    </label>

    <button type="submit" class="btn" name="mode" value="password">Sign in</button>

    <p class="alt" style="margin-top:10px">
        <button type="submit" class="btn" name="mode" value="code"
                style="background:transparent;color:var(--ink);border:1px solid var(--line)">
            Send me a code
        </button>
    </p>

    @if (app()->environment('local', 'staging'))
        <div class="demo">
            Demo technician for {{ $tenant->name }}:<br>
            <code>+966500000001</code> &middot; <code>Aiywah#2026</code><br>
            Codes land in the demo inbox.
        </div>
    @endif
@endsection