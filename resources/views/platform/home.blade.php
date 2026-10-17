@extends('layouts.app')

@section('title', 'AIYWAH FSM — Platform')

@section('content')
    <div class="row">
        <div>
            <h1>Workspaces</h1>
            <p class="lede">All tenants on this platform. Visit a workspace by clicking its subdomain.</p>
        </div>
        <a href="{{ route('tenants.create') }}" class="btn">+ New workspace</a>
    </div>

    <div class="card">
        @forelse ($tenants as $tenant)
            @php
                $host  = parse_url(config('app.url'), PHP_URL_HOST);
                $port  = parse_url(config('app.url'), PHP_URL_PORT);
                $url   = 'http://' . $tenant->slug . '.' . $host . ($port ? ':' . $port : '') . '/dashboard';
            @endphp
            <div class="tenant-row">
                <div>
                    <a href="{{ $url }}">{{ $tenant->name }}</a><br>
                    <small>{{ $url }}</small>
                </div>
                <span class="pill {{ $tenant->status !== 'active' ? 'suspended' : '' }}">{{ $tenant->status }}</span>
            </div>
        @empty
            <p style="color:#5F5D6E">No workspaces yet. Create your first one.</p>
        @endforelse
    </div>
@endsection