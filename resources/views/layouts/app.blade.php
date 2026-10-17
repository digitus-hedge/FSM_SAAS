<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AIYWAH FSM')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:15px;line-height:1.5;padding:40px 24px}
        .wrap{max-width:960px;margin:0 auto}
        h1,h2,h3{font-family:'Figtree',sans-serif;letter-spacing:-.4px}
        h1{font-size:34px;font-weight:700;margin-bottom:8px}
        h2{font-size:22px;font-weight:700;margin-bottom:12px}
        p.lede{color:#5F5D6E;margin-bottom:28px}
        .card{background:#fff;border:1px solid #E6E4EE;border-radius:16px;padding:22px;margin-bottom:16px}
        .btn{display:inline-flex;align-items:center;gap:8px;background:#FFC61A;color:#1B1A24;font-weight:700;font-size:14px;padding:11px 20px;border-radius:999px;border:0;cursor:pointer;text-decoration:none}
        .btn.ghost{background:transparent;border:1px solid #E6E4EE;color:#1B1A24}
        .field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;font-weight:700;font-size:13px}
        .field input{height:44px;padding:0 14px;border:1px solid #E6E4EE;border-radius:10px;font:inherit;font-weight:400;font-size:14px}
        .field input:focus{outline:none;border-color:#FFC61A;box-shadow:0 0 0 3px #FFF4CC}
        .flash{background:#DDF7F1;color:#00866F;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-weight:600}
        .err{color:#C2381B;font-size:12px;margin-top:4px;font-weight:400}
        .pill{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;background:#DDF7F1;color:#00866F;text-transform:uppercase;letter-spacing:.5px}
        .pill.suspended{background:#FDECE7;color:#C2381B}
        .tenant-row{display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid #E6E4EE}
        .tenant-row:last-child{border-bottom:0}
        .tenant-row a{color:#1B1A24;font-weight:700;text-decoration:none}
        .tenant-row a:hover{color:#8A5A00}
        .tenant-row small{color:#5F5D6E;font-weight:400;font-size:13px}
        .brand{display:inline-flex;align-items:center;gap:10px;margin-bottom:30px}
        .brand .logo{width:36px;height:36px;border-radius:10px;background:#1B1A24;color:#FFC61A;display:flex;align-items:center;justify-content:center;font-weight:700}
        .brand span{font-family:'Figtree';font-size:18px;font-weight:700}
        .row{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;gap:16px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <span class="logo">A</span>
        <span>AIYWAH FSM</span>
    </div>

    @if (session('success'))
        <div class="flash">{{ session('success') }}</div>
    @endif

    @yield('content')
</div>
</body>
</html>