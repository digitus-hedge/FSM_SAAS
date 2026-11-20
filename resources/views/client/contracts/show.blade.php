<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $contract->contract_number }} — {{ \App\Models\Tenant::current()->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.wrap{max-width:720px;margin:0 auto;padding:28px 20px 60px}
.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
h1{font-family:'Figtree',sans-serif;font-size:22px;font-weight:800}
.back{color:#5F5D6E;font-size:13px;text-decoration:none;font-weight:600}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;overflow:hidden;margin-bottom:16px}
.kv{display:grid;grid-template-columns:200px 1fr;gap:10px;padding:12px 18px;border-top:1px solid #E6E4EE}
.kv:first-child{border-top:0}
.kv dt{color:#5F5D6E}
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>{{ $contract->contract_number }}</h1>
        <a href="{{ route('portal.contracts.index') }}" class="back">← My contracts</a>
    </div>
    @php
        $remaining = $contract->max_calls ? max(0, $contract->max_calls - $contract->calls_used_current_period) : null;
        $minutes = fn ($m) => $m ? ($m >= 60 ? rtrim(rtrim(number_format($m / 60, 1), '0'), '.') . ' hours' : $m . ' minutes') : 'Not set';
    @endphp
    <div class="card">
        <dl>
            <div class="kv"><dt>Type</dt><dd>{{ $contract->type_label }}</dd></div>
            <div class="kv"><dt>Status</dt><dd>{{ ucfirst(str_replace('_', ' ', $contract->status)) }}</dd></div>
            <div class="kv"><dt>Starts</dt><dd>{{ $contract->start_date->format('j M Y') }}</dd></div>
            <div class="kv"><dt>Ends</dt><dd>{{ $contract->end_date->format('j M Y') }}</dd></div>
            <div class="kv"><dt>Covered sites</dt><dd>{{ $contract->sites->isEmpty() ? 'All your sites' : $contract->sites->pluck('name')->join(', ') }}</dd></div>
            <div class="kv"><dt>Covered categories</dt><dd>{{ $contract->categories->isEmpty() ? 'All categories' : $contract->categories->pluck('name')->join(', ') }}</dd></div>
            <div class="kv"><dt>Response time</dt><dd>{{ $minutes($contract->response_sla_minutes) }}</dd></div>
            <div class="kv"><dt>Resolution time</dt><dd>{{ $minutes($contract->resolution_sla_minutes) }}</dd></div>
            <div class="kv"><dt>Calls used</dt><dd>{{ $contract->calls_used_current_period }}@if ($contract->max_calls) of {{ $contract->max_calls }} @endif</dd></div>
            <div class="kv"><dt>Calls remaining</dt><dd>{{ $remaining === null ? 'No call limit' : $remaining }}</dd></div>
        </dl>
    </div>
</div>
</body>
</html>
