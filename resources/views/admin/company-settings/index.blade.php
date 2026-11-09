@extends('layouts.admin')
@section('title', 'Company settings')

@section('content')
<h1 style="font-family:var(--font-display);font-size:28px;font-weight:700;margin-bottom:4px">Company settings</h1>
<p style="color:var(--muted);margin-bottom:24px">SLA defaults, currency and the reopen window.</p>

<form method="POST" action="{{ route('admin.company-settings.update') }}" style="background:#fff;border:1px solid var(--line);border-radius:18px;padding:26px;max-width:560px">
    @csrf
    @method('PUT')

    <div style="margin-bottom:20px">
        <label style="display:block;font-weight:700;font-size:13px;margin-bottom:6px">Company currency</label>
        @if ($currencyLocked)
            <input type="text" value="{{ $tenant->base_currency }}" disabled
                   style="height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;background:var(--soft);color:var(--muted);width:100%">
            <span style="color:var(--muted);font-size:12px">Locked — this company has already sent a quote or invoice.</span>
        @else
            <input type="text" name="base_currency" maxlength="3" value="{{ old('base_currency', $tenant->base_currency) }}"
                   placeholder="e.g. AED" style="height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;width:100%;text-transform:uppercase">
            @error('base_currency') <span style="color:var(--red);font-size:12px">{{ $message }}</span> @enderror
        @endif
    </div>

    <div style="margin-bottom:20px">
        <label style="display:block;font-weight:700;font-size:13px;margin-bottom:6px">Reopen window (days)</label>
        <input type="number" name="reopen_window_days" min="0" max="365"
               value="{{ old('reopen_window_days', $setting->reopen_window_days ?? 14) }}"
               style="height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;width:100%">
        <span style="color:var(--muted);font-size:12px">How many days after completion a job can be reopened. 0 = never.</span>
        @error('reopen_window_days') <span style="color:var(--red);font-size:12px">{{ $message }}</span> @enderror
    </div>

    <div style="margin-bottom:20px">
        <label style="display:block;font-weight:700;font-size:13px;margin-bottom:6px">Feedback link validity (days)</label>
        <input type="number" name="feedback_link_days" min="1" max="90"
               value="{{ old('feedback_link_days', $setting->feedback_link_days ?? 7) }}"
               style="height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;width:100%">
    </div>

    <div style="margin-bottom:20px">
        <label style="display:block;font-weight:700;font-size:13px;margin-bottom:6px">Client sign-in link validity (hours)</label>
        <input type="number" name="client_sign_in_link_hours" min="1" max="168"
               value="{{ old('client_sign_in_link_hours', $setting->client_sign_in_link_hours ?? 72) }}"
               style="height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;width:100%">
    </div>

    <div style="margin-bottom:20px;display:flex;align-items:center;gap:10px">
        <input type="checkbox" name="require_two_factor" id="require_two_factor" value="1"
               {{ old('require_two_factor', $setting->require_two_factor ?? false) ? 'checked' : '' }}>
        <label for="require_two_factor" style="font-weight:600;font-size:13.5px">Require two-factor authentication for all staff</label>
    </div>

    <button type="submit" style="background:var(--yellow);color:var(--ink);font-weight:700;font-size:14px;padding:12px 22px;border-radius:999px;border:0;cursor:pointer">
        Save changes
    </button>
</form>
@endsection