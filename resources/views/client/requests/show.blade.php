<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $sr->sr_number }} — {{ \App\Models\Tenant::current()->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.wrap{max-width:640px;margin:0 auto;padding:28px 20px 60px}
.back{color:#5F5D6E;font-size:13px;text-decoration:none;font-weight:600;display:inline-block;margin-bottom:14px}
.num{font-family:monospace;font-weight:700;font-size:12px;background:#F5F4FA;padding:4px 10px;border-radius:5px;color:#8A5A00;display:inline-block;margin-bottom:8px}
h1{font-family:'Figtree',sans-serif;font-size:22px;font-weight:800;margin-bottom:10px}
.pill{display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:20px}
.p-pending{background:#FFF4CC;color:#8A5A00}
.p-quoted{background:#EDE9FE;color:#5B3BB8}
.p-approved,.p-assigned,.p-in-progress{background:#E3EBF4;color:#2B5A8C}
.p-pending-review,.p-awaiting-invoice{background:#FFF0D1;color:#8A5A00}
.p-completed{background:#DDF7F1;color:#00866F}
.p-cancelled{background:#F5F4FA;color:#5F5D6E}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;padding:20px;margin-bottom:14px}
.card h3{font-family:'Figtree',sans-serif;font-size:14px;font-weight:700;margin-bottom:10px}
.card p{color:#1B1A24;font-size:13.5px;white-space:pre-wrap}
.meta{color:#5F5D6E;font-size:12.5px;margin-bottom:4px}
.photos{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.photos img{width:84px;height:84px;object-fit:cover;border-radius:8px;border:1px solid #E6E4EE}
.actions{display:flex;gap:10px}
.btn{display:inline-flex;background:#FFC61A;color:#1B1A24;font-weight:700;padding:10px 18px;border-radius:999px;text-decoration:none;font-size:13.5px;border:0;cursor:pointer;font-family:inherit}
.btn.outline{background:#fff;border:1px solid #E6E4EE;color:#1B1A24}
.btn.red{background:#FDECE7;color:#C2381B}
</style>
</head>
<body>
<div class="wrap">
    <a href="{{ route('portal.requests.index') }}" class="back">← Your requests</a>

    @if (session('success'))
        <div style="background:#DDF7F1;color:#00866F;padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:13px">{{ session('success') }}</div>
    @endif

    <div class="num">{{ $sr->sr_number }}</div>
    <h1>{{ $sr->title }}</h1>
    <span class="pill p-{{ str_replace('_','-',$sr->status) }}">{{ $sr->statusLabel() }}</span>

    <div class="card">
        <h3>Details</h3>
        <div class="meta">Site: {{ $sr->site?->name }}</div>
        <div class="meta">Category: {{ $sr->category?->name }}</div>
        <div class="meta">Raised: {{ $sr->created_at->format('d M Y, H:i') }}</div>
    </div>

    <div class="card">
        <h3>Description</h3>
        <p>{{ $sr->description }}</p>

        @php
            $clientPhotos = $sr->attachmentsRel()->where('kind', 'sr_attachment')->get();
        @endphp
        @if ($clientPhotos->isNotEmpty())
            <div class="photos">
                @foreach ($clientPhotos as $p)
                    <a href="{{ $p->url }}" target="_blank"><img src="{{ $p->url }}" alt=""></a>
                @endforeach
            </div>
        @endif
    </div>

    @if ($canEdit)
        <div class="card">
            <h3>Edit while Pending</h3>
            <form method="POST" action="{{ route('portal.requests.update', $sr) }}" style="margin-bottom:12px">
                @csrf @method('PUT')
                <textarea name="description" rows="4" style="width:100%;padding:10px;border:1px solid #E6E4EE;border-radius:10px;font:inherit;margin-bottom:10px">{{ old('description', $sr->description) }}</textarea>
                <button type="submit" class="btn">Save changes</button>
            </form>
            <form method="POST" action="{{ route('portal.requests.cancel', $sr) }}" onsubmit="return confirm('Cancel this request?')">
                @csrf
                <button type="submit" class="btn red">Cancel request</button>
            </form>
        </div>
    @endif
</div>
</body>
</html>