@extends('layouts.admin')
@section('title', $user ? 'Edit user' : 'Add user')
@section('content')
@php
    $clients = $clients ?? collect();
    $teamLeads = $teamLeads ?? collect();
    $clientRoleCodes = $clientRoleCodes ?? ['client_user', 'client_admin', 'client_viewer'];
@endphp
<style>
h1{font-family:var(--font-display);font-size:28px;font-weight:700;letter-spacing:-.5px;margin-bottom:4px}
.lede{color:var(--muted);margin-bottom:24px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:26px;max-width:560px}
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px;font-size:13px;font-weight:700}
.field input,.field select{height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font:inherit;font-weight:400;font-size:14px;background:#fff}
.field input:focus,.field select:focus{outline:none;border-color:var(--yellow);box-shadow:0 0 0 3px var(--yellow-soft)}
.field input[readonly]{background:var(--soft);color:var(--muted)}
.err{color:var(--red);font-size:12px;margin-top:2px;font-weight:500}
.hint{font-weight:400;color:var(--muted);font-size:12px;margin-top:2px}
.actions{display:flex;gap:12px;margin-top:8px;flex-wrap:wrap}
.btn{background:var(--yellow);color:var(--ink);font-weight:700;font-size:14px;padding:12px 22px;border-radius:999px;border:0;cursor:pointer;font-family:var(--font-body);text-decoration:none}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--ink)}
.client-box{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:14px 14px 4px;margin-bottom:16px}
</style>

<h1>{{ $user ? 'Edit user' : 'Add user' }}</h1>
<p class="lede">
    @if ($user)
        Update {{ $user->name }}'s details. Email and phone must stay unique in this company.
    @else
        Email and phone are both optional individually, but at least one must be unique in this company — a client login always needs an email.
    @endif
</p>

<form method="POST" action="{{ $user ? route('tenant.users.update', $user) : route('tenant.users.store') }}" class="card" id="user-form">
    @csrf
    @if ($user)
        @method('PUT')
    @endif

    <div class="field">
        <label for="field-name">Full name</label>
        <input name="name" id="field-name" value="{{ old('name', $user?->name) }}" required autofocus>
        @error('name') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label for="field-email">Email <span class="hint" id="email-hint" style="font-weight:400">(or leave blank if phone-only)</span></label>
        <input type="email" name="email" id="field-email" value="{{ old('email', $user?->email) }}">
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
            value="{{ old('phone', $user?->phone) }}">
        @error('phone') <span class="err">{{ $message }}</span> @enderror
    </div>

    @if ($user)
        <div class="field">
            <label for="field-code">Employee code</label>
            <input type="text" id="field-code" value="{{ $user->employee_code }}" readonly>
            <span class="hint">Generated automatically. It can't be changed here.</span>
        </div>
    @endif

    <div class="field">
        <label for="role_id">Role</label>
        <select name="role_id" id="role_id" required>
            <option value="">— choose a role —</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" data-code="{{ $role->code }}"
                    @selected((string) old('role_id', $user?->role_id) === (string) $role->id)>{{ $role->name }}</option>
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
                    <option value="{{ $client->id }}"
                        @selected((string) old('client_id', $user?->client_id) === (string) $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
            <span class="hint">This login only sees that company's service requests and invoices in the client portal.</span>
            @error('client_id') <span class="err">{{ $message }}</span> @enderror
            @if ($clients->isEmpty())
                <span class="hint" style="color:var(--amber)">No client companies yet. Add one under Master data → Clients first.</span>
            @endif
        </div>
    </div>

    <div class="client-box" id="team-lead-field" style="display:none">
        <div class="field">
            <label for="team_lead_id">Team lead</label>
            <select name="team_lead_id" id="team_lead_id">
                <option value="">— no team lead —</option>
                @foreach ($teamLeads as $lead)
                    <option value="{{ $lead->id }}"
                        @selected((string) old('team_lead_id', $user?->team_lead_id) === (string) $lead->id)>{{ $lead->name }}</option>
                @endforeach
            </select>
            <span class="hint">Puts this technician on that team lead's crew — their Team Lead view (Home, Requests, Overrides, Field conflicts) includes this technician's jobs.</span>
            @error('team_lead_id') <span class="err">{{ $message }}</span> @enderror
            @if ($teamLeads->isEmpty())
                <span class="hint" style="color:var(--amber)">No team leads yet. Create a user with the Team Lead role first.</span>
            @endif
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="btn">{{ $user ? 'Save changes' : 'Create user' }}</button>
        <a href="{{ route('tenant.users.index') }}" class="btn ghost">Cancel</a>
        @if ($user)
            <a href="{{ route('tenant.users.devices', $user) }}" class="btn ghost">Devices</a>
        @endif
    </div>
</form>

<script>
(function () {
    var clientRoleCodes = @json($clientRoleCodes);
    var roleSelect   = document.getElementById('role_id');
    var clientField  = document.getElementById('client-field');
    var clientSelect = document.getElementById('client_id');
    var teamLeadField  = document.getElementById('team-lead-field');
    var teamLeadSelect = document.getElementById('team_lead_id');
    var emailInput   = document.getElementById('field-email');
    var emailHint    = document.getElementById('email-hint');

    function toggleFields() {
        var opt = roleSelect.options[roleSelect.selectedIndex];
        var code = opt ? opt.getAttribute('data-code') : null;
        var client = code !== null && clientRoleCodes.indexOf(code) !== -1;
        var technician = code === 'field_technician';

        clientField.style.display = client ? '' : 'none';
        clientSelect.required = client;
        emailInput.required = client;
        emailHint.textContent = client ? '(required — client users sign in with email)' : '(or leave blank if phone-only)';
        if (!client) clientSelect.value = '';

        teamLeadField.style.display = technician ? '' : 'none';
        if (!technician) teamLeadSelect.value = '';
    }

    roleSelect.addEventListener('change', toggleFields);
    toggleFields();
})();
</script>
@endsection