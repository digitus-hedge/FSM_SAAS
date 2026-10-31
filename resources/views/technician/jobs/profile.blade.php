@extends('layouts.technician')
@section('title', 'Profile')
@section('content')
<div class="card">
    <div style="font-weight:800;font-size:16px">{{ $user->name }}</div>
    <div style="color:var(--muted);font-size:13px;margin-top:4px">{{ $user->role?->name }}</div>
    <div style="margin-top:12px;font-size:13px">{{ $user->email }}</div>
    @if ($user->phone)<div style="font-size:13px">{{ $user->phone }}</div>@endif
</div>
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="btn danger block">Sign out</button>
</form>
@endsection