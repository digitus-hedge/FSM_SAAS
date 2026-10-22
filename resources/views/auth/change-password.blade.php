@extends('layouts.admin')
@section('title', 'Change password')
@section('content')
    <div style="max-width:480px;margin:40px auto;background:#fff;border:1px solid #E6E4EE;border-radius:16px;padding:36px">
        <h1 style="font:700 24px 'Figtree';letter-spacing:-.3px;margin-bottom:6px">Choose a new password to continue</h1>
        <p style="color:#5F5D6E;margin-bottom:24px;font-size:14px">
            Your admin set a temporary password. Replace it before you can use the app.
        </p>

        <form method="POST" action="{{ route('password.change.submit') }}">
            @csrf

            @foreach (['current' => 'Current (temporary) password', 'new' => 'New password', 'repeat' => 'Repeat new password'] as $field => $label)
                <label style="display:block;margin-bottom:14px">
                    <span style="display:block;font-weight:600;font-size:13px;margin-bottom:6px">{{ $label }}</span>
                    <input type="password" name="{{ $field }}" required
                           style="width:100%;height:44px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px">
                    @error($field)
                        <span style="color:#C2381B;font-size:12px;display:block;margin-top:4px">{{ $message }}</span>
                    @enderror
                </label>
            @endforeach

            <p style="color:#5F5D6E;font-size:12px;margin-bottom:16px">
                Use at least 10 characters. Avoid passwords you have used before.
            </p>

            <button type="submit" style="width:100%;background:#FFC61A;color:#1B1A24;border:0;padding:13px;border-radius:999px;font-weight:700;font-size:14px;cursor:pointer">
                Change password and continue
            </button>
        </form>
    </div>
@endsection