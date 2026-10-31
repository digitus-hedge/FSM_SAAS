<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Platform Console') — AIYWAH FSM</title>
@php
    $brandTenant = \App\Models\Tenant::current();
    $brandFont   = \App\Models\BrandFont::where('key', $brandTenant?->font_key ?? 'dm_sans')->first();
    $brandColor  = $brandTenant?->primary_color ?? '#FFC61A';
    $googleParam = $brandFont->google_param ?? 'DM+Sans:wght@400;500;700&family=Figtree:wght@600;700';
    $fontBody    = $brandFont->body_family ?? 'DM Sans';
    $fontDisplay = $brandFont->display_family ?? 'Figtree';
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family={{ $googleParam }}&display=swap" rel="stylesheet">

@php
    if (!function_exists('term_title')) {
        function term_title($key, $form = 'plural') {
            $row = \DB::table('tenant_terminology')
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('term_key', $key)->first();
            return ucwords($row?->{$form} ?? str_replace('_',' ',$key));
        }
    }
@endphp

<style>
:root{
  --bg:#FBFAF6; --ink:#1B1A24; --muted:#5F5D6E; --subtle:#8B89A0;
  --line:#E6E4EE; --soft:#F5F4FA;
  --yellow:{{ $brandColor }}; --yellow-soft:color-mix(in srgb, {{ $brandColor }} 18%, white); --amber:color-mix(in srgb, {{ $brandColor }} 60%, black 25%);
  --green:#00866F; --green-soft:#DDF7F1; --red:#C2381B; --red-soft:#FDECE7;
  --font-body:{{ $fontBody }}; --font-display:{{ $fontDisplay }};
}
*{box-sizing:border-box;margin:0;padding:0}
html, body{height:100%}
body{background:var(--bg);color:var(--ink);font-family:var(--font-body),system-ui,sans-serif;font-size:14px;line-height:1.5}
.shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}


/* SIDEBAR */
.side{
  background:#fff;
  border-right:1px solid var(--line);
  padding:20px 14px;
  display:flex;
  flex-direction:column;
  gap:2px;
  position:sticky;
  top:0;
  height:100vh;
  overflow-y:auto;
  box-shadow:1px 0 0 rgba(27,26,36,.02);
}
.side::-webkit-scrollbar{width:0}
.side::-webkit-scrollbar-thumb{background:var(--line);border-radius:999px}
.side::-webkit-scrollbar-thumb:hover{background:var(--subtle)}
.brand{display:flex;align-items:center;gap:10px;padding:4px 10px 18px;color:var(--ink);font-family:var(--font-display);font-size:17px;font-weight:700;letter-spacing:-.3px;text-decoration:none}
.brand .logo{width:32px;height:32px;border-radius:9px;background:linear-gradient(145deg,#232130,var(--ink));color:var(--yellow);display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;box-shadow:0 2px 6px rgba(27,26,36,.18)}
.brand small{display:block;font-family:var(--font-body);font-size:10.5px;font-weight:400;color:var(--muted);letter-spacing:0;margin-top:1px}
.nav-label{font-size:10.5px;font-weight:700;color:var(--subtle);text-transform:uppercase;letter-spacing:.08em;padding:14px 14px 6px}

.nav-item{display:flex;align-items:center;gap:11px;font-size:13.5px;padding:9px 12px;border-radius:10px;color:var(--ink);text-decoration:none;transition:background .12s}
.nav-item:hover{background:var(--soft)}
.nav-item.active{background:var(--yellow-soft);color:var(--amber);font-weight:600;position:relative}
.nav-item.active::before{content:"";position:absolute;left:-12px;top:50%;transform:translateY(-50%);width:3px;height:16px;background:var(--amber);border-radius:0 3px 3px 0}
.nav-item svg{flex-shrink:0;color:var(--muted);width:17px;height:17px}
.nav-item.active svg{color:var(--amber)}

.nav-group{list-style:none}
.nav-group > summary{
  display:flex;align-items:center;gap:11px;font-size:13.5px;padding:9px 12px;border-radius:10px;
  color:var(--ink);cursor:pointer;user-select:none;list-style:none;position:relative;
}
.nav-group > summary::-webkit-details-marker{display:none}
.nav-group > summary:hover{background:var(--soft)}
.nav-group > summary svg.icon{color:var(--muted);width:17px;height:17px;flex-shrink:0}
.nav-group > summary svg.chev{margin-left:auto;transition:transform .18s ease;color:var(--muted);width:12px;height:12px}
.nav-group[open] > summary svg.chev{transform:rotate(90deg)}
.nav-group.has-active > summary{background:var(--yellow-soft);color:var(--amber);font-weight:600}
.nav-group.has-active > summary svg.icon{color:var(--amber)}

.nav-children{display:flex;flex-direction:column;gap:2px;padding:4px 0 6px 36px;margin-top:2px}
.nav-children .nav-item{padding:7px 10px;font-size:13px;border-radius:8px;gap:9px}
.nav-children .nav-item svg{width:14px;height:14px}
.nav-children .nav-item.disabled{color:var(--subtle);cursor:not-allowed;pointer-events:none}

.side .foot{margin-top:auto;padding-top:14px;border-top:1px solid var(--line)}
.account{display:flex;align-items:center;gap:10px;padding:10px;border-radius:12px;background:var(--soft);margin-bottom:8px}
.avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(145deg,var(--yellow),#F0B700);color:var(--ink);font-weight:800;font-size:12.5px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-family:var(--font-display);box-shadow:0 2px 5px rgba(255,198,26,.35)}
.account .meta{min-width:0;flex:1}
.account .name{color:var(--ink);font-size:13px;font-weight:700;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.account .sub{color:var(--muted);font-size:11px;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}

.foot-row{display:flex;align-items:center;gap:6px;padding:0 2px}
.foot-link{flex:1;display:flex;align-items:center;gap:6px;padding:7px 8px;border-radius:8px;color:var(--muted);text-decoration:none;font-size:12px;background:none;border:0;cursor:pointer;font:inherit;white-space:nowrap}
.foot-link:hover{background:var(--soft);color:var(--ink)}
.foot-link svg{width:13px;height:13px;flex-shrink:0}
.foot-link.tfa-on{color:var(--green);font-weight:600}
.foot-link.signout{color:var(--red);justify-content:center;border:1px solid var(--red-soft);margin-top:4px}
.foot-link.signout:hover{background:var(--red-soft)}

.content{padding:28px 44px 48px;overflow:hidden}

/* SweetAlert2 */
.swal2-popup{font-family:var(--font-body),system-ui,sans-serif!important;border-radius:16px!important;padding:28px!important}
.swal2-title{font-family:var(--font-display)!important;font-size:20px!important;font-weight:700!important;color:var(--ink)!important}
.swal2-html-container{font-size:13px!important;color:var(--muted)!important;margin-top:6px!important}
.swal2-confirm.swal2-styled{background:var(--yellow)!important;color:var(--ink)!important;font-family:var(--font-body)!important;font-weight:600!important;font-size:13px!important;border-radius:999px!important;padding:10px 20px!important;box-shadow:none!important}
.swal2-confirm.swal2-styled:hover{background:#F0B700!important}
.swal2-cancel.swal2-styled{background:#fff!important;color:var(--ink)!important;border:1px solid var(--line)!important;font-family:var(--font-body)!important;font-weight:600!important;font-size:13px!important;border-radius:999px!important;padding:10px 20px!important}
.swal2-icon.swal2-error{border-color:var(--red)!important;color:var(--red)!important}
.swal2-icon.swal2-success{border-color:var(--green)!important;color:var(--green)!important}
.swal2-icon.swal2-warning{border-color:var(--yellow)!important;color:var(--amber)!important}
.swal2-icon.swal2-error [class^='swal2-x-mark-line']{background-color:var(--red)!important}
.swal2-icon.swal2-success [class^='swal2-success-line']{background-color:var(--green)!important}
.swal2-toast{font-family:var(--font-body)!important;border-radius:12px!important;box-shadow:0 8px 24px rgba(0,0,0,.08)!important;border:1px solid var(--line)!important}
.swal2-toast .swal2-title{font-size:13px!important;color:var(--ink)!important}
.swal-btn-red.swal2-confirm.swal2-styled{background:var(--red)!important;color:#fff!important}
.swal-btn-red.swal2-confirm.swal2-styled:hover{background:#A52E15!important}
.swal-btn-amber.swal2-confirm.swal2-styled{background:var(--yellow)!important;color:var(--ink)!important}
.swal-btn-green.swal2-confirm.swal2-styled{background:var(--green)!important;color:#fff!important}
.swal-btn-green.swal2-confirm.swal2-styled:hover{background:#006756!important}
</style>
</head>
<body>

@php
    $isPlatform = \Illuminate\Support\Facades\Auth::guard('platform')->check()
                  && ! \App\Models\Tenant::current();
    $u          = $isPlatform
                  ? \Illuminate\Support\Facades\Auth::guard('platform')->user()
                  : auth()->user();
    $hasOps     = $u?->canDo('client.manage') || $u?->canDo('site.manage') || $u?->canDo('category.manage');
    $hasSetup   = $u?->canDo('settings.manage');
    $hasPeople  = $u?->canDo('user.manage') || $u?->canDo('role.manage') || $u?->canDo('audit.view');

    $opsOpen    = request()->routeIs('admin.clients.*')
               || request()->routeIs('admin.sites.*')
               || request()->routeIs('admin.service-categories.*')
               || request()->routeIs('admin.team-lead-categories.*');

    $setupOpen  = request()->routeIs('admin.sla-policies.*')
               || request()->routeIs('admin.tax-rates.*')
               || request()->routeIs('admin.hold-reasons.*')
               || request()->routeIs('admin.expense-categories.*')
               || request()->routeIs('admin.tenant-holidays.*')
               || request()->routeIs('admin.vendors.*')
               || request()->routeIs('admin.terminology.*')
               || request()->routeIs('admin.tenant-business-hours.*');

    $brandSub   = $isPlatform ? 'Platform console' : ($u?->tenant?->name ?? 'Workspace');
    $brandHome  = $isPlatform ? route('admin.dashboard') : route('tenant.dashboard');
@endphp

<div class="shell">

<nav class="side">
    <a href="{{ $brandHome }}" class="brand">
        <span class="logo">A</span>
        <span>AIYWAH FSM<small>{{ $brandSub }}</small></span>
    </a>

    {{-- ─────────────── PLATFORM ─────────────── --}}
    @if ($isPlatform && $u?->canDo('tenants.view'))
        <div class="nav-label">Platform</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Workspaces
        </a>
    @endif

    {{-- ─────────────── TENANT ─────────────── --}}
    @if (! $isPlatform)
        <a href="{{ route('tenant.dashboard') }}" class="nav-item {{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
            Dashboard
        </a>

        {{-- OPERATIONS --}}
        @if ($hasOps)
            <div class="nav-label">Operations</div>
            <details class="nav-group {{ $opsOpen ? 'has-active' : '' }}" {{ $opsOpen ? 'open' : '' }}>
                <summary>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Master data
                    <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </summary>
                <div class="nav-children">
                    @if ($u->canDo('client.manage'))
                        <a href="{{ route('admin.clients.index') }}" class="nav-item {{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">Clients</a>
                    @endif
                    @if ($u->canDo('site.manage'))
                        <a href="{{ route('admin.sites.index') }}" class="nav-item {{ request()->routeIs('admin.sites.*') ? 'active' : '' }}">Sites</a>
                    @endif
                    @if ($u->canDo('category.manage'))
                        <a href="{{ route('admin.service-categories.index') }}" class="nav-item {{ request()->routeIs('admin.service-categories.*') ? 'active' : '' }}">Service Categories</a>
                    @endif
                    @if ($u->canDo('category.manage'))
                        <a href="{{ route('admin.team-lead-categories.index') }}" class="nav-item {{ request()->routeIs('admin.team-lead-categories.*') ? 'active' : '' }}">Team Lead categories</a>
                    @endif
                </div>
            </details>
        @endif

        {{-- PEOPLE & ACCESS --}}
        @if ($hasPeople)
            <div class="nav-label">People &amp; access</div>
            @if ($u->canDo('user.manage'))
                <a href="{{ route('tenant.users.index') }}" class="nav-item {{ request()->routeIs('tenant.users.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
            @endif
            @if ($u->canDo('role.manage'))
                <a href="{{ route('tenant.roles.index') }}" class="nav-item {{ request()->routeIs('tenant.roles.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    Roles &amp; permissions
                </a>
            @endif
            @if ($u->canDo('audit.view'))
                <a href="{{ route('tenant.audit.index') }}" class="nav-item {{ request()->routeIs('tenant.audit.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>
                    Audit trail
                </a>
            @endif
        @endif

        {{-- SETUP --}}
        @if ($hasSetup)
            <div class="nav-label">Setup</div>
            <details class="nav-group {{ $setupOpen ? 'has-active' : '' }}" {{ $setupOpen ? 'open' : '' }}>
                <summary>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.47.38.9 1.51 1H21a2 2 0 1 1 0 4h-.09c-.6 0-1.17.24-1.51 1Z"/></svg>
                    Company settings
                    <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </summary>
                <div class="nav-children">
                    <a href="{{ route('admin.sla-policies.index') }}" class="nav-item {{ request()->routeIs('admin.sla-policies.*') ? 'active' : '' }}">SLA policies</a>
                    <a href="{{ route('admin.tax-rates.index') }}" class="nav-item {{ request()->routeIs('admin.tax-rates.*') ? 'active' : '' }}">Tax rates</a>
                    <a href="{{ route('admin.hold-reasons.index') }}" class="nav-item {{ request()->routeIs('admin.hold-reasons.*') ? 'active' : '' }}">Hold reasons</a>
                    <a href="{{ route('admin.expense-categories.index') }}" class="nav-item {{ request()->routeIs('admin.expense-categories.*') ? 'active' : '' }}">Expense categories</a>
                    <!-- <a href="{{ route('admin.tenant-holidays.index') }}" class="nav-item {{ request()->routeIs('admin.tenant-holidays.*') ? 'active' : '' }}">Holidays</a> -->
                    <!-- <a href="{{ route('admin.vendors.index') }}" class="nav-item {{ request()->routeIs('admin.vendors.*') ? 'active' : '' }}">Vendors</a> -->
                    <a href="{{ route('admin.tenant-business-hours.index') }}" class="nav-item {{ request()->routeIs('admin.tenant-business-hours.*') ? 'active' : '' }}">Business Hours and Holidays</a>

                    <a href="{{ route('admin.terminology.index') }}" class="nav-item {{ request()->routeIs('admin.terminology.*') ? 'active' : '' }}">Terminology</a>
                    <a href="{{ route('admin.branding.index') }}" class="nav-item {{ request()->routeIs('admin.branding.*') ? 'active' : '' }}">Branding</a>
                </div>
            </details>
        @endif

        {{-- REQUESTS --}}
        @if ($u?->canDo('sr.view'))
            <a href="{{ route('service-requests.index') }}" class="nav-item {{ request()->routeIs('service-requests.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                Service Requests

                <!-- {{ term_title('service_request') }} -->
            </a>
        @endif



        @if ($u->role?->name === 'Company Admin')
        @php
        $waitingCount = \App\Models\ServiceRequestExpense::where('approval_status', 'pending')->count();
        @endphp
        <a href="{{ route('admin.expenses-review.index') }}" class="nav-item {{ request()->routeIs('admin.expenses-review.*') ? 'active' : '' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Expenses to review
        @if ($waitingCount > 0)
            <span style="margin-left:auto;background:var(--yellow);color:var(--ink);font-size:11px;font-weight:700;padding:2px 7px;border-radius:999px;min-width:20px;text-align:center">{{ $waitingCount }}</span>
        @endif
        </a>
        @endif


        @if ($u->role?->name === 'Company Admin')
  @php
    $billingCount = \App\Models\ServiceRequest::whereIn('billing_status', ['pending_quote','awaiting_invoice'])->count();
  @endphp
  <a href="{{ route('admin.requests-review.index') }}" class="nav-item {{ request()->routeIs('admin.requests-review.*')  ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    Billing Requests
    @if ($billingCount > 0)
      <!-- <span style="margin-left:auto;background:var(--yellow);color:var(--ink);font-size:11px;font-weight:700;padding:2px 7px;border-radius:999px">{{ $billingCount }}</span> -->
    @endif
  </a>
@endif



@if ($u->role?->name === 'Company Admin')
  @php
    $draftQuotesCount = \App\Models\Quote::where('status','draft')->count();
  @endphp
  <a href="{{ route('admin.quotes.index') }}" class="nav-item {{ request()->routeIs('admin.quotes.*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
    Quotes
    @if ($draftQuotesCount > 0)
      <!-- <span style="margin-left:auto;background:var(--yellow);color:var(--ink);font-size:11px;font-weight:700;padding:2px 7px;border-radius:999px">{{ $draftQuotesCount }}</span> -->
    @endif
  </a>
@endif

@if ($u->role?->name === 'Company Admin' || $u->role?->name === 'Finance')
  @php
    $invCount = \App\Models\Invoice::whereIn('status', ['draft','pending_approval'])->count();
  @endphp
  <a href="{{ route('admin.invoices.index') }}" class="nav-item {{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
      <polyline points="14 2 14 8 20 8"/>
      <line x1="9" y1="12" x2="15" y2="12"/>
      <line x1="9" y1="16" x2="12" y2="16"/>
    </svg>
    Invoices
    @if ($invCount > 0)
      <span style="margin-left:auto;background:var(--yellow);color:var(--ink);font-size:11px;font-weight:700;padding:2px 7px;border-radius:999px">{{ $invCount }}</span>
    @endif
  </a>
@endif


    @endif

    {{-- FOOT --}}
    <div class="foot">
        <div class="account">
            <span class="avatar">{{ strtoupper(substr($u->name, 0, 2)) }}</span>
            <div class="meta">
                <div class="name">{{ $u->name }}</div>
                <div class="sub">
                    {{ $isPlatform ? ucfirst($u->platform_role ?? 'Admin') : ($u->role?->name ?? 'User') }}
                </div>
            </div>
        </div>

        <div class="foot-row">
            <a href="{{ route('two-factor.setup') }}" class="foot-link {{ ($u->two_factor_confirmed_at ?? false) ? 'tfa-on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                {{ ($u->two_factor_confirmed_at ?? false) ? '2FA on' : '2FA off' }}
            </a>
            <a href="{{ route('profile.devices.index') }}" class="foot-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                Devices
            </a>
        </div>

        <form method="POST" action="{{ $isPlatform ? route('platform.logout') : route('logout') }}">
            @csrf
            <button type="submit" class="foot-link signout" style="width:100%">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Sign out
            </button>
        </form>
    </div>
</nav>

<main class="content">
    @yield('content')
</main>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    @if (session('success'))
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: @json(session('success')),
        showConfirmButton: false, timer: 3500, timerProgressBar: true
    });
    @endif

    @if ($errors->any() && ! $errors->has('email'))
    Swal.fire({
        icon: 'error',
        title: 'Please check your form',
        html: @json(
            $errors->count() . ' field' . ($errors->count() === 1 ? '' : 's')
            . ' need' . ($errors->count() === 1 ? 's' : '') . ' attention. '
            . 'Scroll down to see what to fix.'
        ),
        confirmButtonText: 'Got it'
    }).then(() => {
        const firstBad = document.querySelector('.input.has-error, .has-error');
        if (firstBad) {
            firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstBad.focus({ preventScroll: true });
        }
    });
    @endif

    document.querySelectorAll('form.needs-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: form.dataset.confirmTitle || 'Are you sure?',
                text:  form.dataset.confirmText  || '',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmOk || 'Yes, continue',
                cancelButtonText: 'Cancel',
                reverseButtons: true, focusCancel: true,
                customClass: { confirmButton: 'swal-btn-' + (form.dataset.confirmOkColor || 'amber') }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });
    });

});
</script>

</body>
</html>