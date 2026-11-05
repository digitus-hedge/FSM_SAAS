<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change password — {{ \App\Models\Tenant::current()?->name ?? config('app.name') }}</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'Figtree',system-ui,-apple-system,sans-serif;background:#FAF9F5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{width:100%;max-width:480px;background:#fff;border:1px solid #E6E4EE;border-radius:16px;padding:36px}
h1{font:700 24px/1.25 'Figtree',sans-serif;letter-spacing:-.3px;margin:0 0 6px}
p{margin:0 0 24px;color:#5F5D6E;font-size:14px}
label{display:block;margin-bottom:14px}
label span{display:block;font-weight:600;font-size:13px;margin-bottom:6px}
input{width:100%;height:44px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font-size:14px;font-family:inherit}
input:focus{outline:none;border-color:#FFC61A;box-shadow:0 0 0 3px rgba(255,198,26,.18)}
.err{color:#C2381B;font-size:12px;display:block;margin-top:4px}
.hint{color:#5F5D6E;font-size:12px;margin-bottom:16px}
button{width:100%;background:#FFC61A;color:#1B1A24;border:0;padding:13px;border-radius:999px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit}
button:hover{filter:brightness(.97)}
</style>
</head>
<body>
    <div class="card">
        <h1>Choose a new password to continue</h1>
        <p>Your admin set a temporary password. Replace it before you can use the app.</p>

        <form method="POST" action="{{ route('password.change.submit') }}">
            @csrf

            @foreach (['current' => 'Current (temporary) password', 'new' => 'New password', 'repeat' => 'Repeat new password'] as $field => $label)
                <label>
                    <span>{{ $label }}</span>
                    <input type="password" name="{{ $field }}" required>
                    @error($field)
                        <span class="err">{{ $message }}</span>
                    @enderror
                </label>
            @endforeach

            <p class="hint">Use at least 10 characters. Avoid passwords you have used before.</p>

            <button type="submit">Change password and continue</button>
        </form>
    </div>
</body>
</html>