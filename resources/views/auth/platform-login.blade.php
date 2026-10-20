<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Platform sign-in · Aiywah FSM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap">
<style>
    :root { --bg:#F5F4FA; --surface:#FFFFFF; --soft:#F5F4FA; --ink:#1B1A24; --muted:#5F5D6E; --line:#E6E4EE; --accent:#2F5BEA; --accent-ink:#FFFFFF; --danger:#DC3E36; color-scheme: light; }
    *,*::before,*::after { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: var(--bg); color: var(--ink); font: 15px/1.5 "DM Sans", system-ui, sans-serif; }
    .card { background: var(--surface); border: 1px solid var(--line); border-radius: 20px; padding: 36px; width: 100%; max-width: 420px; }
    .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
    .brand .logo { width: 40px; height: 40px; border-radius: 10px; background: var(--ink); color: var(--accent); display: grid; place-items: center; font: 700 18px "Figtree", sans-serif; }
    .brand span { font: 700 18px "Figtree", sans-serif; }
    h1 { font: 700 26px "Figtree", sans-serif; letter-spacing: -.4px; margin: 0 0 6px; }
    .lede { color: var(--muted); font-size: 14px; margin: 0 0 24px; }
    .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; font-size: 13px; font-weight: 700; }
    .field input { height: 46px; padding: 0 14px; border: 1px solid var(--line); border-radius: 10px; font: 400 14px inherit; background: var(--surface); color: var(--ink); }
    .field input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 25%, transparent); }
    .btn { display: block; width: 100%; background: var(--accent); color: var(--accent-ink); font: 700 15px inherit; padding: 13px; border-radius: 999px; border: 0; cursor: pointer; }
    .btn:hover { filter: brightness(.96); }
    .err { background: color-mix(in srgb, var(--danger) 15%, var(--surface)); color: var(--danger); padding: 10px 14px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; font-weight: 500; }
    .demo { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); line-height: 1.6; }
    .demo code { background: var(--soft); padding: 2px 6px; border-radius: 4px; font-family: "JetBrains Mono", Consolas, monospace; color: var(--ink); }
</style>
</head>
<body>
<form method="POST" action="{{ route('platform.login.attempt') }}" class="card" autocomplete="on">
    @csrf
    <div class="brand">
        <span class="logo">A</span>
        <span>Aiywah FSM</span>
    </div>
    <h1>Sign in</h1>
    <p class="lede">Aiywah staff console.</p>

    @if ($errors->any())
        <div class="err">Email, phone or password is incorrect.</div>
    @endif

    <label class="field">Email
        <input type="email" name="identifier" value="{{ old('identifier') }}" autocomplete="email" autofocus required>
    </label>
    <label class="field">Password
        <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <button type="submit" class="btn">Continue</button>

    @if (app()->environment('local'))
        <div class="demo">
            Demo platform admin:<br>
            <code>owner@aiywah.app</code> &middot; <code>Aiywah#2026</code>
        </div>
    @endif
</form>
</body>
</html>