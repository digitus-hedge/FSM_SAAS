<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Check your email — {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .phone{width:100%;max-width:420px;background:#fff;border:1px solid #E6E4EE;border-radius:28px;padding:36px 28px;box-shadow:0 10px 40px rgba(0,0,0,.06);text-align:center}
        .tick{width:72px;height:72px;border-radius:50%;background:#DDF7F1;color:#00866F;display:inline-flex;align-items:center;justify-content:center;margin-bottom:20px}
        .tick svg{width:34px;height:34px}
        h1{font-family:'Figtree';font-size:22px;font-weight:800;margin-bottom:6px}
        .lede{color:#5F5D6E;font-size:14px;margin-bottom:20px}
        .lede b{color:#1B1A24;font-weight:700}
        .hint{background:#F5F4FA;border-radius:12px;padding:12px 16px;font-size:12.5px;color:#5F5D6E;line-height:1.5}
        .link{display:inline-block;margin-top:20px;color:#5F5D6E;font-size:13px;font-weight:600;text-decoration:underline}
        .link:hover{color:#1B1A24}
    </style>
</head>
<body>
    <div class="phone">
        <div class="tick">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h1>Check your email</h1>
        <p class="lede">
            If this email is linked to a client contact at <b>{{ $tenant->name }}</b>, we've sent a sign-in link to <b>{{ $email ?: 'the address you entered' }}</b>.
        </p>
        <div class="hint">The link arrives in a minute. Tap the button in that email to sign in. It works once and expires in 24 hours.</div>

        <a href="{{ route('client.sign-in') }}" class="link">Use a different email</a>
    </div>
</body>
</html>