@extends('layouts.admin')
@section('title', 'Devices')
@section('content')
    <h1 style="font:700 24px 'Figtree';letter-spacing:-.3px;margin-bottom:6px">Devices</h1>
    <p style="color:#5F5D6E;margin-bottom:20px">Every browser signed into your account.</p>

    @if ($devices->count() > 1)
        <form method="POST" action="{{ route('profile.devices.revoke-others') }}" style="margin-bottom:16px"
              class="needs-confirm" data-confirm-title="Sign out everywhere?"
              data-confirm-text="You'll stay signed in on this device. All other devices will be signed out immediately."
              data-confirm-ok="Sign out everywhere" data-confirm-ok-color="red">
            @csrf
            <button type="submit" style="background:#FDECE7;color:#C2381B;border:1px solid #C2381B;padding:9px 18px;border-radius:999px;font-weight:600;cursor:pointer;font-size:13px">
                Sign out everywhere else
            </button>
        </form>
    @endif

    <div style="background:#fff;border:1px solid #E6E4EE;border-radius:16px;overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead style="background:#F5F4FA;color:#5F5D6E;font-size:11px;text-transform:uppercase;letter-spacing:.08em">
                <tr>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Device</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">IP</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Last used</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Trusted until</th>
                    <th style="padding:12px 16px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($devices as $d)
                    <tr style="border-top:1px solid #E6E4EE">
                        <td style="padding:12px 16px;font-weight:600">
                            {{ $d->label }}
                            @if ($d->session_id === $currentSessionId)
                                <span style="background:#DDF7F1;color:#00866F;padding:2px 8px;border-radius:999px;font-size:10.5px;font-weight:600;margin-left:6px">This device</span>
                            @endif
                        </td>
                        <td style="padding:12px 16px;color:#5F5D6E;font-family:'JetBrains Mono',Consolas,monospace;font-size:12px">{{ $d->ip ?? '—' }}</td>
                        <td style="padding:12px 16px;color:#5F5D6E;font-size:12px">{{ $d->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                        <td style="padding:12px 16px;color:#5F5D6E;font-size:12px">{{ $d->trusted_until->diffForHumans() }}</td>
                        <td style="padding:12px 16px;text-align:right">
                            @if ($d->session_id !== $currentSessionId)
                                <form method="POST" action="{{ route('profile.devices.revoke', $d) }}" style="display:inline"
                                      class="needs-confirm" data-confirm-title="Sign out {{ $d->label }}?"
                                      data-confirm-ok="Sign out" data-confirm-ok-color="red">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none;border:0;color:#C2381B;font-weight:600;cursor:pointer;font-size:12px;text-decoration:underline">Sign out</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection