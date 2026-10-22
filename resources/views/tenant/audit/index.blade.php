@extends('layouts.admin')
@section('title', 'Audit trail')
@section('content')
    <h1 style="font:700 24px 'Figtree';letter-spacing:-.3px;margin-bottom:6px">Audit trail</h1>
    <p style="color:#5F5D6E;margin-bottom:20px">Every change to users, roles, and company state. Read-only, kept for 3 years.</p>

    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <input type="date" name="from" value="{{ request('from') }}" style="padding:8px 10px;border:1px solid #E6E4EE;border-radius:8px;font-size:13px">
        <input type="date" name="to" value="{{ request('to') }}" style="padding:8px 10px;border:1px solid #E6E4EE;border-radius:8px;font-size:13px">
        <select name="actor" style="padding:8px 10px;border:1px solid #E6E4EE;border-radius:8px;font-size:13px">
            <option value="">All people</option>
            @foreach ($actors as $a)
                <option value="{{ $a->id }}" @selected((int) request('actor') === $a->id)>{{ $a->name }}</option>
            @endforeach
        </select>
        <select name="action" style="padding:8px 10px;border:1px solid #E6E4EE;border-radius:8px;font-size:13px">
            <option value="">All actions</option>
            @foreach ($actionGroups as $prefix => $label)
                <option value="{{ $prefix }}." @selected(request('action') === $prefix.'.')>{{ $label }}</option>
            @endforeach
        </select>
        <select name="item" style="padding:8px 10px;border:1px solid #E6E4EE;border-radius:8px;font-size:13px">
            <option value="">All items</option>
            <option value="User"       @selected(request('item') === 'User')>Users</option>
            <option value="Role"       @selected(request('item') === 'Role')>Roles</option>
            <option value="Tenant"     @selected(request('item') === 'Tenant')>Company</option>
        </select>
        <button type="submit" style="background:#FFC61A;color:#1B1A24;border:0;padding:9px 18px;border-radius:999px;font-weight:600;cursor:pointer">Filter</button>
    </form>

    <div style="background:#fff;border:1px solid #E6E4EE;border-radius:16px;overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead style="background:#F5F4FA;color:#5F5D6E;font-size:11px;text-transform:uppercase;letter-spacing:.08em">
                <tr>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">When</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Who</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">What</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Item</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">From</th>
                    <th style="padding:12px 16px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr style="border-top:1px solid #E6E4EE">
                        <td style="padding:12px 16px;color:#5F5D6E;font-size:12px;white-space:nowrap">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                        <td style="padding:12px 16px;font-weight:600">{{ $log->actor_name ?? '—' }}</td>
                        <td style="padding:12px 16px;font-family:'JetBrains Mono',Consolas,monospace;font-size:12px">{{ $log->action }}</td>
                        <td style="padding:12px 16px;color:#5F5D6E">
                            {{ $log->item_type ? class_basename($log->item_type) : '—' }}
                            @if ($log->item_id)<span style="color:#8B89A0">#{{ $log->item_id }}</span>@endif
                        </td>
                        <td style="padding:12px 16px;color:#5F5D6E;font-family:'JetBrains Mono',Consolas,monospace;font-size:12px">{{ $log->ip_address ?? '—' }}</td>
                        <td style="padding:12px 16px;text-align:right">
                            @if ($log->old_values || $log->new_values)
                                <details>
                                    <summary style="cursor:pointer;color:#1B1A24;font-weight:600;font-size:12px;text-decoration:underline">Values</summary>
                                    <pre style="background:#F5F4FA;padding:10px;border-radius:8px;font-family:'JetBrains Mono',Consolas,monospace;font-size:11px;white-space:pre-wrap;margin-top:8px;max-width:420px;overflow-x:auto">@if ($log->old_values){{ 'OLD: ' . json_encode($log->old_values, JSON_PRETTY_PRINT) . "\n" }}@endif
@if ($log->new_values){{ 'NEW: ' . json_encode($log->new_values, JSON_PRETTY_PRINT) }}@endif</pre>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:#5F5D6E">No activity matches these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $logs->links() }}</div>
@endsection