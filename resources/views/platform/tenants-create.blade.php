@extends('layouts.app')

@section('title', 'New workspace')

@section('content')
    <h1>New workspace</h1>
    <p class="lede">A workspace is one company that uses the platform. Each gets its own subdomain, users and data.</p>

    <form method="POST" action="{{ route('tenants.store') }}" class="card" style="max-width:520px">
        @csrf

        <div class="field">
            <label>Company name</label>
            <input name="name" value="{{ old('name') }}" placeholder="Acme Industries LLC" autofocus>
            @error('name') <span class="err">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label>Subdomain</label>
            <input name="slug" value="{{ old('slug') }}" placeholder="acme" pattern="[a-z0-9\-]+">
            <small style="color:#8B89A0;font-weight:400">Will be reachable at <b>{{ $slug ?? '[slug]' }}.{{ parse_url(config('app.url'), PHP_URL_HOST) }}</b></small>
            @error('slug') <span class="err">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label>Contact email <span style="color:#8B89A0;font-weight:400;font-size:12px">optional</span></label>
            <input type="email" name="contact_email" value="{{ old('contact_email') }}" placeholder="ops@acme.com">
            @error('contact_email') <span class="err">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label>Primary colour <span style="color:#8B89A0;font-weight:400;font-size:12px">optional</span></label>
            <input type="color" name="primary_color" value="{{ old('primary_color', '#FFC61A') }}" style="height:44px;width:80px;padding:4px">
            @error('primary_color') <span class="err">{{ $message }}</span> @enderror
        </div>

        <div style="display:flex;gap:12px;margin-top:12px">
            <button type="submit" class="btn">Create workspace</button>
            <a href="{{ route('home') }}" class="btn ghost">Cancel</a>
        </div>
    </form>
@endsection