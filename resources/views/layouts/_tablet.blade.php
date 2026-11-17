{{--
    resources/views/layouts/_tablet.blade.php
    Tablet layout for the technician / team-lead mobile app (layouts/technician.blade.php).
    Include it AFTER the layout's own <style> block and after _theme / _brand_font:
        @include('layouts._tablet')
    Phones (under 600px) and wide screens (1100px and up) are not touched.
--}}
<style id="aiywah-tablet">
@media (min-width:600px) and (max-width:1099px){
  /* The app fills the screen instead of a 480px column / centred phone card. */
  html,body{font-size:17px}
  body{display:block!important;min-height:100dvh;padding:0!important}
  .app{max-width:none!important;height:100dvh!important;border-radius:0!important;border:0!important;box-shadow:none!important;margin:0}

  /* Content is centred and kept to a comfortable reading width (900px). */
  .hdr{padding-left:28px;padding-right:28px;min-height:64px}
  .main{padding:24px max(28px,calc((100% - 900px) / 2)) 120px}
  .nav{padding-left:max(0px,calc((100% - 720px) / 2));padding-right:max(0px,calc((100% - 720px) / 2))}
  .nav a{min-height:68px;font-size:13px}

  /* Roomier touch targets and type. */
  .btn{min-height:56px}
  .btn.sm{min-height:46px}
  .inp,.sel{min-height:54px}
  .card{padding:18px}
  .card h3{font-size:18px}
  .big{font-size:46px}
  .grid3{grid-template-columns:repeat(4,1fr)}
  .pad{height:300px}

  /* Lists of tappable cards (jobs, team) go two across. Only when there are 2 or more,
     so a single detail card is never squeezed into half the width. */
  .main:has(> .card.tap ~ .card.tap){display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-content:start}
  .main:has(> .card.tap ~ .card.tap) > :not(.card.tap){grid-column:1 / -1}
  .main:has(> .card.tap ~ .card.tap) > .card.tap{margin:0}

  /* Bottom sheets become centred dialogs (the side menu is left alone). */
  .sheetwrap:not(#drawer-wrap){align-items:center!important;justify-content:center!important;padding:24px}
  .sheetwrap:not(#drawer-wrap) .sheet{width:100%;max-width:560px;border-radius:24px;max-height:86%}
  .sheetwrap:not(#drawer-wrap) .sheet .grab{display:none}

  /* Toast sits centred above the bottom bar. */
  .toast{left:50%;right:auto;transform:translateX(-50%);width:min(560px,calc(100% - 56px))}
}
</style>
