<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Feedback — {{ $company?->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:15px;line-height:1.5}
.wrap{max-width:520px;margin:0 auto;padding:60px 20px}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;padding:28px;text-align:center}
h1{font-family:'Figtree',sans-serif;font-size:22px;font-weight:800;margin-bottom:8px}
p{color:#5F5D6E}
</style>
@include('layouts._public_brand', ['brandTenant' => $company])
</head>
<body>
<div class="wrap">
    <div class="card">
        @if ($state === 'thanks')
            <h1>Thank you</h1>
            <p>Your feedback on {{ $sr->sr_number }} was received. We appreciate it.</p>
        @elseif ($state === 'used')
            <h1>Feedback already received</h1>
            <p>This link has already been used to give feedback on {{ $sr->sr_number }}. Each link works once, so there is nothing more to do. Thank you.</p>
        @else
            <h1>This link has expired</h1>
            <p>The link to give feedback on {{ $sr->sr_number }} is no longer valid. Please contact {{ $company?->name ?? 'us' }} if you would still like to share your feedback.</p>
        @endif
    </div>
</div>
</body>
</html>
