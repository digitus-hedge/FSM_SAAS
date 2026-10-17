@extends('layouts.admin')

@section('title', 'Members')

@section('content')
<style>
.row-top{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:24px}
h1{font-family:'Figtree';font-size:30px;font-weight:700;letter-spacing:-.6px}
.lede{color:var(--muted);margin-top:4px}
.btn{background:var(--yellow);color:var(--ink);font-weight:700;font-size:14px;padding:11px 20px;border-radius:999px;border:0;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.panel{background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden}
.tbl{width:100%;border-collapse:collapse}
.tbl thead th{text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);padding:12px 22px;background:var(--soft)}
.tbl tbody td{padding:14px 22px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:middle}
.tbl tbody tr:last-child td{border-bottom:0}
.who{display:flex;align-items:center;gap:12px}
.who .av{width:34px;height:34px;border-radius:50%;background:var(--yellow-soft);color:var(--amber);font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center}
.who b{display:block;font-weight:700}
.who small{color:var(--muted);font-size:12px}
.chip{display:inline-block;padding:3px 10px;border-radius:999px;background:var(--soft);color:var(--ink);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.chip.company_admin{background:var(--yellow-soft);color:var(--amber)}
.pill{display:inline-flex;gap:5px;align-items:center;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase}
.pill .d{width:6px;height:6px;border-radius:50%}
.pill.active{background:var(--green-soft);color:var(--green)} .pill.active .d{background:var(--green)}
.pill.suspended{background:var(--red-soft);color:var(--red)} .pill.suspended .d{background:var(--red)}
.linkbtn{background:none;border:0;font:inherit;font-size:12px;color:var(--muted);cursor:pointer;text-decoration:underline}
.linkbtn.danger{color:var(--red)}
</style>

<div class="row-top">
    <div>
        <h1>Members</h1>
        <p class="lede">People with access to <b>{{ $tenant->name }}</b>.</p>
    </div>
    <a href="{{ route('tenant.members.create') }}" class="btn">+ Add member</a>
</div>

<div class="panel">
    <table class="tbl">
        <thead>
            <tr><th>Member</th><th>Role</th><th>Status</th><th>Joined</th><th style="text-align:right">Actions</th></tr>
        </thead>
        <tbody>
            @foreach ($members as $member)
                <tr>
                    <td>
                        <div class="who">
                            <span class="av">{{ strtoupper(substr($member->name, 0, 2)) }}</span>
                            <div>
                                <b>{{ $member->name }}</b>
                                <small>{{ $member->email }}</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="chip {{ $member->pivot->role }}">{{ str_replace('_', ' ', $member->pivot->role) }}</span></td>
                    <td><span class="pill {{ $member->pivot->status }}"><span class="d"></span>{{ $member->pivot->status }}</span></td>
                    <td style="color:var(--muted)">{{ \Carbon\Carbon::parse($member->pivot->joined_at)->format('d M Y') }}</td>
                    <td style="text-align:right">
                        @if ($member->pivot->status === 'active')
                            <form method="POST" action="{{ route('tenant.members.suspend', $member) }}" style="display:inline" onsubmit="return confirm('Suspend {{ $member->name }}?')">
                                @csrf
                                <button type="submit" class="linkbtn danger">Suspend</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('tenant.members.reactivate', $member) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="linkbtn">Reactivate</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection