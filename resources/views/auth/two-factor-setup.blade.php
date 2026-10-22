@extends('layouts.admin')
@section('title', 'Two-factor sign-in')
@section('content')
    <div style="max-width:520px;margin:40px auto;background:#fff;border:1px solid #E6E4EE;border-radius:16px;padding:36px">
        <h1 style="font:700 24px 'Figtree';letter-spacing:-.3px;margin-bottom:6px">Two-factor sign-in</h1>
        <p style="color:#5F5D6E;margin-bottom:24px;font-size:14px">
            Scan this with an authenticator app (Google Authenticator, 1Password, Authy), then enter the 6-digit code to confirm.
        </p>

        <div style="text-align:center;background:#F5F4FA;border-radius:12px;padding:20px;margin-bottom:16px">
            <img src="{{ $qrSvg }}" alt="QR code" style="width:200px;height:200px">
        </div>

        <p style="font-size:12px;color:#5F5D6E;margin-bottom:8px">Can't scan? Enter this key manually:</p>
        <code style="display:block;background:#F5F4FA;padding:10px 14px;border-radius:8px;font-family:'JetBrains Mono',Consolas,monospace;font-size:13px;margin-bottom:24px;word-break:break-all">{{ $secret }}</code>

        <form method="POST" action="{{ route('two-factor.confirm') }}">
            @csrf
            <label style="display:block;margin-bottom:14px">
                <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">6-digit code</span>
                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required
                       style="width:100%;height:48px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:20px;letter-spacing:.5em;text-align:center;font-family:'JetBrains Mono',Consolas,monospace">
                @error('code') <span style="color:#C2381B;font-size:12px;display:block;margin-top:4px">{{ $message }}</span> @enderror
            </label>

            <button type="submit" style="width:100%;background:#FFC61A;color:#1B1A24;border:0;padding:13px;border-radius:999px;font-weight:700;font-size:14px;cursor:pointer">
                Turn on two-factor
            </button>
        </form>
    </div>
@endsection