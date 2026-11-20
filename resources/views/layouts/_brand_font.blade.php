{{--
    resources/views/layouts/_brand_font.blade.php
    Applies the tenant's Branding font to any layout. Put it LAST inside <head>:

      office layout (admin)          @include('layouts._brand_font', ['withLink' => false])   // admin already loads the font link
      team-lead desktop              @include('layouts._brand_font')
      login pages (standalone CSS)   @include('layouts._brand_font', ['applyBody' => true])

    It sets --font-body / --font-display, and makes sure the sidebar uses them.
    No Branding font saved (or no tenant, e.g. platform login) -> DM Sans + Figtree.
--}}
@php
    $withLink  = $withLink ?? true;
    $applyBody = $applyBody ?? false;

    $bfTenant  = \App\Models\Tenant::current();
    $bfFont    = \App\Models\BrandFont::where('key', $fontKey ?? $bfTenant?->font_key ?? 'dm_sans')->first();
    $bfParam   = $bfFont->google_param ?? 'DM+Sans:wght@400;500;700&family=Figtree:wght@600;700';
    $bfSafe    = fn ($f) => preg_replace('/[^A-Za-z0-9 ,\'"\-]/', '', (string) $f);   // only safe characters in a CSS font list
    $bfBody    = $bfSafe($bfFont->body_family ?? "'DM Sans'");
    $bfDisplay = $bfSafe($bfFont->display_family ?? "'Figtree'");
@endphp
@if ($withLink)
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family={{ $bfParam }}&display=swap" rel="stylesheet">
@endif
<style id="aiywah-font">
:root{--font-body:{!! $bfBody !!}, system-ui, sans-serif;--font-display:{!! $bfDisplay !!}, system-ui, sans-serif}
@if ($applyBody)
body,button,input,select,textarea{font-family:var(--font-body)}
h1,h2,h3,h4{font-family:var(--font-display),var(--font-body)}
@endif
/* sidebar and mobile menu/header/bar: always the tenant's font (display font for the logo/brand name) */
body .side,body .side :where(a,summary,button,span,div,small,label,b),body .drawer,body .drawer :where(a,button,span,div,small,b),body .hdr,body .hdr :where(button,span,a),body .nav,body .nav a{font-family:var(--font-body),system-ui,sans-serif}
body .side .brand,body .side .brand span,body .side .logo,body .side .logo span{font-family:var(--font-display),var(--font-body),system-ui,sans-serif}
/* numbers line up in tables */
body table{font-variant-numeric:tabular-nums}
</style>
