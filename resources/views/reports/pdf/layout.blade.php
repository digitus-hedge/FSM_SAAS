<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  body{font-family:DejaVu Sans, sans-serif;font-size:11px;color:#222}
  .head{border-bottom:3px solid {{ $accent }};padding-bottom:8px;margin-bottom:14px}
  .head img{max-height:48px;float:right}
  h1{font-size:18px;margin:0;color:{{ $accent }}}
  .sub{color:#666;font-size:10px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th{background:#f3f3f3;text-align:left;padding:5px;border:1px solid #ddd;font-size:10px}
  td{padding:5px;border:1px solid #ddd}
  .foot{margin-top:18px;font-size:9px;color:#888}
</style>
</head>
<body>
<div class="head">
  @if ($branded && $logo)<img src="{{ $logo }}" alt="">@endif
  <h1>{{ $title }}</h1>
  <div class="sub">@if ($branded){{ $company }} · @endif{{ $generatedAt }}</div>
</div>
@yield('body')
<div class="foot">Generated {{ $generatedAt }}</div>
</body>
</html>
