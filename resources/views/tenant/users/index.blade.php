@extends('layouts.admin')
@section('title', 'Users')
@section('content')

<script>
function copyText(text, btn) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    ta.setSelectionRange(0, 99999);
    try {
        document.execCommand('copy');
        if (btn) {
            const original = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = original; }, 1500);
        }
    } catch (e) {
        alert('Could not copy automatically — please select and copy manually.');
    }
    document.body.removeChild(ta);
}
</script>
    <h1 style="font:700 24px 'Figtree';letter-spacing:-.3px;margin-bottom:6px">Users</h1>
    <p class="muted" style="color:#5F5D6E;margin-bottom:20px">
        Everyone who signs in to this company.
    </p>

    @if ($activeAdminCount === 1)
        <div style="background:#FFF4CC;border:1px solid #FFC61A;color:#8A5A00;padding:10px 14px;border-radius:12px;margin-bottom:16px;font-size:13px">
            Add a second admin so you are never locked out.
        </div>
    @endif

    @if (session('temp_password'))
        @php($tp = session('temp_password'))
        <div style="background:#DDF7F1;border:1px solid #00866F;color:#00564A;padding:12px 16px;border-radius:12px;margin-bottom:16px">
            <strong>Temporary password for {{ $tp['user'] }}</strong><br>
            <code style="background:#fff;padding:4px 8px;border-radius:4px;font-family:'JetBrains Mono',Consolas,monospace;font-size:13px;display:inline-block;margin-top:6px">{{ $tp['password'] }}</code>
            <button onclick="copyText('{{ $tp['password'] }}', this)" style="margin-left:8px;background:#00866F;color:#fff;border:0;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer">Copy</button>
            <div style="font-size:12px;color:#00564A;margin-top:8px">Shown once. They must change it on first sign-in.</div>
        </div>
    @endif
        @if (session('invitation_link'))
    @php($il = session('invitation_link'))
    <div style="background:#E3EBF4;border:1px solid #2B5A8C;color:#1C3E63;padding:12px 16px;border-radius:12px;margin-bottom:16px">
        <strong>Invitation link for {{ $il['user'] }}</strong><br>
        <code style="background:#fff;padding:4px 8px;border-radius:4px;font-family:'JetBrains Mono',Consolas,monospace;font-size:12px;display:inline-block;margin-top:6px;word-break:break-all">{{ $il['url'] }}</code>
        <button onclick="copyText('{{ $il['url'] }}', this)" style="margin-left:8px;background:#2B5A8C;color:#fff;border:0;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer">Copy</button>
        <div style="font-size:12px;margin-top:8px">Share with them. Works once, expires in 7 days. Email delivery lands later.</div>
    </div>
@endif
    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email, phone"
               style="flex:1;min-width:220px;padding:9px 12px;border:1px solid #E6E4EE;border-radius:10px;font-size:13px">
        <select name="role" style="padding:9px 12px;border:1px solid #E6E4EE;border-radius:10px;font-size:13px">
            <option value="">All roles</option>
            @foreach ($roles as $r)
                <option value="{{ $r->code }}" @selected(request('role')===$r->code)>{{ $r->name }}</option>
            @endforeach
        </select>
        <select name="status" style="padding:9px 12px;border:1px solid #E6E4EE;border-radius:10px;font-size:13px">
            <option value="">All statuses</option>
            <option value="active"       @selected(request('status')==='active')>Active</option>
            <option value="invited"      @selected(request('status')==='invited')>Invited</option>
            <option value="deactivated"  @selected(request('status')==='deactivated')>Turned off</option>
        </select>
        <button type="submit" style="background:#FFC61A;color:#1B1A24;border:0;padding:9px 18px;border-radius:999px;font-weight:600;cursor:pointer">Filter</button>
        <a href="{{ route('tenant.users.create') }}" style="margin-left:auto;background:#1B1A24;color:#fff;padding:9px 18px;border-radius:999px;text-decoration:none;font-weight:600;font-size:13px">+ New user</a>
        <a href="{{ route('tenant.users.import.show') }}" class="btn-ghost" style="margin-right:8px">Import CSV</a>
    </form>

    <div style="background:#fff;border:1px solid #E6E4EE;border-radius:16px;overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead style="background:#F5F4FA;color:#5F5D6E;font-size:11px;text-transform:uppercase;letter-spacing:.08em">
                <tr>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Name</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Role</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Email / phone</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Status</th>
                    <th style="text-align:left;padding:12px 16px;font-weight:600">Last sign-in</th>
                    <th style="text-align:right;padding:12px 16px;font-weight:600">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr style="border-top:1px solid #E6E4EE">
                        <td style="padding:12px 16px;font-weight:600">{{ $u->name }}</td>
                        <td style="padding:12px 16px">{{ $u->role?->name ?? '—' }}</td>
                        <td style="padding:12px 16px;color:#5F5D6E">{{ $u->email ?? $u->phone ?? '—' }}</td>
                        <td style="padding:12px 16px">
                            @if ($u->deactivated_at)
                                <span style="background:#FDECE7;color:#C2381B;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600">Turned off</span>
                            @elseif ($u->must_change_password && ! $u->last_sign_in_at)
                                <span style="background:#FFF4CC;color:#8A5A00;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600">Invited</span>
                            @else
                                <span style="background:#DDF7F1;color:#00866F;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600">Active</span>
                            @endif
                        </td>
                        <td style="padding:12px 16px;color:#5F5D6E;font-size:12px">{{ $u->last_sign_in_at?->diffForHumans() ?? 'Never' }}</td>
                        <td style="padding:12px 16px;text-align:right">
                            <a href="{{ route('tenant.users.edit', $u) }}"
                            style="color:#1B1A24;font-weight:600;text-decoration:underline;font-size:12px;margin-right:12px">Edit</a>

                            <a href="{{ route('tenant.users.devices', $u) }}"
                            style="color:#1B1A24;font-weight:600;text-decoration:underline;font-size:12px;margin-right:12px">Devices</a>

                            @if ($u->deactivated_at)
                                <form method="POST" action="{{ route('tenant.users.reactivate', $u) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" style="background:none;border:0;color:#00866F;font-weight:600;cursor:pointer;font-size:12px;text-decoration:underline">Turn on</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('tenant.users.deactivate', $u) }}" style="display:inline" class="needs-confirm" data-confirm-title="Turn off {{ $u->name }}?" data-confirm-text="They won't be able to sign in." data-confirm-ok="Turn off" data-confirm-ok-color="red">
                                    @csrf
                                    <button type="submit" style="background:none;border:0;color:#C2381B;font-weight:600;cursor:pointer;font-size:12px;text-decoration:underline">Turn off</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:#5F5D6E">No users match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $users->links() }}</div>
@endsection