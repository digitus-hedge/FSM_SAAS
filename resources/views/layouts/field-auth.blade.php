<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FFFFFF">
<title>@yield('title', 'Sign in') — {{ $tenant->name ?? 'AIYWAH FSM' }}</title>
@php
    $brandFont   = \App\Models\BrandFont::where('key', $tenant->font_key ?? 'dm_sans')->first();
    $googleParam = $brandFont->google_param ?? 'DM+Sans:wght@400;500;700&family=Figtree:wght@700;800';
    $fontBody    = $brandFont->body_family ?? 'DM Sans';
    $fontDisplay = $brandFont->display_family ?? 'Figtree';
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family={{ $googleParam }}&display=swap" rel="stylesheet">
@include('technician._design_system', [
    'accentHex'   => $accentHex ?? ($tenant->primary_color ?? '#FFC61A'),
    'fontBody'    => $fontBody,
    'fontDisplay' => $fontDisplay,
])
<style>body{display:flex;justify-content:center;padding:0}.authwrap{max-width:420px;margin:0 auto;min-height:100dvh;background:var(--bg);display:flex;flex-direction:column;padding:0 18px}</style>
</head>
<body>
<div class="authwrap">
  <form method="POST" action="{{ $action }}" style="padding-top:10px">
    @csrf
    @yield('body')
  </form>
</div>
</body>
</html>