<!doctype html>
<html lang="en" data-accent="{{ $accent ?? 'blue' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} · {{ $tenant?->name ?? 'Aiywah FSM' }}</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap">
<style>
    /* Tokens from the approved prototype (docs/prototypes/aiywah-tenancy-prototype-minimal.html).
       Default accent: blue. Yellow is the alternative, chosen per tenant via primary_color. */
    :root {
        --bg: #FFFFFF; --surface: #FFFFFF; --soft: #F5F4FA;
        --ink: #1B1A24; --muted: #5F5D6E; --line: #E6E4EE;
        --accent: {{ $accentHex ?? '#2F5BEA' }}; --accent-ink: #FFFFFF;
        color-scheme: light;
    }
    :root[data-accent="yellow"] { --accent: #FFC61A; --accent-ink: #1B1A24; }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            --bg: #13121A; --surface: #1A1924; --soft: #222130;
            --ink: #F3F2F8; --muted: #A9A7B8; --line: #2D2B3C;
            --accent: #7C9CFF; --accent-ink: #0F1220;
            color-scheme: dark;
        }
    }
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center;
           padding: max(24px, env(safe-area-inset-top)) max(24px, env(safe-area-inset-right))
                    max(24px, env(safe-area-inset-bottom)) max(24px, env(safe-area-inset-left));
           background: var(--soft); color: var(--ink);
           font: 15px/1.5 "DM Sans", system-ui, sans-serif; }
    .card { background: var(--surface); border: 1px solid var(--line); border-radius: 20px;
            padding: 36px; width: 100%; max-width: 420px; }
    .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
    .brand .logo { width: 40px; height: 40px; border-radius: 10px; background: var(--ink);
                   color: var(--accent); display: grid; place-items: center;
                   font: 700 18px "Figtree", sans-serif; }
    .brand .logo img { width: 100%; height: 100%; object-fit: contain; border-radius: inherit; }
    .brand span { font: 700 18px "Figtree", sans-serif; }
    h1 { font: 700 26px "Figtree", sans-serif; letter-spacing: -.4px; margin: 0 0 6px; }
    .lede { color: var(--muted); font-size: 14px; margin: 0 0 24px; }
    .field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px;
             font-size: 13px; font-weight: 700; }
    .field input { height: 46px; padding: 0 14px; border: 1px solid var(--line);
                   border-radius: 10px; font: 400 14px inherit; background: var(--bg);
                   color: var(--ink); }
    .field input:focus { outline: none; border-color: var(--accent);
                          box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 25%, transparent); }
    .row { display: flex; justify-content: space-between; align-items: center;
           margin-bottom: 18px; font-size: 13px; color: var(--muted); }
    .row label { display: flex; gap: 6px; align-items: center; font-weight: 500; cursor: pointer; }
    .row a { color: var(--ink); font-weight: 600; text-decoration: underline;
             text-underline-offset: 3px; }
    .btn { display: block; width: 100%; background: var(--accent); color: var(--accent-ink);
           font: 700 15px inherit; padding: 13px; border-radius: 999px; border: 0; cursor: pointer; }
    .btn:hover { filter: brightness(.96); }
    .err { background: color-mix(in srgb, #DC3E36 15%, var(--surface));
           color: #C2381B; padding: 10px 14px; border-radius: 10px;
           margin-bottom: 16px; font-size: 13px; font-weight: 500; }
    .alt { margin-top: 14px; text-align: center; font-size: 13px; color: var(--muted); }
    .demo { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--line);
            font-size: 12px; color: var(--muted); line-height: 1.6; }
    .demo code { background: var(--soft); padding: 2px 6px; border-radius: 4px;
                 font-family: "JetBrains Mono", Consolas, monospace; color: var(--ink); }
</style>
</head>
<body>
<form method="POST" action="{{ $action }}" class="card" autocomplete="on">
    @csrf
    <div class="brand">
        @if ($tenant?->logo_path)
            <span class="logo"><img src="{{ Storage::url($tenant->logo_path) }}" alt=""></span>
        @else
            <span class="logo">{{ substr($tenant?->name ?? 'A', 0, 1) }}</span>
        @endif
        <span>{{ $tenant?->name ?? 'Aiywah FSM' }}</span>
    </div>
    {{ $slot }}
</form>
</body>
</html>