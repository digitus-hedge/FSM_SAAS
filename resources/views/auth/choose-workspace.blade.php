<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Choose your company · Aiywah FSM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap">
<style>
    :root {
        --bg:#FFFFFF; --surface:#FFFFFF; --soft:#F5F4FA;
        --ink:#1B1A24; --muted:#5F5D6E; --line:#E6E4EE;
        --accent:#5E8A3C; --accent-ink:#FFFFFF;
        color-scheme: light;
    }
    *,*::before,*::after { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center;
           padding: 24px; background: var(--bg); color: var(--ink);
           font: 15px/1.5 "DM Sans", system-ui, sans-serif; }
    .card { background: var(--surface); border: 1px solid var(--line); border-radius: 20px;
            padding: 36px; width: 100%; max-width: 420px; }
    h1 { font: 700 24px "Figtree", sans-serif; letter-spacing: -.4px; margin: 0 0 6px; }
    .lede { color: var(--muted); font-size: 14px; margin: 0 0 20px; }
    form { margin: 0 0 10px; }
    .pick { display: flex; align-items: center; gap: 12px; width: 100%; text-align: left;
            background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            padding: 12px 14px; cursor: pointer; font: inherit; color: inherit; }
    .pick:hover { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent); }
    .pick .logo { width: 38px; height: 38px; border-radius: 10px; background: var(--ink); color: var(--accent);
                  display: grid; place-items: center; font: 700 16px "Figtree", sans-serif; flex: none; overflow: hidden; }
    .pick .logo img { width: 100%; height: 100%; object-fit: contain; }
    .pick b { display: block; font-weight: 700; }
    .pick small { color: var(--muted); font-size: 12.5px; }
    .back { display: inline-block; margin-top: 8px; font-size: 13px; color: var(--muted); text-decoration: none; }
</style>
@include('layouts._theme', ['ctx' => 'auth'])
@include('layouts._brand_font', ['applyBody' => true])
</head>
<body>
<div class="card">
    <h1>Choose your company</h1>
    <p class="lede">This email has access to more than one company. Pick the one you want to open.</p>

    @foreach ($users as $u)
        <form method="POST" action="{{ route('login.choose') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $u->id }}">
            <button type="submit" class="pick">
                <span class="logo">
                    @if (! empty($u->tenant->logo_path))
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($u->tenant->logo_path) }}" alt="">
                    @else
                        {{ strtoupper(substr($u->tenant->name, 0, 1)) }}
                    @endif
                </span>
                <span>
                    <b>{{ $u->tenant->name }}</b>
                    <small>{{ $u->role?->name ?? 'Member' }}</small>
                </span>
            </button>
        </form>
    @endforeach

    <a class="back" href="{{ route('central.login') }}">Use a different email</a>
</div>
</body>
</html>