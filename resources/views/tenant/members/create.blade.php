@extends('layouts.admin')

@section('title', 'Add member')

@section('content')
<style>
h1{font-family:var(--font-display);font-size:28px;font-weight:700;letter-spacing:-.5px;margin-bottom:4px}
.lede{color:var(--muted);margin-bottom:24px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:26px;max-width:560px}
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px;font-size:13px;font-weight:700}
.field input,.field select{height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font:inherit;font-weight:400;font-size:14px;background:#fff}
.field input:focus,.field select:focus{outline:none;border-color:var(--yellow);box-shadow:0 0 0 3px var(--yellow-soft)}
.err{color:var(--red);font-size:12px;margin-top:2px;font-weight:500}
.hint{font-weight:400;color:var(--muted);font-size:12px;margin-top:2px}
.actions{display:flex;gap:12px;margin-top:8px}
.btn{background:var(--yellow);color:var(--ink);font-weight:700;font-size:14px;padding:12px 22px;border-radius:999px;border:0;cursor:pointer;font-family:var(--font-body)}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--ink)}
</style>

<h1>Add member</h1>
<p class="lede">The user gets an invitation email to set their own password. If they have no email, a one-time temporary password is shown after you create them.</p>

<form method="POST" action="{{ route('tenant.users.store') }}" class="card">
    @csrf

    <div class="field">
        <label>Full name</label>
        <input name="name" value="{{ old('name') }}" required autofocus>
        @error('name') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required>
        @error('email') <span class="err">{{ $message }}</span> @enderror
    </div>
<div class="field">
    <label>Phone Number</label>
    <input
        type="tel"
        inputmode="tel"
        pattern="[0-9+\-\s()]*"
        oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
        name="phone"
        value="{{ old('phone') }}"
        placeholder="Enter phone number"
    >
    @error('phone')
        <span class="err">{{ $message }}</span>
    @enderror
</div>
<div class="field">
    <label>Role</label>

    <select name="role_id" required>
        <option value="">Select role</option>

        @foreach ($roles as $role)
            <option
                value="{{ $role->id }}"
                {{ (string) old('role_id') === (string) $role->id ? 'selected' : '' }}
            >
                {{ $role->name }}
            </option>
        @endforeach
    </select>

    @error('role_id')
        <span class="err">{{ $message }}</span>
    @enderror
</div>

    <div class="actions">
        <button type="submit" class="btn">Add member</button>
        <a href="{{ route('tenant.users.index') }}" class="btn ghost">Cancel</a>
    </div>
</form>
@endsection