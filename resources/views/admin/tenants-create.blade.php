@extends('layouts.admin')
@section('title', 'New workspace')
@section('content')
    <h1 style="font:700 24px 'Figtree';letter-spacing:-.3px;margin-bottom:6px">New workspace</h1>
    <p style="color:#5F5D6E;margin-bottom:24px">A company and its first admin. The admin gets an invitation link.</p>

    <form method="POST" action="{{ route('admin.tenants.store') }}" style="max-width:720px;background:#fff;border:1px solid #E6E4EE;border-radius:16px;padding:28px">
        @csrf

        <h2 style="font:600 16px 'Figtree';margin-bottom:14px">Company</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Company name</span>
                <input type="text" name="name" value="{{ old('name') }}" required
                       style="width:100%;height:42px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
                @error('name') <span style="color:#C2381B;font-size:12px">{{ $message }}</span> @enderror
            </label>
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Address (slug)</span>
                <input type="text" name="slug" value="{{ old('slug') }}" required pattern="[a-z][a-z0-9-]*"
                       style="width:100%;height:42px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
                @error('slug') <span style="color:#C2381B;font-size:12px">{{ $message }}</span> @enderror
            </label>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Plan</span>
                <select name="plan_id" style="width:100%;height:42px;padding:0 10px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
                    <option value="">— none —</option>
                    @foreach ($plans as $p)
                        <option value="{{ $p->id }}" @selected(old('plan_id')==$p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Country (ISO 2)</span>
                <input type="text" name="country" value="{{ old('country') }}" maxlength="2"
                       style="width:100%;height:42px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px;text-transform:uppercase">
            </label>
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Base currency (ISO 3)</span>
                <input type="text" name="base_currency" value="{{ old('base_currency') }}" maxlength="3"
                       style="width:100%;height:42px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px;text-transform:uppercase">
            </label>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Time zone</span>
                <input type="text" name="timezone" value="{{ old('timezone', 'Asia/Calcutta') }}"
                       style="width:100%;height:42px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
            </label>
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Start as</span>
                <select name="start_as" style="width:100%;height:42px;padding:0 10px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
                    <option value="trial"  @selected(old('start_as', 'trial')==='trial')>Trial</option>
                    <option value="active" @selected(old('start_as')==='active')>Active</option>
                </select>
            </label>
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Trial ends on</span>
                <input type="date" name="trial_ends_on" value="{{ old('trial_ends_on') }}"
                       style="width:100%;height:42px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
            </label>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Contact email</span>
                <input type="email" name="contact_email" value="{{ old('contact_email') }}"
                       style="width:100%;height:42px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
            </label>
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Primary color</span>
                <input type="color" name="primary_color" value="{{ old('primary_color', '#FFC61A') }}"
                       style="width:60px;height:42px;padding:0;border:1px solid #E6E4EE;border-radius:10px">
            </label>
        </div>

        <h2 style="font:600 16px 'Figtree';margin:24px 0 14px;padding-top:20px;border-top:1px solid #E6E4EE">First admin</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Name</span>
                <input type="text" name="first_admin_name" value="{{ old('first_admin_name') }}" required
                       style="width:100%;height:42px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
                @error('first_admin_name') <span style="color:#C2381B;font-size:12px">{{ $message }}</span> @enderror
            </label>
            <label>
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">Email</span>
                <input type="email" name="first_admin_email" value="{{ old('first_admin_email') }}" required
                       style="width:100%;height:42px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
                @error('first_admin_email') <span style="color:#C2381B;font-size:12px">{{ $message }}</span> @enderror
            </label>
        </div>

        <div style="display:flex;gap:10px;align-items:center;margin-top:24px;padding-top:20px;border-top:1px solid #E6E4EE">
            <button type="submit" style="background:#FFC61A;color:#1B1A24;border:0;padding:11px 24px;border-radius:999px;font-weight:600;cursor:pointer">Create workspace</button>
            <a href="{{ route('admin.dashboard') }}" style="color:#5F5D6E;text-decoration:none;font-weight:600;margin-left:8px">Cancel</a>
        </div>
    </form>
@endsection