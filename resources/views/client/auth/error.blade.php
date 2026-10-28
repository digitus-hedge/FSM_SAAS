<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign-in error — {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{width:100%;max-width:460px;background:#fff;border:1px solid #E6E4EE;border-radius:24px;padding:40px 32px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.06)}
        .ico{width:60px;height:60px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:18px}
        .ico svg{width:28px;height:28px}
        .ico.expired{background:#FFF4CC;color:#8A5A00}
        .ico.used{background:#E3EBF4;color:#2B5A8C}
        .ico.starter{background:#FDECE7;color:#C2381B}
        .ico.invalid{background:#FDECE7;color:#C2381B}
        h1{font-family:'Figtree';font-size:22px;font-weight:800;margin-bottom:10px}
        p{color:#5F5D6E;font-size:14px;line-height:1.6;margin-bottom:22px}
        p b{color:#1B1A24;font-weight:700}
        .act{display:inline-flex;align-items:center;gap:6px;background:#FFC61A;color:#1B1A24;padding:12px 24px;border-radius:999px;font-weight:700;font-size:14px;text-decoration:none}
        .act:hover{background:#F0B700}
    </style>
</head>
<body>
    <div class="card">
        @if ($errorKey === 'expired')
            <div class="ico expired"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <h1>This link has expired</h1>
            <p>Sign-in links are valid for 24 hours.{{ isset($sentAt) ? ' This one was sent on ' . $sentAt->format('d M') . '.' : '' }}</p>
            <a href="{{ route('client.sign-in') }}" class="act">Request a new link</a>

        @elseif ($errorKey === 'used')
            <div class="ico used"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <h1>This link was already used</h1>
            <p>For your security, each sign-in link works only once.{{ isset($usedAt) ? ' Last sign-in: ' . $usedAt->format('d M, H:i') . '.' : '' }}</p>
            <a href="{{ route('client.sign-in') }}" class="act">Request a new link</a>

        @elseif ($errorKey === 'starter')
            <div class="ico starter"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
            <h1>Client sign-in isn't available</h1>
            <p>This company's plan doesn't include client sign-in. Ask your contact at <b>{{ $tenant->name }}</b> — they'll send you links to answer quotes and view invoices by email.</p>
            @if ($tenant->contact_email)
                <a href="mailto:{{ $tenant->contact_email }}" class="act">Email your contact</a>
            @endif

        @else
            <div class="ico invalid"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
            <h1>This link isn't valid</h1>
            <p>The link you clicked may be incomplete or no longer active.</p>
            <a href="{{ route('client.sign-in') }}" class="act">Request a new link</a>
        @endif
    </div>
</body>
</html>