@extends('layouts.admin')
@section('title', $targetUser->name . ' — devices')
@section('content')
    <h1 style="font:700 24px var(--font-display);letter-spacing:-.3px;margin-bottom:6px">{{ $targetUser->name }}'s devices</h1>
    <p style="color:var(--muted);margin-bottom:20px">Browsers signed into this account.</p>

    <div style="background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead style="background:var(--soft);color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.08em">
                <tr>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Device</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">IP</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Last used</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Trusted until</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($devices as $d)
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:12px 16px;font-weight:600">{{ $d->label }}</td>
                        <td style="padding:12px 16px;color:var(--muted);font-family:'JetBrains Mono',Consolas,monospace;font-size:12px">{{ $d->ip ?? '—' }}</td>
                        <td style="padding:12px 16px;color:var(--muted);font-size:12px">{{ $d->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                        <td style="padding:12px 16px;color:var(--muted);font-size:12px">{{ $d->trusted_until?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding:40px;text-align:center;color:var(--muted)">No active devices.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">
        <a href="{{ route('tenant.users.edit', $targetUser) }}" style="color:var(--muted);text-decoration:none;font-weight:600">&larr; Back to user</a>
    </div>
@endsection