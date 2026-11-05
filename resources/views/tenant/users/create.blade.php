@extends('layouts.admin')
@section('title', 'Add user')
@section('content')
@php
    $clients = $clients ?? collect();
    $clientRoleCodes = $clientRoleCodes ?? ['client_user', 'client_admin', 'client_viewer'];
@endphp
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
.btn{background:var(--yellow);color:var(--ink);font-weight:700;font-size:14px;padding:12px 22px;border-radius:999px;border:0;cursor:pointer;font-family:var(--font-body);text-decoration:none}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--ink)}
.client-box{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:14px 14px 4px;margin-bottom:16px}
</style>

<h1>Add user</h1>
<p class="lede">If you give an email, the user gets an invitation link. Without an email, you'll see a one-time temporary password. Client users always need an email and a client company.</p>

<form method="POST" action="{{ route('tenant.users.store') }}" class="card" id="add-user-form">
    @csrf

    <div class="field">
        <label for="field-name">Full name</label>
        <input name="name" id="field-name" value="{{ old('name') }}" required autofocus>
        @error('name') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label for="field-email">Email <span class="hint" id="email-hint" style="font-weight:400">(or leave blank if phone-only)</span></label>
        <input type="email" name="email" id="field-email" value="{{ old('email') }}">
        @error('email') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label for="field-phone">Phone <span class="hint" style="font-weight:400">(country code first, e.g. +971501234567)</span></label>
        <input
            type="tel"
            inputmode="tel"
            pattern="[0-9+\-\s()]*"
            oninput="this.value = this.value.replace(/[^0-9+\-\s()]/g, '')"
            name="phone"
            id="field-phone"
            value="{{ old('phone') }}">
        @error('phone') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label for="field-code">Employee code <span class="hint" style="font-weight:400">(optional)</span></label>
        <input type="text" name="employee_code" id="field-code" value="{{ old('employee_code') }}" maxlength="40">
        @error('employee_code') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label for="role_id">Role</label>
        <select name="role_id" id="role_id" required>
            <option value="">— choose a role —</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" data-code="{{ $role->code }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        @error('role_id') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="client-box" id="client-field" style="display:none">
        <div class="field">
            <label for="client_id">Client company</label>
            <select name="client_id" id="client_id">
                <option value="">— choose a client company —</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
            <span class="hint">This login only sees that company's service requests and invoices in the client portal.</span>
            @error('client_id') <span class="err">{{ $message }}</span> @enderror
            @if ($clients->isEmpty())
                <span class="hint" style="color:var(--amber)">No client companies yet. Add one under Master data → Clients first.</span>
            @endif
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="btn">Create user</button>
        <a href="{{ route('tenant.users.index') }}" class="btn ghost">Cancel</a>
    </div>
</form>

<script>
(function () {
    var clientRoleCodes = @json($clientRoleCodes);
    var form         = document.getElementById('add-user-form');
    var roleSelect   = document.getElementById('role_id');
    var clientField  = document.getElementById('client-field');
    var clientSelect = document.getElementById('client_id');
    var emailInput   = document.getElementById('field-email');
    var emailHint    = document.getElementById('email-hint');

    function isClientRole() {
        var opt = roleSelect.options[roleSelect.selectedIndex];
        var code = opt ? opt.getAttribute('data-code') : null;
        return code !== null && clientRoleCodes.indexOf(code) !== -1;
    }

    function toggleClientField() {
        var client = isClientRole();
        clientField.style.display = client ? '' : 'none';
        clientSelect.required = client;
        emailInput.required = client;
        emailHint.textContent = client ? '(required — client users sign in with email)' : '(or leave blank if phone-only)';
        if (!client) clientSelect.value = '';
    }

    roleSelect.addEventListener('change', toggleClientField);
    toggleClientField();

    form.addEventListener('submit', function (e) {
        var email = emailInput.value.trim();
        var phone = document.getElementById('field-phone').value.trim();
        if (!email && !phone) {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Email or phone required',
                    text: 'Enter at least an email or a phone number so this user can be invited or given sign-in credentials.'
                });
            } else {
                alert('Enter at least an email or a phone number.');
            }
        }
    });
})();
</script>
@endsection