<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign in — {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .phone{width:100%;max-width:420px;background:#fff;border:1px solid #E6E4EE;border-radius:28px;padding:36px 28px;box-shadow:0 10px 40px rgba(0,0,0,.06)}
        .brand{text-align:center;margin-bottom:28px}
        .logo{width:52px;height:52px;border-radius:14px;background:{{ $tenant->primary_color ?? '#FFC61A' }};color:#1B1A24;display:inline-flex;align-items:center;justify-content:center;font-family:'Figtree';font-weight:800;font-size:22px;margin-bottom:12px}
        .co{font-family:'Figtree';font-weight:800;font-size:18px;letter-spacing:-.2px}
        .sub{color:#5F5D6E;font-size:12.5px;margin-top:2px}
        h1{font-family:'Figtree';font-size:22px;font-weight:800;margin-bottom:6px}
        .lede{color:#5F5D6E;font-size:14px;margin-bottom:24px}
        .fld{margin-bottom:16px}
        .fld label{font-size:12px;font-weight:700;color:#5F5D6E;text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:6px}
        .fld input{width:100%;height:48px;padding:0 16px;border:1px solid #E6E4EE;border-radius:12px;font-family:inherit;font-size:15px;background:#fff;color:#1B1A24}
        .fld input:focus{outline:none;border-color:#FFC61A;box-shadow:0 0 0 3px #FFF4CC}
        .btn{display:flex;align-items:center;justify-content:center;background:#FFC61A;color:#1B1A24;font-weight:700;font-size:15px;padding:14px 24px;border-radius:999px;border:0;cursor:pointer;font-family:inherit;width:100%}
        .btn:hover{background:#F0B700}
        .hint{background:#F5F4FA;border-radius:12px;padding:12px 16px;font-size:12.5px;color:#5F5D6E;margin-top:16px;line-height:1.5}
        .err{background:#FDECE7;border:1px solid #C2381B;color:#8A2718;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:14px}
    </style>
</head>
<body>
    <div class="phone">
        <div class="brand">
            <div class="logo">{{ strtoupper(substr($tenant->name, 0, 1)) }}</div>
            <div class="co">{{ $tenant->name }}</div>
            <div class="sub">on Aiywah FSM</div>
        </div>

        <h1>Sign in</h1>
        <p class="lede">We'll email you a one-time link. No password to remember.</p>

        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('client.sign-in.request') }}">
            @csrf
            <div class="fld">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" autofocus required>
            </div>
            <button type="submit" class="btn">Send me a sign-in link</button>
        </form>

        <div class="hint">The link works once and expires in 24 hours.</div>
    </div>
</body>
</html>