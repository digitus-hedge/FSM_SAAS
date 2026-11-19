<style>
  .page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px;gap:12px;flex-wrap:wrap}
  .page-head h1{font-family:var(--font-display);font-size:26px;font-weight:700;letter-spacing:-.3px}
  .page-head p{color:var(--muted);font-size:13px;margin-top:3px}
  .btn{display:inline-flex;align-items:center;gap:6px;font-family:var(--font-body);font-size:13px;font-weight:600;padding:9px 16px;border-radius:999px;border:1px solid transparent;text-decoration:none;cursor:pointer}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-danger{background:#fff;color:var(--red);border-color:var(--line)}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}
  .pad{padding:18px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  thead th{text-align:left;padding:12px 16px;font-weight:600;font-size:11px;color:var(--subtle);text-transform:uppercase;letter-spacing:.06em;background:#FCFBF8;border-bottom:1px solid var(--line)}
  tbody td{padding:14px 16px;border-top:1px solid var(--line);vertical-align:middle}
  .pill{display:inline-flex;align-items:center;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px;background:var(--soft);color:var(--muted)}
  .pill-ok{background:var(--green-soft);color:var(--green)}
  .pill-amber{background:var(--yellow-soft);color:var(--amber)}
  .inp,.sel,.txta{font-family:var(--font-body);font-size:13px;padding:9px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;max-width:100%}
  .txta{width:100%;min-height:70px}
  .field{margin-bottom:14px}
  .field label{display:block;font-size:12px;font-weight:600;margin-bottom:5px}
  .hint{font-size:12px;color:var(--muted)}
  .err{font-size:12px;color:var(--red)}
  .flash{padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px}
  .flash.ok{background:var(--green-soft);color:var(--green)}
  .flash.bad{background:var(--red-soft);color:var(--red)}
</style>
@if (session('error'))<div class="flash bad">{{ session('error') }}</div>@endif
