<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up your account</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap">
<style>
    body{margin:0;min-height:100vh;background:#F5F4FA;color:#1B1A24;font:15px/1.5 "DM Sans",system-ui,sans-serif;display:grid;place-items:center;padding:24px}
    .card{background:#fff;border:1px solid #E6E4EE;border-radius:20px;padding:36px;width:100%;max-width:420px}
    h1{font:700 24px "Figtree",sans-serif;letter-spacing:-.3px;margin:0 0 6px}
    .lede{color:#5F5D6E;font-size:14px;margin:0 0 24px}
    label{display:block;margin-bottom:14px;font-size:13px;font-weight:700}
    input{width:100%;height:44px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font:400 14px inherit;background:#fff;margin-top:6px}
    input:focus{outline:none;border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.15)}
    .btn{display:block;width:100%;background:#FFC61A;color:#1B1A24;font:700 15px inherit;padding:13px;border-radius:999px;border:0;cursor:pointer;margin-top:8px}
    .err{color:#C2381B;font-size:12px;display:block;margin-top:4px;font-weight:400}
    .hint{color:#5F5D6E;font-size:12px;margin:10px 0 16px}
</style>
</head>
<body>
<form method="POST" action="{{ route('invitation.accept.submit', $token) }}" class="card">
    @csrf
    <h1>Welcome, {{ $link->user->name }}</h1>
    <p class="lede">Set a password to finish setting up your account.</p>

    <label>New password
        <input type="password" name="new" required>
        @error('new') <span class="err">{{ $message }}</span> @enderror
    </label>
    <label>Repeat new password
        <input type="password" name="repeat" required>
        @error('repeat') <span class="err">{{ $message }}</span> @enderror
    </label>
    <p class="hint">Use at least 10 characters.</p>

    <button type="submit" class="btn">Set password and sign in</button>
</form>
</body>
</html>