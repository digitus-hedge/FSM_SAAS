<style>
:root{
  --bg:#FFFFFF; --surface:#FFFFFF; --soft:#F5F4FA; --ink:#1B1A24; --muted:#5F5D6E; --line:#E6E4EE;
  --accent: {{ $accentHex ?? '#2F5BEA' }}; --accent-ink:#FFFFFF;
  --blue:#2F5BEA; --violet:#7A5AF8; --green:#14A058; --amber:#D98200; --red:#DC3E36; --slate:#64748B; --teal:#0E94A3; --indigo:#4F46E5; --pink:#D6409F;
  --tint:color-mix(in srgb,var(--accent) 14%,var(--surface));
  box-sizing:border-box; padding-top:env(safe-area-inset-top,0px); padding-bottom:env(safe-area-inset-bottom,0px); color-scheme:light;
}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]){
    --bg:#13121A; --surface:#1A1924; --soft:#222130; --ink:#F3F2F8; --muted:#A9A7B8; --line:#2D2B3C;
    --accent: {{ $accentHex ?? '#7C9CFF' }}; --accent-ink:#0F1220;
    --blue:#7C9CFF; --violet:#AB9DFF; --green:#4CD08A; --amber:#F3B14A; --red:#FF8A82; --slate:#9FB0CC; --teal:#4FD1D9; --indigo:#9A94FF; --pink:#F08CCB; color-scheme:dark;
  }
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;background:var(--soft);color:var(--ink);font:16px/1.45 "DM Sans",system-ui,-apple-system,"Segoe UI",sans-serif;-webkit-text-size-adjust:100%}
h1,h2,h3,h4{font-family:"Figtree","DM Sans",system-ui,sans-serif;margin:0;letter-spacing:-.01em}
button{font:inherit;color:inherit;cursor:pointer}
input,select,textarea{font:inherit;color:inherit}
:root{padding:0!important}
.muted{color:var(--muted)}
.hdr{flex:0 0 auto;min-height:58px;display:flex;align-items:center;gap:10px;padding:max(8px,env(safe-area-inset-top,0px)) 14px 8px;background:var(--bg);border-bottom:1px solid var(--line)}
.hdr .mk{width:34px;height:34px;border-radius:10px;background:var(--ink);color:var(--bg);display:grid;place-items:center;font:700 13px "Figtree";flex:0 0 auto}
.hdr h1{font-size:18px;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ib{width:44px;height:44px;border-radius:14px;border:0;background:transparent;display:grid;place-items:center;position:relative;flex:0 0 auto;text-decoration:none;color:var(--ink)}
.ib:active{background:var(--soft)}
.dot{position:absolute;top:8px;right:8px;min-width:16px;height:16px;border-radius:8px;background:var(--red);color:#fff;font-size:10px;font-weight:700;display:grid;place-items:center;padding:0 4px}
.badge{border:1px solid var(--line);background:var(--surface);border-radius:999px;padding:6px 11px;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
.badge i{width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block}
.badge.amber i{background:var(--amber)}.badge.grey i{background:var(--slate)}
.main{flex:1 1 auto;min-height:0;overflow:auto;overscroll-behavior:contain;padding:14px 14px 100px;-webkit-overflow-scrolling:touch}
.nav{flex:0 0 auto;display:grid;grid-auto-flow:column;grid-auto-columns:1fr;border-top:1px solid var(--line);background:var(--bg);padding-bottom:env(safe-area-inset-bottom,0px)}
.nav a{border:0;background:none;min-height:62px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;font-size:12px;font-weight:600;color:var(--muted);position:relative;text-decoration:none}
.nav a[aria-current=page]{color:var(--ink)}
.nav a[aria-current=page]::before{content:"";position:absolute;top:0;left:30%;right:30%;height:3px;border-radius:0 0 3px 3px;background:var(--accent)}
.nav .dot{top:8px;right:calc(50% - 22px)}
.abar{position:sticky;left:0;right:0;bottom:0;z-index:5;background:var(--bg);border-top:1px solid var(--line);padding:10px 14px calc(10px + env(safe-area-inset-bottom,0px));display:flex;flex-direction:column;gap:8px}
.abar .row{display:flex;gap:8px}
.btn{min-height:52px;border-radius:15px;border:1px solid var(--line);background:var(--surface);font-weight:700;padding:0 18px;display:inline-flex;align-items:center;justify-content:center;gap:8px;flex:1;text-align:center;text-decoration:none;color:var(--ink)}
.btn.primary{background:var(--accent);color:var(--accent-ink);border-color:transparent}
.btn.dark{background:var(--ink);color:var(--bg);border-color:transparent}
.btn.danger{border-color:color-mix(in srgb,var(--red) 45%,var(--line));color:var(--red)}
.btn.sm{min-height:44px;font-size:14px;padding:0 14px;flex:0 0 auto}
.btn[disabled]{opacity:.45;cursor:not-allowed}
.btn.block{width:100%;flex:none}
.banner{display:flex;gap:10px;align-items:flex-start;border-radius:14px;padding:11px 13px;margin-bottom:12px;font-size:14.5px;border:1px solid var(--line);background:var(--soft)}
.banner.amber{background:color-mix(in srgb,var(--amber) 11%,var(--surface));border-color:color-mix(in srgb,var(--amber) 38%,var(--line))}
.banner.red{background:color-mix(in srgb,var(--red) 9%,var(--surface));border-color:color-mix(in srgb,var(--red) 35%,var(--line))}
.banner.green{background:color-mix(in srgb,var(--green) 10%,var(--surface));border-color:color-mix(in srgb,var(--green) 35%,var(--line))}
.banner.blue{background:color-mix(in srgb,var(--blue) 9%,var(--surface));border-color:color-mix(in srgb,var(--blue) 30%,var(--line))}
.banner .ic{flex:0 0 auto;margin-top:2px}
.banner b{font-weight:700}
.sec{font-size:12px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700;margin:20px 2px 9px}
.sec:first-child{margin-top:4px}
.card{background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:14px;margin-bottom:10px}
.card.tap{width:100%;text-align:left;display:block;position:relative}
.card.tap:active{background:var(--soft)}
.card .r1{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:13px;color:var(--muted)}
.card h3{font-size:17px;margin:6px 0 3px;line-height:1.25}
.card .sub{color:var(--muted);font-size:14.5px}
.card .r3{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:10px}
.eta{font-weight:700;font-size:14px;display:inline-flex;align-items:center;gap:5px}
.chip{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:3px 10px;font-size:12.5px;font-weight:700;background:color-mix(in srgb,var(--c,var(--slate)) 13%,var(--surface));color:var(--c,var(--slate));white-space:nowrap}
.chip.sm{font-size:11.5px;padding:2px 8px}
.kv{display:grid;grid-template-columns:96px 1fr;gap:6px 12px;font-size:15px}
.kv dt{color:var(--muted)}.kv dd{margin:0}
.field{margin-bottom:14px}
.field label{display:block;font-weight:700;font-size:14px;margin-bottom:6px}
.inp,.sel,.txt{width:100%;min-height:50px;border:1px solid var(--line);border-radius:14px;background:var(--surface);padding:0 14px;outline:none}
.txt{padding:12px 14px;min-height:96px;resize:vertical}
.inp:focus,.sel:focus,.txt:focus{border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 22%,transparent)}
.err{color:var(--red);font-size:13.5px;margin-top:5px;min-height:0}.err:empty{display:none}
.hint{color:var(--muted);font-size:13.5px;margin-top:5px}
.tabs{display:flex;gap:6px;background:var(--soft);padding:4px;border-radius:14px;margin-bottom:12px}
.tabs a{flex:1;text-align:center;border:0;background:none;border-radius:11px;min-height:42px;line-height:42px;font-weight:700;color:var(--muted);text-decoration:none;display:block}
.tabs a[aria-selected=true]{background:var(--surface);color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.1)}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.thumb{position:relative;aspect-ratio:1;border-radius:14px;overflow:hidden;border:1px solid var(--line);background:var(--soft)}
.thumb img{width:100%;height:100%;object-fit:cover;display:block}
.thumb .st{position:absolute;left:6px;bottom:6px}
.thumb .x{position:absolute;right:5px;top:5px;width:30px;height:30px;border-radius:50%;border:0;background:rgba(20,18,30,.65);color:#fff;display:grid;place-items:center}
.addph{aspect-ratio:1;border:2px dashed var(--line);border-radius:14px;display:grid;place-items:center;color:var(--muted);background:transparent;font-weight:700;font-size:13px;text-align:center}
.proof{list-style:none;margin:0;padding:0}.proof li{display:flex;gap:10px;align-items:center;padding:9px 0;border-top:1px solid var(--line)}.proof li:first-child{border-top:0}
.tick{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;flex:0 0 auto;background:var(--soft);color:var(--muted)}
.tick.ok{background:var(--green);color:#fff}.tick.no{background:color-mix(in srgb,var(--red) 14%,var(--surface));color:var(--red)}
.sheetwrap{position:fixed;inset:0;z-index:30;background:rgba(18,16,28,.5);display:flex;align-items:stretch;justify-content:flex-start}
.sheet{background:var(--bg);width:100%;border-radius:24px 24px 0 0;padding:10px 16px calc(18px + env(safe-area-inset-bottom,0px));max-height:90%;overflow:auto}
.sheet .grab{width:44px;height:5px;border-radius:3px;background:var(--line);margin:0 auto 14px}
.sheet h2{font-size:20px;margin-bottom:6px}
.sheet .acts{display:flex;gap:10px;margin-top:14px}
.toast{position:fixed;left:14px;right:14px;bottom:calc(86px + env(safe-area-inset-bottom,0px));z-index:40;background:var(--ink);color:var(--bg);border-radius:14px;padding:12px 14px;font-size:14.5px;box-shadow:0 12px 30px rgba(0,0,0,.28)}
.pad{display:block;width:100%;height:230px;border:1px solid var(--line);border-radius:16px;background:var(--surface);touch-action:none}
.bar{height:8px;border-radius:5px;background:var(--soft);overflow:hidden}.bar i{display:block;height:100%;background:var(--accent);width:0;transition:width .15s}
.big{font:700 40px "Figtree";letter-spacing:-.02em}
.center{text-align:center}
.row2{display:flex;gap:10px}.row2>*{flex:1}
.list{border:1px solid var(--line);border-radius:18px;overflow:hidden;background:var(--surface);margin-bottom:12px}
.list .it{display:flex;align-items:center;gap:12px;padding:13px 14px;border-top:1px solid var(--line);width:100%;background:none;border-left:0;border-right:0;border-bottom:0;text-align:left;min-height:56px}
.list .it:first-child{border-top:0}
.list .it:active{background:var(--soft)}
.list .it .grow{flex:1;min-width:0}
.list .it .t{font-weight:700}.list .it .s{font-size:13.5px;color:var(--muted)}
.radio{width:22px;height:22px;border-radius:50%;border:2px solid var(--line);flex:0 0 auto;display:grid;place-items:center}
.radio.on{border-color:var(--accent)}.radio.on::after{content:"";width:10px;height:10px;border-radius:50%;background:var(--accent)}
.spin{width:44px;height:44px;border-radius:50%;border:4px solid var(--soft);border-top-color:var(--accent);animation:sp 1s linear infinite;margin:6px auto}
@keyframes sp{to{transform:rotate(360deg)}}
.hide{display:none!important}
.legal{font-size:12.5px;color:var(--muted);text-align:center;margin-top:18px}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
.drawer{width:86%;max-width:330px;height:100%;background:var(--bg);display:flex;flex-direction:column;padding-top:env(safe-area-inset-top,0px);box-shadow:8px 0 30px rgba(0,0,0,.2)}
.drawer .dh{display:flex;gap:12px;align-items:center;padding:18px 16px 14px;border-bottom:1px solid var(--line)}
.drawer .mk{width:44px;height:44px;border-radius:13px;background:var(--ink);color:var(--bg);display:grid;place-items:center;font:700 16px "Figtree";flex:0 0 auto}
.drawer .dl{flex:1;overflow:auto;padding:8px}
.drawer .df{border-top:1px solid var(--line);padding:8px 8px calc(8px + env(safe-area-inset-bottom,0px))}
.dit{display:flex;align-items:center;gap:14px;width:100%;min-height:52px;padding:0 12px;border:0;background:none;border-radius:14px;font-weight:600;text-align:left;color:var(--ink);text-decoration:none}
.dit .grow{flex:1}
.dit:active{background:var(--soft)}
.dit[aria-current=page]{background:var(--soft)}
details summary{list-style:none}details summary::-webkit-details-marker{display:none}details summary::before{content:'+ ';font-weight:700}details[open] summary::before{content:'- '}
</style>