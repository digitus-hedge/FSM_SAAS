{{--
    resources/views/layouts/_theme.blade.php
    Aiywah FSM — shared premium theme (v2: Sage default, Mint preset, brand override, theme dock).

    Include it LAST inside <head>, after the layout's own <style> blocks:
        @include('layouts._theme', ['ctx' => 'admin', 'dock' => true])   office layout (shows the theme dock)
        @include('layouts._theme', ['ctx' => 'field', 'dock' => true])    team-lead desktop (floating dock)
        @include('layouts._theme', ['ctx' => 'field', 'dock' => 'slot'])  technician mobile (theme choices inside the side menu: <div id="aw-theme-slot"></div>)
        @include('layouts._theme', ['ctx' => 'auth', 'brandOverride' => $tenant->primary_color ?? null])   login pages

    Colours:
      - No company colour saved in Branding (or only an old code default) -> Sage.
      - A company colour saved in Branding -> every shade is derived from it.
      - The dock switches the colour (Brand/Sage or Mint) and has a separate Dark switch that sits on top of
        whichever colour is active. Pass 'darkReady' => false to hide the Dark switch.
--}}
@php
    $ctx       = $ctx ?? 'admin';
    $dock      = $dock ?? false;
    $dockSlot  = ($dock === 'slot');
    $darkReady = $darkReady ?? true;

    $candidate = $brandOverride ?? ($accentHex ?? ($brandColor ?? \App\Models\Tenant::current()?->primary_color));
    $legacy    = ['#FFC61A', '#E22A6B', '#E11D5C', '#2F5BEA'];   // old code defaults count as "not set"
    $hasBrand  = is_string($candidate)
                 && preg_match('/^#[0-9A-Fa-f]{6}$/', $candidate)
                 && ! in_array(strtoupper($candidate), $legacy, true);
    $brand     = $hasBrand ? $candidate : '#5E8A3C';
    $firstName = $hasBrand ? 'Brand' : 'Sage';
    $firstDot  = $hasBrand ? 'color-mix(in srgb,' . $brand . ' 38%,#fff)' : '#BBD6A3';
@endphp
@if ($dock)
<script>try{var h=document.documentElement,t=localStorage.getItem('fsmTheme'),m=localStorage.getItem('fsmMode');if(t==='mint')h.setAttribute('data-theme','mint');if(m==='dark'&&{{ $darkReady ? 'true' : 'false' }})h.setAttribute('data-mode','dark')}catch(e){}</script>
@endif
<style id="aiywah-theme">
/* ============ 1. Tokens ============ */
:root{
@if ($hasBrand)
  --brand:{{ $brand }};
  --btn:color-mix(in srgb,var(--brand) 38%,#fff);
  --btntx:color-mix(in srgb,var(--brand) 25%,#000);
  --btnsh:color-mix(in srgb,var(--brand) 40%,transparent);
  --bar:color-mix(in srgb,var(--brand) 70%,#fff);
  --acc:color-mix(in srgb,var(--brand) 55%,#000);
  --acct:color-mix(in srgb,var(--brand) 9%,#fff);
  --fill:color-mix(in srgb,var(--brand) 7%,#fff);
  --fill2:color-mix(in srgb,var(--brand) 4%,#fff);
  --track:color-mix(in srgb,var(--brand) 13%,#fff);
  --ln:color-mix(in srgb,var(--brand) 8%,#E6E8EC);
  --field:color-mix(in srgb,var(--brand) 5%,#F3F4F6);
@else
  /* Sage (default) */
  --brand:#5E8A3C;
  --btn:#BBD6A3;--btntx:#26381A;--btnsh:rgba(187,214,163,.55);--bar:#9DC57E;--acc:#5E8A3C;
  --acct:#F6FAF0;--fill:#F1F6EC;--fill2:#F8FBF4;--track:#E9EFE4;--ln:#EEF1EC;--field:#F3F6F0;
@endif
  --shadow:0 2px 14px rgba(31,41,80,.05),0 0 0 1px rgba(31,41,80,.06);
  --shadow-hi:0 10px 30px rgba(31,41,80,.10),0 0 0 1px rgba(31,41,80,.07);
  --ease:cubic-bezier(.2,.8,.2,1);
}

/* ============ 2. Mint preset (chosen in the dock) ============ */
:root[data-theme=mint]{
  --brand:#2F8F7D;
  --btn:#9DD9CE;--btntx:#0F3D34;--btnsh:rgba(157,217,206,.55);--bar:#6CC9B3;--acc:#2F8F7D;
  --acct:#F1FBF8;--fill:#EDF7F4;--fill2:#F6FBF9;--track:#E3F1ED;--ln:#EAF2EF;--field:#F1F7F5;
}

/* ============ 3. Dark mode (layers on top of Brand/Sage or Mint) ============ */
:root[data-mode=dark]{
  color-scheme:dark;
  --bg:#0F1214;--surface:#171B1F;--soft:#1E2328;--ink:#E8ECEF;--muted:#9AA4AD;--subtle:#6F7A84;
  --btn:color-mix(in srgb,var(--brand) 55%,#fff);--btntx:#0F1410;
  --acc:color-mix(in srgb,var(--brand) 55%,#fff);
  --acct:color-mix(in srgb,var(--brand) 18%,#171B1F);
  --fill:color-mix(in srgb,var(--brand) 12%,#171B1F);
  --fill2:color-mix(in srgb,var(--brand) 8%,#171B1F);
  --track:color-mix(in srgb,var(--brand) 20%,#171B1F);
  --ln:#262C32;--field:#1E2328;
  --shadow:0 6px 28px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.05);
  --shadow-hi:0 20px 48px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.08);
}

/* ============ 3b. Dark catch-all: pages that hardcode white ============ */
:root[data-mode=dark] [data-awd]{background-color:var(--surface)!important}
:root[data-mode=dark] [data-awt]{color:var(--ink)!important}
:root[data-mode=dark] [data-awm]{color:var(--muted)!important}
:root[data-mode=dark] [data-awb]{border-color:var(--ln)!important}
:root[data-mode=dark] input:not([type=checkbox]):not([type=radio]):not([type=color]):not([type=range]):not([type=file]):not(.dx-date),
:root[data-mode=dark] select,:root[data-mode=dark] textarea{background-color:var(--field);color:var(--ink);border-color:var(--ln)}
:root[data-mode=dark] ::placeholder{color:var(--subtle)}

@if ($ctx === 'admin')
/* ============ 4A. Office layout vocabulary ============ */
:root{
  --bg:#fff;
  --surface:#fff;
  --ink:#14171F;--muted:#5E6573;--subtle:#8A92A0;
  --line:var(--ln);--soft:var(--fill);
  --yellow:var(--btn);--yellow-soft:var(--acct);--amber:var(--acc);
  --green:#2A9468;--green-soft:#E3F6EC;--red:#C8283A;--red-soft:#FDECEF;
}
:root[data-mode=dark]{--bg:#0F1214;--surface:#171B1F;--ink:#E8ECEF;--muted:#9AA4AD;--subtle:#6F7A84;--soft:#1E2328;--green:#7FD3A2;--green-soft:rgba(110,190,140,.16);--red:#F28A9A;--red-soft:rgba(231,90,110,.16)}
body{background:var(--bg)}

.side{background:var(--surface);border-right:0;box-shadow:4px 0 24px rgba(31,41,80,.07)}
.brand .logo{background:var(--btn);color:var(--btntx);border-radius:12px;box-shadow:0 6px 16px var(--btnsh)}
.nav-label{letter-spacing:.08em;font-weight:700}
.nav-item,.nav-group>summary{border-radius:12px;transition:transform .25s var(--ease),box-shadow .25s,background .2s,color .2s}
.nav-item:hover,.nav-group>summary:hover{background:var(--surface);box-shadow:0 8px 22px rgba(31,41,80,.12);transform:translateY(-2px)}
.nav-item.active,.nav-group.has-active>summary{background:var(--acct);color:var(--acc);font-weight:600}
.nav-item.active::before{display:none}
.nav-item.active:hover{box-shadow:0 10px 24px var(--btnsh)}
.nav-item.active svg,.nav-group.has-active>summary svg.icon{color:var(--acc)}
.avatar{background:var(--btn);color:var(--btntx);box-shadow:0 4px 12px var(--btnsh)}
.account{background:var(--fill);border-radius:14px}
.content{padding-bottom:96px}

body .card{background:var(--surface);border:0;border-radius:18px;box-shadow:var(--shadow);transition:transform .3s var(--ease),box-shadow .3s}
body .card:hover{transform:translateY(-3px);box-shadow:var(--shadow-hi)}
body .card:has(form):hover,body .card:has(table):hover{transform:none;box-shadow:var(--shadow)}
body .card-head,body .modal-foot{background:transparent;border-color:var(--ln)}
:root:not([data-mode=dark]) body .card,:root:not([data-mode=dark]) body .card-head,:root:not([data-mode=dark]) body .card-foot,:root:not([data-mode=dark]) body .card table,:root:not([data-mode=dark]) body .card thead,:root:not([data-mode=dark]) body .card thead th{background:#fff}
body .modal-card{background:var(--surface);border-radius:20px}
body .btn,body .act-btn{border-radius:999px;font-weight:600}
body .btn-primary,body .act-primary{background:var(--btn);color:var(--btntx);border-color:transparent;box-shadow:0 6px 18px var(--btnsh)}
body .btn-primary:hover,body .act-primary:hover{background:var(--btn);filter:brightness(.97);transform:translateY(-1px)}
body .btn-ghost,body .act-neutral{background:var(--surface);color:var(--acc);border-color:transparent;box-shadow:0 2px 10px rgba(31,41,80,.12)}
body .btn-ghost:hover,body .act-neutral:hover{background:var(--surface);box-shadow:0 6px 18px rgba(31,41,80,.14);transform:translateY(-1px)}
body .act-danger-outline{background:var(--surface)}
body .modal-select,body .modal-input,body .note-area{background:var(--field);border-color:transparent;border-radius:12px}
body .modal-select:focus,body .modal-input:focus,body .note-area:focus{background:var(--surface);border-color:var(--btn);box-shadow:0 0 0 3px var(--btnsh)}
body .radio-row:has(input:checked),body .cand:has(input:checked){border-color:var(--btn);background:var(--acct)}
body table th{font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--subtle)}
body table tbody tr{transition:background .2s,box-shadow .25s}
body table tbody tr:hover{background:var(--fill2)}

:root[data-mode=dark] .dx-deep{background:linear-gradient(110deg,color-mix(in srgb,var(--brand) 62%,#000),color-mix(in srgb,var(--brand) 34%,#000))}
:root[data-mode=dark] .dx-hero p b{color:#F0C060}
:root[data-mode=dark] .dx-glance{background:color-mix(in srgb,var(--surface) 70%,transparent)}
:root[data-mode=dark] .dx-chip,:root[data-mode=dark] .dx-dates,:root[data-mode=dark] .dx-b.g{box-shadow:0 0 0 1px rgba(255,255,255,.06)}
:root[data-mode=dark] .dx-seg button.on{box-shadow:0 0 0 1px rgba(255,255,255,.08)}

.swal2-popup{border-radius:20px!important;background:var(--surface)!important}
.swal2-confirm.swal2-styled{background:var(--btn)!important;color:var(--btntx)!important;box-shadow:0 6px 18px var(--btnsh)!important}
.swal-btn-amber.swal2-confirm.swal2-styled{background:var(--btn)!important;color:var(--btntx)!important}
@endif

@if ($ctx === 'field')
/* ============ 4B. Field vocabulary (technician mobile + team-lead desktop) ============ */
/* ':not([data-mode=dark])' also out-ranks the design system's OS-dark rule, so pages follow the dock, not the OS. */
:root:not([data-mode=dark]){
  --bg:#fff;--surface:#fff;--soft:var(--fill);--ink:#14171F;--muted:#5E6573;--line:var(--ln);
  --accent:var(--acc);--accent-ink:#fff;--tint:var(--acct);
  --yellow:var(--btn);--yellow-soft:var(--acct);
  --blue:#2F5BEA;--violet:#7A5AF8;--green:#14A058;--amber:#D98200;--red:#DC3E36;--slate:#64748B;--teal:#0E94A3;--indigo:#4F46E5;--pink:#D6409F;
  color-scheme:light;
}
:root[data-mode=dark]{
  --line:var(--ln);--accent:var(--acc);--accent-ink:#0F1410;--tint:var(--acct);
  --yellow:var(--btn);--yellow-soft:var(--acct);
  --blue:#7C9CFF;--violet:#AB9DFF;--green:#4CD08A;--amber:#F3B14A;--red:#FF8A82;--slate:#9FB0CC;--teal:#4FD1D9;--indigo:#9A94FF;--pink:#F08CCB;
}
html,body{background:var(--bg)}
body .card{border:0;border-radius:18px;box-shadow:var(--shadow);transition:transform .3s var(--ease),box-shadow .3s}
body .btn.primary,body .btn.dark{background:var(--btn);color:var(--btntx);border-color:transparent;box-shadow:0 6px 18px var(--btnsh)}
body .btn.dark:hover,body .btn.primary:hover{filter:brightness(.97)}
body .btn.ghost,body .btn:not(.primary):not(.dark):not(.danger){background:var(--surface);border-color:transparent;box-shadow:0 2px 10px rgba(31,41,80,.10)}
body .logo .mk,body .me .av{background:var(--btn);color:var(--btntx)}
body .navitem{border-radius:12px;transition:transform .25s var(--ease),box-shadow .25s,background .2s}
body .navitem:hover{background:var(--surface);box-shadow:0 8px 22px rgba(31,41,80,.12);transform:translateY(-2px)}
body .navitem.current{background:var(--acct);color:var(--acc)}
body .side{border-right:0;box-shadow:4px 0 24px rgba(31,41,80,.07)}
body .kpi,body .tbl{border-color:transparent}
:root:not([data-mode=dark]) body .card,:root:not([data-mode=dark]) body .kpi,:root:not([data-mode=dark]) body .tbl,:root:not([data-mode=dark]) body .tbl thead,:root:not([data-mode=dark]) body .tbl th,:root:not([data-mode=dark]) body .card-head{background:#fff}
body .kpi.dark{background:var(--btn);color:var(--btntx);border-color:transparent;box-shadow:0 6px 18px var(--btnsh)}
body .kpi.dark span{color:color-mix(in srgb,var(--btntx) 70%,transparent)}
body .frm-select{background:var(--field);border:1px solid transparent;border-radius:10px;color:var(--ink)}
body .wmain{padding-bottom:96px}
body .authwrap .mk{background:var(--btn)!important;color:var(--btntx)!important;box-shadow:0 6px 16px var(--btnsh)}
body .kpi{box-shadow:var(--shadow)}
body .tbl th{font-size:10.5px;letter-spacing:.07em}
body .inp,body .sel,body .txt{background:var(--field);border-color:transparent}
body .inp:focus,body .sel:focus,body .txt:focus{background:var(--surface);border-color:var(--btn);box-shadow:0 0 0 3px var(--btnsh)}
@endif

@if ($ctx === 'auth')
/* ============ 4C. Login pages ============ */
:root{--accent:var(--btn);--accent-ink:var(--btntx)}
body{background:#fff}
body .card{border:0;border-radius:24px;box-shadow:var(--shadow-hi)}
body .brand .logo{background:var(--btn);color:var(--btntx);box-shadow:0 6px 16px var(--btnsh)}
body .field input{background:var(--field);border-color:transparent;border-radius:12px}
body .field input:focus{background:var(--surface);border-color:var(--btn);box-shadow:0 0 0 3px var(--btnsh)}
body .btn{background:var(--btn);color:var(--btntx);box-shadow:0 6px 18px var(--btnsh);font-weight:700}
/* standalone pages (2FA code, set/change password, error pages) */
body input:not([type=checkbox]):not([type=radio]):not([type=hidden]){background:var(--field);border-color:transparent;border-radius:12px}
body input:not([type=checkbox]):not([type=radio]):not([type=hidden]):focus{background:#fff;border-color:var(--btn);box-shadow:0 0 0 3px var(--btnsh);outline:none}
body form button[type=submit]{background:var(--btn);color:var(--btntx);box-shadow:0 6px 18px var(--btnsh)}
@endif

/* ============ 5. Pure white page (light mode) ============ */
:root:not([data-mode=dark]),:root:not([data-mode=dark]) body,:root:not([data-mode=dark]) .main,:root:not([data-mode=dark]) .content,:root:not([data-mode=dark]) .page,:root:not([data-mode=dark]) .wrap,:root:not([data-mode=dark]) .app,:root:not([data-mode=dark]) .shell,:root:not([data-mode=dark]) .layout,:root:not([data-mode=dark]) main,:root:not([data-mode=dark]) .dx{background:#fff!important}

@media (prefers-reduced-motion:reduce){
  body .card,.nav-item,.nav-group>summary,body .navitem,body table tbody tr{transition:none!important}
  body .card:hover,.nav-item:hover,.nav-group>summary:hover,body .navitem:hover{transform:none!important}
}

/* ============ 5. Theme dock ============ */
.aw-dock{position:fixed;left:50%;bottom:22px;transform:translateX(-50%);z-index:60;display:flex;align-items:center;gap:6px;padding:6px 6px 6px 16px;background:var(--surface);border-radius:99px;box-shadow:0 14px 40px rgba(31,41,80,.22),0 0 0 1px rgba(31,41,80,.06);transition:transform .3s var(--ease),box-shadow .3s}
.aw-dock:hover{transform:translateX(-50%) translateY(-3px);box-shadow:0 20px 50px rgba(31,41,80,.28),0 0 0 1px rgba(31,41,80,.08)}
.aw-dock .lb{font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--subtle,#8A92A0);margin-right:4px;display:flex;align-items:center;gap:6px}
.aw-dock .lb svg{width:14px;height:14px;fill:none;stroke:var(--acc);stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.aw-dock button{display:flex;align-items:center;gap:8px;padding:7px 14px 7px 8px;border:0;background:none;border-radius:99px;font:600 12.5px/1 inherit;color:var(--muted,#5E6573);cursor:pointer;transition:background .2s,color .2s,box-shadow .2s,transform .2s}
.aw-dock button:hover{background:var(--fill);transform:translateY(-1px)}
.aw-dock button.on{background:var(--acct);color:var(--acc);box-shadow:inset 0 0 0 1.5px var(--btn)}
.aw-dock .sw2{width:22px;height:22px;border-radius:50%;box-shadow:0 0 0 2px var(--surface),0 0 0 3px rgba(31,41,80,.14);flex:none;position:relative;overflow:hidden}
.aw-dock .sw2:after{content:"";position:absolute;inset:0;background:linear-gradient(135deg,transparent 50%,rgba(255,255,255,.55) 50%)}
.aw-dock .sep{width:1px;height:22px;background:var(--ln);margin:0 4px;flex:none}
.aw-dock .ck{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;display:none;flex:none}
.aw-dock button.on .ck{display:block}
.aw-dock .sw2.dk{background:linear-gradient(135deg,#1B2127 50%,#E9EFE4 50%)}
@media(max-width:640px){.aw-dock{bottom:14px;padding:5px 5px 5px 12px}.aw-dock .lb span{display:none}.aw-dock button{padding:7px 8px}.aw-dock button span{display:none}.aw-dock button.on span{display:inline}.aw-dock .sep{display:none}}

/* ============ 5b. Theme choices inside a menu (mobile) ============ */
.aw-slot{padding:8px 4px 10px;border-bottom:1px solid var(--ln);margin-bottom:6px}
.aw-slot .slb{font-size:11.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);padding:6px 12px 8px}
.aw-slot .slrow{display:flex;flex-wrap:wrap;gap:8px;padding:0 8px}
.aw-slot button{display:flex;align-items:center;gap:8px;min-height:44px;padding:0 14px 0 9px;border:0;border-radius:99px;background:var(--soft);color:var(--ink);font:600 13.5px/1 inherit;cursor:pointer}
.aw-slot button.on{background:var(--acct);color:var(--acc);box-shadow:inset 0 0 0 1.5px var(--btn)}
.aw-slot .sw2{width:22px;height:22px;border-radius:50%;box-shadow:0 0 0 2px var(--surface),0 0 0 3px rgba(31,41,80,.14);flex:none;position:relative;overflow:hidden}
.aw-slot .sw2.dk{background:linear-gradient(135deg,#1B2127 50%,#E9EFE4 50%)}
.aw-slot .ck{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;display:none;flex:none}
.aw-slot button.on .ck{display:block}
</style>
@if ($dock)
<script>
document.addEventListener('DOMContentLoaded',function(){
  var h=document.documentElement;
  var inSlot={{ $dockSlot ? 'true' : 'false' }};
  var colours=[['brand',{{ \Illuminate\Support\Js::from($firstName) }},{{ \Illuminate\Support\Js::from($firstDot) }}],['mint','Mint','#9DD9CE']];
  var darkReady={{ $darkReady ? 'true' : 'false' }};
  var d;
  if(inSlot){d=document.getElementById('aw-theme-slot');if(!d)return;d.className='aw-slot';}
  else{d=document.createElement('div');d.className='aw-dock';d.setAttribute('aria-label','Theme');}
  var CK='<svg class="ck" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
  function theme(){return h.getAttribute('data-theme')==='mint'?'mint':'brand';}
  function dark(){return h.getAttribute('data-mode')==='dark';}
  function meta(){var m=document.querySelector('meta[name=theme-color]');if(m)m.setAttribute('content',dark()?'#0F1214':'#FFFFFF');}
  function cbtn(i,c){return'<button type="button" role="radio" aria-checked="'+(c===i[0])+'" class="'+(c===i[0]?'on':'')+'" data-c="'+i[0]+'"><i class="sw2" style="background:'+i[2]+'"></i><span>'+i[1]+'</span>'+CK+'</button>';}
  function dbtn(k){return darkReady?'<button type="button" role="switch" aria-checked="'+k+'" class="'+(k?'on':'')+'" data-m="dark"><i class="sw2 dk"></i><span>Dark</span>'+CK+'</button>':'';}
  function paint(){var c=theme(),k=dark();
    if(inSlot){
      d.innerHTML='<div class="slb">Theme</div><div class="slrow" role="group" aria-label="Theme">'+colours.map(function(i){return cbtn(i,c);}).join('')+dbtn(k)+'</div>';
    }else{
      d.innerHTML='<div class="lb"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/></svg><span>Theme</span></div>'+
        '<div role="radiogroup" aria-label="Colour" style="display:flex;gap:6px">'+colours.map(function(i){return cbtn(i,c);}).join('')+'</div>'+
        (darkReady?'<i class="sep"></i>'+dbtn(k):'');
    }
    meta();}
  d.addEventListener('click',function(e){
    var b=e.target.closest('button');if(!b)return;
    if(b.dataset.c){
      if(b.dataset.c==='mint')h.setAttribute('data-theme','mint');else h.removeAttribute('data-theme');
      try{localStorage.setItem('fsmTheme',b.dataset.c)}catch(x){}
    }else if(b.dataset.m){
      if(dark()){h.removeAttribute('data-mode');try{localStorage.setItem('fsmMode','light')}catch(x){}}
      else{h.setAttribute('data-mode','dark');try{localStorage.setItem('fsmMode','dark')}catch(x){}}
    }else return;
    paint();window.dispatchEvent(new Event('aw-theme'));});
  paint();if(!inSlot)document.body.appendChild(d);

  // Dark catch-all: flag elements that hardcode a white/light-neutral background, dark text or
  // light borders, so the CSS in section 3b can recolour them. Runs only in Dark.
  var SKIP=/^(SCRIPT|STYLE|IMG|SVG|PATH|CANVAS|VIDEO|IFRAME|HEAD|META|LINK|NOSCRIPT|BR|HR)$/;
  function parse(c){var m=c&&c.match(/rgba?\(([^)]+)\)/);if(!m)return null;var p=m[1].split(/[ ,\/]+/).map(parseFloat);return{r:p[0],g:p[1],b:p[2],a:p.length>3?p[3]:1};}
  function mx(o){return Math.max(o.r,o.g,o.b);}function mn(o){return Math.min(o.r,o.g,o.b);}
  function lum(o){return .299*o.r+.587*o.g+.114*o.b;}
  function fixEl(el,parentEff){
    var cs=getComputedStyle(el),eff=parentEff,b=parse(cs.backgroundColor);
    if(b&&b.a>.6){
      if(mn(b)>=244&&mx(b)-mn(b)<=12){el.setAttribute('data-awd','1');eff=20;}
      else eff=lum(b);
    }else if(!b&&cs.backgroundColor.indexOf('color(')===0){eff=20;}   // themed (variable) surface = already dark
    el.__awEff=eff;
    var c=parse(cs.color);
    if(c&&eff<110&&mx(c)-mn(c)<45){
      if(mx(c)<=60)el.setAttribute('data-awt','1');
      else if(mx(c)<=140)el.setAttribute('data-awm','1');
    }
    var bd=parse(cs.borderTopColor);
    if(bd&&cs.borderTopWidth!=='0px'&&mn(bd)>=205&&mx(bd)-mn(bd)<=25)el.setAttribute('data-awb','1');
    return eff;
  }
  function walk(el,parentEff){
    if(el.nodeType!==1||SKIP.test(el.tagName)||el.classList.contains('aw-dock')||el.classList.contains('aw-slot'))return;
    var eff=fixEl(el,parentEff);
    for(var k=el.firstElementChild;k;k=k.nextElementSibling)walk(k,eff);
  }
  function fixAll(){if(!dark()||!darkReady)return;walk(document.body,255);}
  window.addEventListener('aw-theme',function(){setTimeout(fixAll,0);});
  var t=null,pend=[];
  new MutationObserver(function(ms){
    if(!dark())return;
    ms.forEach(function(m){m.addedNodes.forEach(function(n){if(n.nodeType===1)pend.push(n);});});
    if(t)return;
    t=setTimeout(function(){t=null;var l=pend;pend=[];l.forEach(function(n){var p=n.parentElement;walk(n,p&&p.__awEff!=null?p.__awEff:20);});},120);
  }).observe(document.body,{childList:true,subtree:true});
  setTimeout(fixAll,0);
});
</script>
@endif