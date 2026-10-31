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

<style>
    .btn-ghost{
  display:inline-flex;
  align-items:center;
  gap:7px;
  padding:9px 18px;
  background:#fff;
  color:var(--ink);
  border:1px solid var(--line);
  border-radius:10px;
  font-family:var(--font-body);
  font-size:13.5px;
  font-weight:600;
  text-decoration:none;
  cursor:pointer;
  line-height:1.2;
  transition:background .12s, border-color .12s, color .12s;
}
.btn-ghost:hover{
  background:var(--soft);
  border-color:var(--muted);
  color:var(--ink);
}
.btn-ghost:focus{
  outline:none;
  box-shadow:0 0 0 3px color-mix(in srgb, var(--yellow) 15%, transparent);
  border-color:var(--yellow);
}
.btn-ghost svg{
  width:14px;height:14px;flex-shrink:0;
}
</style>


    <h1 style="font:700 24px var(--font-display);letter-spacing:-.3px;margin-bottom:6px">Users</h1>
    <p class="muted" style="color:var(--muted);margin-bottom:20px">
        Everyone who signs in to this company.
    </p>

    @if ($activeAdminCount === 1)
        <div style="background:var(--yellow-soft);border:1px solid var(--yellow);color:var(--amber);padding:10px 14px;border-radius:12px;margin-bottom:16px;font-size:13px">
            Add a second admin so you are never locked out.
        </div>
    @endif

    @if (session('temp_password'))
        @php($tp = session('temp_password'))
        <div style="background:var(--green-soft);border:1px solid var(--green);color:var(--green);padding:12px 16px;border-radius:12px;margin-bottom:16px">
            <strong>Temporary password for {{ $tp['user'] }}</strong><br>
            <code style="background:#fff;padding:4px 8px;border-radius:4px;font-family:'JetBrains Mono',Consolas,monospace;font-size:13px;display:inline-block;margin-top:6px">{{ $tp['password'] }}</code>
            <button onclick="copyText('{{ $tp['password'] }}', this)" style="margin-left:8px;background:var(--green);color:#fff;border:0;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer">Copy</button>
            <div style="font-size:12px;color:var(--green);margin-top:8px">Shown once. They must change it on first sign-in.</div>
        </div>
    @endif
        @if (session('invitation_link'))
    @php($il = session('invitation_link'))
    <div style="background:var(--soft);border:1px solid var(--line);color:var(--ink);padding:12px 16px;border-radius:12px;margin-bottom:16px">
        <strong>Invitation link for {{ $il['user'] }}</strong><br>
        <code style="background:#fff;padding:4px 8px;border-radius:4px;font-family:'JetBrains Mono',Consolas,monospace;font-size:12px;display:inline-block;margin-top:6px;word-break:break-all">{{ $il['url'] }}</code>
        <button onclick="copyText('{{ $il['url'] }}', this)" style="margin-left:8px;background:var(--ink);color:#fff;border:0;padding:5px 11px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer">Copy</button>
        <div style="font-size:12px;margin-top:8px">Share with them. Works once, expires in 7 days. Email delivery lands later.</div>
    </div>
@endif
    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email, phone"
               style="flex:1;min-width:220px;padding:9px 12px;border:1px solid var(--line);border-radius:10px;font-size:13px;font-family:var(--font-body)">
        <select name="role" style="padding:9px 12px;border:1px solid var(--line);border-radius:10px;font-size:13px;font-family:var(--font-body)">
            <option value="">All roles</option>
            @foreach ($roles as $r)
                <option value="{{ $r->code }}" @selected(request('role')===$r->code)>{{ $r->name }}</option>
            @endforeach
        </select>
        <select name="status" style="padding:9px 12px;border:1px solid var(--line);border-radius:10px;font-size:13px;font-family:var(--font-body)">
            <option value="">All statuses</option>
            <option value="active"       @selected(request('status')==='active')>Active</option>
            <option value="invited"      @selected(request('status')==='invited')>Invited</option>
            <option value="deactivated"  @selected(request('status')==='deactivated')>Turned off</option>
        </select>
        <button type="submit" style="background:var(--yellow);color:var(--ink);border:0;padding:9px 18px;border-radius:999px;font-weight:600;cursor:pointer;font-family:var(--font-body)">Filter</button>
        <a href="{{ route('tenant.users.create') }}" style="margin-left:auto;background:var(--ink);color:#fff;padding:9px 18px;border-radius:999px;text-decoration:none;font-weight:600;font-size:13px;font-family:var(--font-body)">+ New user</a>
      <a href="{{ route('tenant.users.import.show') }}" class="btn-ghost">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
    <polyline points="17 8 12 3 7 8"/>
    <line x1="12" y1="3" x2="12" y2="15"/>
  </svg>
  Import CSV
</a>
    </form>

    <div style="background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:13px;font-family:var(--font-body)">
            <thead style="background:var(--soft);color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.08em">
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
                    <tr style="border-top:1px solid var(--line)">
                        <td style="padding:12px 16px;font-weight:600">{{ $u->name }}</td>
                        <td style="padding:12px 16px">{{ $u->role?->name ?? '—' }}</td>
                        <td style="padding:12px 16px;color:var(--muted)">{{ $u->email ?? $u->phone ?? '—' }}</td>
                        <td style="padding:12px 16px">
                            @if ($u->deactivated_at)
                                <span style="background:var(--red-soft);color:var(--red);padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600">Turned off</span>
                            @elseif ($u->must_change_password && ! $u->last_sign_in_at)
                                <span style="background:var(--yellow-soft);color:var(--amber);padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600">Invited</span>
                            @else
                                <span style="background:var(--green-soft);color:var(--green);padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600">Active</span>
                            @endif
                        </td>
                        <td style="padding:12px 16px;color:var(--muted);font-size:12px">{{ $u->last_sign_in_at?->diffForHumans() ?? 'Never' }}</td>
                        <td style="padding:12px 16px;text-align:right">
                            <a href="{{ route('tenant.users.edit', $u) }}"
                            style="color:var(--ink);font-weight:600;text-decoration:underline;font-size:12px;margin-right:12px">Edit</a>

                            <a href="{{ route('tenant.users.devices', $u) }}"
                            style="color:var(--ink);font-weight:600;text-decoration:underline;font-size:12px;margin-right:12px">Devices</a>

                            @if ($u->deactivated_at)
                                <form method="POST" action="{{ route('tenant.users.reactivate', $u) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" style="background:none;border:0;color:var(--green);font-weight:600;cursor:pointer;font-size:12px;text-decoration:underline">Turn on</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('tenant.users.deactivate', $u) }}" style="display:inline" class="needs-confirm" data-confirm-title="Turn off {{ $u->name }}?" data-confirm-text="They won't be able to sign in." data-confirm-ok="Turn off" data-confirm-ok-color="red">
                                    @csrf
                                    <button type="submit" style="background:none;border:0;color:var(--red);font-weight:600;cursor:pointer;font-size:12px;text-decoration:underline">Turn off</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:40px;text-align:center;color:var(--muted)">No users match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $users->links() }}</div>
@endsection