@extends('layouts.admin')

@section('title', 'Workspaces')

@section('content')
<style>
.row-top{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:28px;gap:16px}
h1{font-family:'Figtree';font-size:32px;font-weight:700;letter-spacing:-.6px}
.lede{color:var(--muted);margin-top:6px;font-size:14.5px}
.btn{background:var(--yellow);color:var(--ink);font-weight:700;font-size:14px;padding:11px 20px;border-radius:999px;border:0;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px}
.kpi{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px 20px}
.kpi .lbl{font-size:12px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
.kpi .num{font-family:'Figtree';font-size:30px;font-weight:700;letter-spacing:-.5px;line-height:1}
.panel{background:#fff;border:1px solid var(--line);border-radius:18px;padding:0;overflow:hidden}
.panel-head{padding:20px 24px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--line)}
.panel-head h2{font-family:'Figtree';font-size:16px;font-weight:700}
.tbl{width:100%;border-collapse:collapse}
.tbl thead th{text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);padding:12px 24px;background:var(--soft)}
.tbl tbody td{padding:16px 24px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:middle}
.tbl tbody tr:last-child td{border-bottom:0}
.tenant-name{display:flex;align-items:center;gap:12px}
.swatch{width:32px;height:32px;border-radius:9px;font-weight:700;font-size:12px;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.tenant-name b{font-weight:700;font-size:14px}
.tenant-name small{display:block;color:var(--muted);font-size:12px;margin-top:1px}
.slug{font-family:'JetBrains Mono',Consolas,monospace;background:var(--soft);padding:3px 8px;border-radius:5px;font-size:12px;color:var(--amber);font-weight:600}
.pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.pill .d{width:6px;height:6px;border-radius:50%}
.pill.active{background:var(--green-soft);color:var(--green)} .pill.active .d{background:var(--green)}
.pill.suspended{background:var(--red-soft);color:var(--red)} .pill.suspended .d{background:var(--red)}
.actions-cell{text-align:right}
.linkbtn{background:none;border:0;font:inherit;font-size:12px;color:var(--muted);cursor:pointer;text-decoration:underline;padding:4px 8px}
.linkbtn:hover{color:var(--ink)}
.linkbtn.danger{color:var(--red)}
.empty{padding:60px 24px;text-align:center;color:var(--muted)}
.empty h3{font-family:'Figtree';font-size:16px;margin-bottom:6px;color:var(--ink);font-weight:700}
</style>

<div class="row-top">
    <div>
        <h1>Workspaces</h1>
        <p class="lede">Every tenant on this platform. Each one is a company using the FSM product.</p>
    </div>
    <a href="{{ route('admin.tenants.create') }}" class="btn">+ New workspace</a>
</div>

<div class="kpis">
    <div class="kpi"><div class="lbl">Total</div><div class="num">{{ $stats['total'] }}</div></div>
    <div class="kpi"><div class="lbl">Active</div><div class="num" style="color:var(--green)">{{ $stats['active'] }}</div></div>
    <div class="kpi"><div class="lbl">Suspended</div><div class="num" style="color:var(--red)">{{ $stats['suspended'] }}</div></div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>All workspaces</h2>
    </div>

    @if ($tenants->isEmpty())
        <div class="empty">
            <h3>No workspaces yet</h3>
            <p>Create your first workspace to get started.</p>
        </div>
    @else
    <table class="tbl">
        <thead>
            <tr>
                <th>Name</th>
                <th>Subdomain</th>
                <th>Status</th>
                <th>Created</th>
                <th class="actions-cell">Actions</th>
            </tr>
        </thead>
        <tbody>
            @php
                $host = parse_url(config('app.url'), PHP_URL_HOST);
                $port = parse_url(config('app.url'), PHP_URL_PORT);
                $portPart = $port ? ':' . $port : '';
            @endphp
            @foreach ($tenants as $tenant)
                <tr>
                    <td>
                        <div class="tenant-name">
                            <span class="swatch" style="background:{{ $tenant->primary_color }}">
                                {{ strtoupper(substr($tenant->name, 0, 2)) }}
                            </span>
                            <div>
                                <b>{{ $tenant->name }}</b>
                                <small>{{ $tenant->contact_email ?: 'no contact email' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="http://{{ $tenant->slug }}.{{ $host }}{{ $portPart }}/dashboard" target="_blank" class="slug">
                            {{ $tenant->slug }}.{{ $host }}{{ $portPart }}
                        </a>
                    </td>
                    <td>
                        <span class="pill {{ $tenant->status }}"><span class="d"></span>{{ $tenant->status }}</span>
                    </td>
                    <td style="color:var(--muted)">{{ $tenant->created_at->format('d M Y') }}</td>
                    <td class="actions-cell">
                        @if ($tenant->status === 'active')
                            <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" style="display:inline" onsubmit="return confirm('Suspend {{ $tenant->name }}? Users will not be able to sign in.')">
                                @csrf
                                <button type="submit" class="linkbtn danger">Suspend</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.tenants.reactivate', $tenant) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="linkbtn">Reactivate</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection