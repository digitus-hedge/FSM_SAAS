<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — AIYWAH FSM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:20px;padding:36px;width:100%;max-width:420px;box-shadow:0 20px 60px -30px rgba(27,26,36,.1)}
.brand{display:flex;align-items:center;gap:10px;margin-bottom:28px}
.brand .logo{width:40px;height:40px;border-radius:10px;background:#1B1A24;color:#FFC61A;display:flex;align-items:center;justify-content:center;font-family:'Figtree';font-weight:700;font-size:18px}
.brand span{font-family:'Figtree';font-size:18px;font-weight:700}
h1{font-family:'Figtree';font-size:26px;font-weight:700;letter-spacing:-.4px;margin-bottom:6px}
.lede{color:#5F5D6E;font-size:14px;margin-bottom:24px}
.field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;font-size:13px;font-weight:700}
.field input{height:46px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font:inherit;font-weight:400;font-size:14px;background:#fff}
.field input:focus{outline:none;border-color:#FFC61A;box-shadow:0 0 0 3px #FFF4CC}
.row{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;font-size:13px;color:#5F5D6E}
.row label{display:flex;gap:6px;align-items:center;font-weight:500;cursor:pointer}
.btn{display:block;width:100%;background:#FFC61A;color:#1B1A24;font-weight:700;font-size:15px;padding:13px;border-radius:999px;border:0;cursor:pointer;font-family:inherit}
.btn:hover{background:#F2B200}
.err{background:#FDECE7;color:#C2381B;padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:13px;font-weight:500}
.hint{margin-top:18px;padding-top:18px;border-top:1px solid #E6E4EE;font-size:12px;color:#8B89A0;line-height:1.6}
.hint code{background:#F5F4FA;padding:2px 6px;border-radius:4px;font-family:'JetBrains Mono',Consolas,monospace;color:#1B1A24}
</style>
</head>
<body>
<form method="POST" action="{{ route('login.attempt') }}" class="card">
    @csrf
    <div class="brand">
        <span class="logo">A</span>
        <span>AIYWAH FSM</span>
    </div>
    <h1>Sign in</h1>
    <p class="lede">Platform console for managing workspaces.</p>

    @if ($errors->any())
        <div class="err">{{ $errors->first() }}</div>
    @endif

    <label class="field">Email
        <input type="email" name="email" value="{{ old('email') }}" autofocus required>
    </label>

    <label class="field">Password
        <input type="password" name="password" required>
    </label>

    <div class="row">
        <label><input type="checkbox" name="remember" value="1"> Remember me</label>
    </div>

    <button type="submit" class="btn">Sign in</button>

    <div class="hint">
        Seeded platform admin:<br>
        <code>owner@aiywah.app</code> / <code>password</code>
    </div>
</form>
</body>
</html>