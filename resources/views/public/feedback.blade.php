<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Your feedback — {{ $company?->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#FBFAF6;color:#1B1A24;font-family:'DM Sans',system-ui,sans-serif;font-size:15px;line-height:1.5}
.wrap{max-width:560px;margin:0 auto;padding:32px 20px 60px}
h1{font-family:'Figtree',sans-serif;font-size:24px;font-weight:800;margin-bottom:4px}
.sub{color:#5F5D6E;margin-bottom:22px}
.card{background:#fff;border:1px solid #E6E4EE;border-radius:14px;padding:20px;margin-bottom:16px}
.card h2{font-size:15px;margin-bottom:10px}
.row{display:flex;flex-wrap:wrap;gap:8px}
.row label{display:inline-flex;align-items:center;justify-content:center;min-width:44px;height:44px;border:1px solid #D9D7E4;border-radius:10px;cursor:pointer;font-weight:700;background:#fff}
.row input{position:absolute;opacity:0;width:1px;height:1px}
.row input:checked + span{background:#FFC61A;border-radius:9px}
.row label span{display:flex;align-items:center;justify-content:center;width:100%;height:100%;border-radius:9px}
.ends{display:flex;justify-content:space-between;color:#8B89A0;font-size:12px;margin-top:6px}
textarea{width:100%;border:1px solid #D9D7E4;border-radius:10px;padding:10px;font:inherit;min-height:100px}
.btn{background:#8A5A00;color:#fff;border:0;border-radius:10px;padding:13px 26px;font-weight:700;font-size:15px;cursor:pointer}
.err{color:#B42318;font-size:13px;margin-top:6px}
</style>
</head>
<body>
<div class="wrap">
    <h1>How did we do?</h1>
    <p class="sub">{{ $sr->sr_number }} — {{ $sr->title }}<br>{{ $company?->name }}</p>

    <form method="POST" action="{{ route('public.feedback.store', $token) }}">
        @csrf
        <div class="card">
            <h2>Your rating <span style="color:#B42318">*</span></h2>
            <div class="row">
                @foreach (range(1, 5) as $i)
                    <label><input type="radio" name="stars" value="{{ $i }}" @checked((int) old('stars') === $i)><span>{{ $i }} ★</span></label>
                @endforeach
            </div>
            @error('stars')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="card">
            <h2>How likely are you to recommend us to a colleague? <span style="color:#B42318">*</span></h2>
            <div class="row">
                @foreach (range(0, 10) as $i)
                    <label><input type="radio" name="score" value="{{ $i }}" @checked(old('score') !== null && (int) old('score') === $i)><span>{{ $i }}</span></label>
                @endforeach
            </div>
            <div class="ends"><span>0 = not at all likely</span><span>10 = extremely likely</span></div>
            @error('score')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="card">
            <h2>Anything you would like to add? (optional)</h2>
            <textarea name="comment" maxlength="2000">{{ old('comment') }}</textarea>
            @error('comment')<div class="err">{{ $message }}</div>@enderror
        </div>

        <button class="btn" type="submit">Send my feedback</button>
    </form>
</div>
</body>
</html>
