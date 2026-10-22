<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Two-factor code</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap">
<style>
    body{margin:0;min-height:100vh;background:#F5F4FA;color:#1B1A24;font:15px/1.5 "DM Sans",system-ui,sans-serif;display:grid;place-items:center;padding:24px}
    .card{background:#fff;border:1px solid #E6E4EE;border-radius:20px;padding:36px;width:100%;max-width:400px}
    h1{font:700 24px "Figtree",sans-serif;letter-spacing:-.3px;margin:0 0 6px}
    .lede{color:#5F5D6E;font-size:14px;margin:0 0 24px}
    input{width:100%;height:52px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font:400 24px "JetBrains Mono",Consolas,monospace;letter-spacing:.5em;text-align:center;background:#fff;color:#1B1A24;margin-bottom:14px}
    input:focus{outline:none;border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.15)}
    .btn{display:block;width:100%;background:#FFC61A;color:#1B1A24;font:700 15px inherit;padding:13px;border-radius:999px;border:0;cursor:pointer}
    .err{color:#C2381B;font-size:12px;display:block;margin-top:-10px;margin-bottom:12px}
</style>
</head>
<body>
<form method="POST" action="{{ route('two-factor.verify') }}" class="card" autocomplete="on">
    @csrf
    <h1>Enter your 6-digit code</h1>
    <p class="lede">From your authenticator app.</p>

    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" autofocus required>
    @error('code') <span class="err">{{ $message }}</span> @enderror
    @error('identifier') <span class="err">{{ $message }}</span> @enderror

    <button type="submit" class="btn">Continue</button>
</form>
</body>
</html>