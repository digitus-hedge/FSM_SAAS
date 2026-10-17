@extends('layouts.admin')

@section('title', 'New workspace')

@section('content')
<style>
h1{font-family:'Figtree';font-size:30px;font-weight:700;letter-spacing:-.6px;margin-bottom:4px}
.lede{color:var(--muted);margin-bottom:28px;font-size:14.5px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:28px;max-width:620px}
.section-title{font-family:'Figtree';font-size:15px;font-weight:700;margin:22px 0 4px;color:var(--ink)}
.section-sub{color:var(--muted);font-size:13px;margin-bottom:16px}
.divider{height:1px;background:var(--line);margin:22px 0}
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:18px;font-size:13px;font-weight:700}
.field .opt{font-weight:400;color:var(--muted);font-size:12px}
.field input{height:46px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font:inherit;font-weight:400;font-size:14px;background:#fff}
.field input:focus{outline:none;border-color:var(--yellow);box-shadow:0 0 0 3px var(--yellow-soft)}
.field .hint{font-weight:400;color:var(--muted);font-size:12px;margin-top:2px}
.err{color:var(--red);font-size:12px;font-weight:500;margin-top:2px}
.actions{display:flex;gap:12px;margin-top:8px}
.btn{display:inline-flex;align-items:center;gap:6px;background:var(--yellow);color:var(--ink);font-weight:700;font-size:14px;padding:12px 22px;border-radius:999px;border:0;cursor:pointer;text-decoration:none;font-family:inherit}
.btn:hover{background:#F2B200}
.btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--line);padding:11px 20px}
.btn.ghost:hover{background:var(--soft)}
.color-row{display:flex;align-items:center;gap:12px}
.color-row input[type=color]{width:60px;height:46px;padding:4px;cursor:pointer}
.color-row .code{font-family:'JetBrains Mono',Consolas,monospace;font-size:13px;color:var(--muted);font-weight:400}
</style>

<h1>New workspace</h1>
<p class="lede">A workspace is one company using the platform. Each gets its own subdomain, users and data.</p>

<form method="POST" action="{{ route('admin.tenants.store') }}" class="card">
    @csrf

    {{-- ─── WORKSPACE ────────────────────────────────────────── --}}
    <div class="section-title">Workspace</div>
    <div class="section-sub">Where this company's data lives.</div>

    <div class="field">
        <label>Company name</label>
        <input name="name" value="{{ old('name') }}" placeholder="Acme Industries LLC" autofocus required>
        @error('name') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label>Subdomain</label>
        <input name="slug" value="{{ old('slug') }}" placeholder="acme" pattern="[a-z0-9\-]+" required>
        <span class="hint">Lowercase letters, numbers and hyphens only. Reachable at <b>[subdomain].{{ parse_url(config('app.url'), PHP_URL_HOST) }}</b></span>
        @error('slug') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label>Contact email <span class="opt">optional</span></label>
        <input type="email" name="contact_email" value="{{ old('contact_email') }}" placeholder="ops@acme.com">
        @error('contact_email') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label>Primary colour <span class="opt">optional</span></label>
        <div class="color-row">
            <input type="color" name="primary_color" value="{{ old('primary_color', '#FFC61A') }}" oninput="document.getElementById('colorCode').textContent=this.value">
            <span class="code" id="colorCode">{{ old('primary_color', '#FFC61A') }}</span>
        </div>
        @error('primary_color') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="divider"></div>

    {{-- ─── FIRST COMPANY ADMIN ──────────────────────────────── --}}
    <div class="section-title">First Company Admin</div>
    <div class="section-sub">The initial login for this workspace. They can invite more members after signing in.</div>

    <div class="field">
        <label>Admin name</label>
        <input name="admin_name" value="{{ old('admin_name') }}" placeholder="Jane Smith" required>
        @error('admin_name') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label>Admin email</label>
        <input type="email" name="admin_email" value="{{ old('admin_email') }}" placeholder="jane@acme.com" required>
        @error('admin_email') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="field">
        <label>Admin password</label>
        <input type="text" name="admin_password" value="{{ old('admin_password') }}" minlength="8" placeholder="At least 8 characters" required>
        <span class="hint">Share this with the admin — they can change it after signing in.</span>
        @error('admin_password') <span class="err">{{ $message }}</span> @enderror
    </div>

    <div class="actions">
        <button type="submit" class="btn">Create workspace</button>
        <a href="{{ route('admin.dashboard') }}" class="btn ghost">Cancel</a>
    </div>
</form>
@endsection