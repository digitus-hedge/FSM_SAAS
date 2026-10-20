<!doctype html>
<title>Platform admin</title>
<style>body{font:14px/1.5 system-ui;padding:40px;max-width:720px;margin:0 auto}</style>
<h1>Platform admin</h1>
<p>Signed in as <strong>{{ auth('platform')->user()->email }}</strong>.</p>
<p>Role: <code>{{ auth('platform')->user()->platform_role }}</code>.</p>
<form method="POST" action="{{ route('platform.logout') }}">
    @csrf
    <button type="submit">Sign out</button>
</form>
<h2>Tenants ({{ $tenants->count() }})</h2>
<ul>
    @forelse ($tenants as $t)
        <li>{{ $t->name }} ({{ $t->slug }}) — {{ $t->status }}</li>
    @empty
        <li>No tenants yet.</li>
    @endforelse
</ul>