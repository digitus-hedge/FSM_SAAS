@extends('layouts.technician')
@section('title', 'Notifications')
@section('content')

<div class="card center" style="padding:48px 20px;margin-top:8px">
    <div style="width:56px;height:56px;border-radius:999px;background:var(--tint);color:var(--accent);display:grid;place-items:center;margin:0 auto 16px">
        @include('technician._icon', ['name'=>'bell','size'=>26])
    </div>
    <h3 style="margin-bottom:6px">No notifications yet</h3>
    <p class="sub" style="margin:0">This isn't wired up yet &mdash; once it is, job assignments and updates will show up here.</p>
</div>
@endsection