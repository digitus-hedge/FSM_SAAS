@extends('layouts.admin')
@section('title', 'Roles & permissions')
@section('content')
    <h1 style="font:700 24px 'Figtree';letter-spacing:-.3px;margin-bottom:6px">Roles & permissions</h1>
    <p style="color:#5F5D6E;margin-bottom:20px">Who can do what in this company.</p>

    <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
        <a href="{{ route('tenant.roles.create') }}"
           style="background:#1B1A24;color:#fff;padding:9px 18px;border-radius:999px;text-decoration:none;font-weight:600;font-size:13px">+ Custom role</a>
    </div>

    <div style="background:#fff;border:1px solid #E6E4EE;border-radius:16px;overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead style="background:#F5F4FA;color:#5F5D6E;font-size:11px;text-transform:uppercase;letter-spacing:.08em">
                <tr>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Role</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Type</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Users</th>
                    <th style="text-align:right;padding:12px 16px;font-weight:600">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $r)
                    <tr style="border-top:1px solid #E6E4EE">
                        <td style="padding:12px 16px;font-weight:600">{{ $r->name }}
                            <span style="color:#8B89A0;font-weight:400;font-family:'JetBrains Mono',Consolas,monospace;font-size:11px;margin-left:6px">{{ $r->code }}</span>
                        </td>
                        <td style="padding:12px 16px">
                            @if ($r->is_standard)
                                <span style="background:#F5F4FA;color:#5F5D6E;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600">Standard</span>
                            @else
                                <span style="background:#FFF4CC;color:#8A5A00;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600">Custom · based on {{ $r->based_on_code }}</span>
                            @endif
                        </td>
                        <td style="padding:12px 16px">{{ $r->users_count }}</td>
                        <td style="padding:12px 16px;text-align:right">
                            <a href="{{ route('tenant.roles.edit', $r) }}" style="color:#1B1A24;font-weight:600;text-decoration:underline;font-size:12px;margin-right:12px">Edit</a>
                            @if (! $r->is_standard && $r->users_count === 0)
                                <form method="POST" action="{{ route('tenant.roles.destroy', $r) }}" style="display:inline"
                                      class="needs-confirm" data-confirm-title="Delete {{ $r->name }}?" data-confirm-ok="Delete" data-confirm-ok-color="red">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:none;border:0;color:#C2381B;font-weight:600;cursor:pointer;font-size:12px;text-decoration:underline">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection