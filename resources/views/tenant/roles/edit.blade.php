@extends('layouts.admin')
@section('title', $role->name . ' — permissions')
@section('content')
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:6px">
        <h1 style="font:700 24px 'Figtree';letter-spacing:-.3px;margin:0">{{ $role->name }}</h1>
        <span style="color:#5F5D6E;font-size:12px;font-family:'JetBrains Mono',Consolas,monospace">{{ $role->code }}</span>
    </div>
    <p style="color:#5F5D6E;margin-bottom:16px;font-size:14px">
        {{ $role->is_standard ? 'Standard role.' : "Custom role based on {$role->based_on_code}." }}
    </p>

    <div style="background:#E3EBF4;border:1px solid #2B5A8C;color:#1C3E63;padding:10px 14px;border-radius:12px;margin-bottom:16px;font-size:13px">
        {{ $affectedUsers }} user{{ $affectedUsers === 1 ? '' : 's' }} will be affected by changes to this role.
    </div>

    <form method="POST" action="{{ route('tenant.roles.update', $role) }}" style="background:#fff;border:1px solid #E6E4EE;border-radius:16px;padding:24px 28px">
        @csrf
        @method('PUT')

        @foreach ($permissions as $group => $groupPerms)
            <h2 style="font:600 15px 'Figtree';margin:20px 0 10px;color:#1B1A24">{{ $group }}</h2>
            <div style="border:1px solid #E6E4EE;border-radius:12px;overflow:hidden">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    @foreach ($groupPerms as $p)
                        <tr style="{{ ! $loop->first ? 'border-top:1px solid #E6E4EE;' : '' }}">
                            <td style="padding:10px 14px;width:40%">
                                <div style="font-weight:600;font-family:'JetBrains Mono',Consolas,monospace;font-size:12px;color:#1B1A24">{{ $p->key }}</div>
                                <div style="color:#5F5D6E;font-size:12px;margin-top:2px">{{ $p->description }}</div>
                            </td>
                            <td style="padding:10px 14px;width:60%">
                                <select name="levels[{{ $p->id }}]"
                                        style="width:100%;max-width:220px;height:36px;padding:0 10px;border:1px solid #E6E4EE;border-radius:8px;font-size:13px;background:#fff">
                                    @foreach ($levels as $lvl)
                                        <option value="{{ $lvl }}" @selected(($currentLevels[$p->id] ?? 'none') === $lvl)>{{ $lvl }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endforeach

        <div style="display:flex;gap:10px;align-items:center;margin-top:24px;padding-top:20px;border-top:1px solid #E6E4EE">
            <button type="submit" style="background:#FFC61A;color:#1B1A24;border:0;padding:11px 24px;border-radius:999px;font-weight:600;cursor:pointer">Save</button>

            @if ($role->is_standard)
                <form method="POST" action="{{ route('tenant.roles.reset-defaults', $role) }}"
                      class="needs-confirm" data-confirm-title="Reset {{ $role->name }}?"
                      data-confirm-text="Permissions for this role will be re-applied from the signed matrix. Any custom changes you made will be lost."
                      data-confirm-ok="Reset to defaults" data-confirm-ok-color="amber">
                    @csrf
                    <button type="submit" style="background:#fff;border:1px solid #E6E4EE;padding:10px 16px;border-radius:999px;font-weight:600;cursor:pointer;font-size:13px">Reset to signed defaults</button>
                </form>
            @endif

            <a href="{{ route('tenant.roles.index') }}" style="color:#5F5D6E;text-decoration:none;font-weight:600;margin-left:auto">Cancel</a>
        </div>
    </form>
@endsection