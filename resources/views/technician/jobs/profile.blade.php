@extends('layouts.technician')
@section('title', 'Profile')
@section('content')
@php
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

<div class="card center" style="padding:28px 20px;margin-bottom:16px">
    <div style="width:68px;height:68px;border-radius:999px;background:var(--ink);color:var(--bg);display:grid;place-items:center;font-weight:700;font-size:22px;font-family:var(--font-display);margin:0 auto 14px">
        {{ strtoupper($initials ?: 'U') }}
    </div>
    <h2 style="font-size:19px">{{ $user->name }}</h2>
    @if ($user->role?->name)
        <span class="chip sm" style="--c:var(--accent);margin-top:10px">{{ $user->role->name }}</span>
    @endif
</div>

<div class="list">
    <div class="it">
        @include('technician._icon', ['name'=>'mail','size'=>19])
        <div class="grow">
            <div class="s">Email</div>
            <div class="t">{{ $user->email }}</div>
        </div>
    </div>
    @if ($user->phone)
    <div class="it">
        @include('technician._icon', ['name'=>'phone','size'=>19])
        <div class="grow">
            <div class="s">Phone</div>
            <div class="t">{{ $user->phone }}</div>
        </div>
    </div>
    @endif
</div>

<form method="POST" action="{{ route('logout') }}" style="margin-top:16px">
    @csrf
    <button type="submit" class="btn danger block">Sign out</button>
</form>
@endsection