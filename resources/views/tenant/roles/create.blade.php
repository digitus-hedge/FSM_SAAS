@extends('layouts.admin')
@section('title', 'Custom role')
@section('content')
    <h1 style="font:700 24px var(--font-display);letter-spacing:-.3px;margin-bottom:6px">Custom role</h1>
    <p style="color:var(--muted);margin-bottom:24px;font-size:14px">
        Based on one standard role. You can edit its permissions after creating.
    </p>

    <form method="POST" action="{{ route('tenant.roles.store') }}"
          style="max-width:520px;background:#fff;border:1px solid var(--line);border-radius:16px;padding:28px">
        @csrf

        <label style="display:block;margin-bottom:16px">
            <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Name</span>
            <input type="text" name="name" value="{{ old('name') }}" required
                   style="width:100%;height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font-size:14px">
            @error('name') <span style="color:var(--red);font-size:12px;display:block;margin-top:4px">{{ $message }}</span> @enderror
        </label>

        <label style="display:block;margin-bottom:16px">
            <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Based on</span>
            <select name="based_on" required style="width:100%;height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font-size:14px">
                <option value="">— choose a standard role —</option>
                @foreach ($standardRoles as $r)
                    <option value="{{ $r->code }}" @selected(old('based_on') === $r->code)>{{ $r->name }}</option>
                @endforeach
            </select>
            @error('based_on') <span style="color:var(--red);font-size:12px;display:block;margin-top:4px">{{ $message }}</span> @enderror
        </label>

        <div style="display:flex;gap:10px;align-items:center;margin-top:24px;padding-top:20px;border-top:1px solid var(--line)">
            <button type="submit" style="background:var(--yellow);color:var(--ink);border:0;padding:11px 24px;border-radius:999px;font-weight:600;cursor:pointer">Create role</button>
            <a href="{{ route('tenant.roles.index') }}" style="color:var(--muted);text-decoration:none;font-weight:600;margin-left:8px">Cancel</a>
        </div>
    </form>
@endsection