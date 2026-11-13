<!doctype html>
<html>
<body style="font-family:Arial,Helvetica,sans-serif;color:#1B1A24;font-size:14px;line-height:1.5">
  <h2 style="font-size:17px;margin:0 0 10px">{{ $n->title }}</h2>
  @if ($n->body)<p style="margin:0 0 16px">{{ $n->body }}</p>@endif
  @if ($n->url)
    <p><a href="{{ \Illuminate\Support\Str::startsWith($n->url, '/') ? url($n->url) : $n->url }}" style="background:#FFC61A;color:#1B1A24;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:999px;display:inline-block">Open</a></p>
  @endif
  <p style="color:#8B89A0;font-size:12px;margin-top:24px">You receive this message because of your role in AIYWAH FSM.</p>
</body>
</html>
