{{--
    resources/views/layouts/_public_brand.blade.php
    The company's own Branding colour and font for the stand-alone pages a client opens (quote and invoice respond pages, feedback,
    the client portal and its sign-in). Put it LAST inside <head>, after the page's own <style>:
        @include('layouts._public_brand', ['brandTenant' => $tenantOrId])
    Without brandTenant it uses the company of the address, else the Sage default. Same rules as emails (EmailContent::brand).
    The page's own CSS uses var(--brand), var(--brand-ink) and var(--brand-soft) instead of a fixed colour.
--}}
@php
    $pbSource = $brandTenant ?? \App\Models\Tenant::current();
    $pb       = \App\Services\Notifications\EmailContent::brand($pbSource);
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family={{ $pb['font_param'] }}&display=swap" rel="stylesheet">
<style id="aiywah-public-brand">
:root{--brand:{{ $pb['accent'] }};--brand-ink:{{ $pb['ink'] }};--brand-soft:{{ $pb['soft'] }};--accent:{{ $pb['accent'] }};--font-body:{!! $pb['font_body'] !!},system-ui,sans-serif;--font-display:{!! $pb['font_display'] !!},system-ui,sans-serif}
body,button,input,select,textarea,.hdr .co .addr{font-family:var(--font-body)!important}
h1,h2,h3,h4,.co,.hdr .co,.me,.totals .total,.ph h3{font-family:var(--font-display),var(--font-body)!important}
</style>
