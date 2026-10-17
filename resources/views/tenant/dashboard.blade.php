@extends('layouts.admin')

@section('title', $tenant->name)

@section('content')
<h1 style="font-family:'Figtree';font-size:30px;font-weight:700">{{ $tenant->name }}</h1>
<p style="color:var(--muted);margin:6px 0 24px">You're on the <b>{{ $tenant->slug }}</b> workspace.</p>

<div style="background:#fff;border:1px solid var(--line);border-radius:18px;padding:24px;max-width:520px">
    <h2 style="font-family:'Figtree';font-size:16px;margin-bottom:14px">Workspace details</h2>
    <table style="width:100%;font-size:14px">
        <tr><td style="padding:6px 0;color:var(--muted)">Tenant ID</td><td><b>{{ $tenant->id }}</b></td></tr>
        <tr><td style="padding:6px 0;color:var(--muted)">Slug</td><td><b>{{ $tenant->slug }}</b></td></tr>
        <tr><td style="padding:6px 0;color:var(--muted)">Status</td><td><b>{{ $tenant->status }}</b></td></tr>
        <tr><td style="padding:6px 0;color:var(--muted)">Primary colour</td>
            <td><span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:{{ $tenant->primary_color }};vertical-align:middle;margin-right:6px"></span><b>{{ $tenant->primary_color }}</b></td></tr>
    </table>
</div>
@endsection