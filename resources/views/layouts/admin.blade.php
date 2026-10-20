<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Platform Console') — AIYWAH FSM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Figtree:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#FBFAF6; --ink:#1B1A24; --muted:#5F5D6E; --subtle:#8B89A0;
  --line:#E6E4EE; --soft:#F5F4FA;
  --yellow:#FFC61A; --yellow-soft:#FFF4CC; --amber:#8A5A00;
  --green:#00866F; --green-soft:#DDF7F1; --red:#C2381B; --red-soft:#FDECE7;
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--ink);font-family:'DM Sans',system-ui,sans-serif;font-size:14px;line-height:1.5}
.shell{display:grid;grid-template-columns:240px 1fr;min-height:100vh}

/* SIDEBAR */
.side{background:#fff;border-right:1px solid var(--line);padding:22px 14px;display:flex;flex-direction:column;gap:2px}
.brand{display:flex;align-items:center;gap:10px;padding:0 10px 20px;color:var(--ink);font-family:'Figtree';font-size:18px;font-weight:700;letter-spacing:-.3px;text-decoration:none}
.brand .logo{width:34px;height:34px;border-radius:9px;background:var(--ink);color:var(--yellow);display:flex;align-items:center;justify-content:center;font-weight:700}
.brand small{display:block;font-family:'DM Sans';font-size:11px;font-weight:400;color:var(--muted);letter-spacing:0;margin-top:1px}

.nav-item{display:flex;align-items:center;gap:10px;font-size:14px;padding:10px 12px;border-radius:999px;color:var(--ink);text-decoration:none}
.nav-item:hover{background:var(--soft)}
.nav-item.active{background:var(--yellow-soft);color:var(--amber);font-weight:600}
.nav-item svg{flex-shrink:0;color:var(--muted)}
.nav-item.active svg{color:var(--amber)}

/* Collapsible parent menu */
.nav-group{list-style:none}
.nav-group > summary{
  display:flex;align-items:center;gap:10px;font-size:14px;padding:10px 12px;border-radius:999px;
  color:var(--ink);cursor:pointer;user-select:none;list-style:none;position:relative;
}
.nav-group > summary::-webkit-details-marker{display:none}
.nav-group > summary:hover{background:var(--soft)}
.nav-group > summary svg.chev{margin-left:auto;transition:transform .18s ease;color:var(--muted)}
.nav-group[open] > summary svg.chev{transform:rotate(90deg)}
.nav-group.has-active > summary{background:var(--yellow-soft);color:var(--amber);font-weight:600}
.nav-group.has-active > summary svg.icon{color:var(--amber)}

/* Children inside parent */
.nav-children{display:flex;flex-direction:column;gap:2px;padding:4px 0 6px 32px;margin-top:2px}
.nav-children .nav-item{padding:8px 12px;font-size:13px;border-radius:8px}
.nav-children .nav-item.active{background:var(--yellow-soft);color:var(--amber)}
.nav-children .nav-item.disabled{color:var(--subtle);cursor:not-allowed;pointer-events:none}
.nav-children .nav-item .count{margin-left:auto;font-size:10px;color:var(--subtle);background:transparent;padding:0}

.side .foot{margin-top:auto;display:flex;align-items:center;gap:10px;padding:12px 10px;border-top:1px solid var(--line)}
.avatar{width:34px;height:34px;border-radius:50%;background:var(--yellow-soft);color:var(--amber);font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.side .foot b{color:var(--ink);font-size:13px;display:block;line-height:1.2}
.side .foot span{font-size:11px;color:var(--muted)}
.logout-btn{background:none;border:0;color:var(--muted);font-size:12px;cursor:pointer;padding:0;margin-top:3px;text-decoration:underline;font-family:inherit}

/* CONTENT */
.content{padding:28px 44px 48px;overflow:hidden}

/* SweetAlert2 — brand-matched */
.swal2-popup{font-family:'DM Sans',system-ui,sans-serif!important;border-radius:16px!important;padding:28px!important}
.swal2-title{font-family:'Figtree'!important;font-size:20px!important;font-weight:700!important;color:var(--ink)!important}
.swal2-html-container{font-size:13px!important;color:var(--muted)!important;margin-top:6px!important}
.swal2-confirm.swal2-styled{background:var(--yellow)!important;color:var(--ink)!important;font-family:'DM Sans'!important;font-weight:600!important;font-size:13px!important;border-radius:999px!important;padding:10px 20px!important;box-shadow:none!important}
.swal2-confirm.swal2-styled:hover{background:#F0B700!important}
.swal2-cancel.swal2-styled{background:#fff!important;color:var(--ink)!important;border:1px solid var(--line)!important;font-family:'DM Sans'!important;font-weight:600!important;font-size:13px!important;border-radius:999px!important;padding:10px 20px!important}
.swal2-icon.swal2-error{border-color:var(--red)!important;color:var(--red)!important}
.swal2-icon.swal2-success{border-color:var(--green)!important;color:var(--green)!important}
.swal2-icon.swal2-warning{border-color:var(--yellow)!important;color:var(--amber)!important}
.swal2-icon.swal2-error [class^='swal2-x-mark-line']{background-color:var(--red)!important}
.swal2-icon.swal2-success [class^='swal2-success-line']{background-color:var(--green)!important}
.swal2-toast{font-family:'DM Sans'!important;border-radius:12px!important;box-shadow:0 8px 24px rgba(0,0,0,.08)!important;border:1px solid var(--line)!important}
.swal2-toast .swal2-title{font-size:13px!important;color:var(--ink)!important}

.swal-btn-red.swal2-confirm.swal2-styled{background:var(--red)!important;color:#fff!important}
.swal-btn-red.swal2-confirm.swal2-styled:hover{background:#A52E15!important}
.swal-btn-amber.swal2-confirm.swal2-styled{background:var(--yellow)!important;color:var(--ink)!important}
.swal-btn-green.swal2-confirm.swal2-styled{background:var(--green)!important;color:#fff!important}
.swal-btn-green.swal2-confirm.swal2-styled:hover{background:#006756!important}
</style>
</head>
<body>
<div class="shell">

<nav class="side">

    <a href="{{ route('admin.dashboard') }}" class="brand">
        <span class="logo">A</span>
        <span>AIYWAH FSM<small>Platform console</small></span>
    </a>

    {{-- Platform-only items (owner / support / finance) --}}
    @if (auth()->user()?->canDo('tenants.view'))
        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Workspaces
        </a>
    @endif
    {{-- Tenant-only items (company_admin / operations_manager / etc.) --}}
 @php
        $masterOpen = request()->routeIs('admin.clients.*')
                   || request()->routeIs('admin.sites.*')
                   || request()->routeIs('admin.service-categories.*')
                   || request()->routeIs('admin.sla-policies.*')
                   || request()->routeIs('admin.tax-rates.*')
                   || request()->routeIs('admin.hold-reasons.*')
                   || request()->routeIs('admin.expense-categories.*')
                   || request()->routeIs('admin.tenant-holidays.*')
                   || request()->routeIs('admin.vendors.*')
                   || request()->routeIs('admin.tenant-business-hours.*');
                   

    @endphp

    @if (auth()->user()?->canDo('client.manage') || auth()->user()?->canDo('site.manage') || auth()->user()?->canDo('category.manage') || auth()->user()?->canDo('settings.manage'))
    <details class="nav-group {{ $masterOpen ? 'has-active' : '' }}" {{ $masterOpen ? 'open' : '' }}>
        <summary>
            <svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>
            </svg>
            Master Data
            <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </summary>

        <div class="nav-children">
            @if (auth()->user()?->canDo('client.manage'))
                <a href="{{ route('admin.clients.index') }}"
                   class="nav-item {{ request()->routeIs('admin.clients.*') ? 'active' : '' }}">
                    Clients
                </a>
            @endif
            @if (auth()->user()?->canDo('site.manage'))
                <a href="{{ route('admin.sites.index') }}"
                   class="nav-item {{ request()->routeIs('admin.sites.*') ? 'active' : '' }}">
                    Project Sites
                </a>
            @endif
            @if (auth()->user()?->canDo('category.manage'))
                <a href="{{ route('admin.service-categories.index') }}"
                   class="nav-item {{ request()->routeIs('admin.service-categories.*') ? 'active' : '' }}">
                    Service Categories
                </a>
            @endif
            @if (auth()->user()?->canDo('settings.manage'))
                <a href="{{ route('admin.sla-policies.index') }}"
                   class="nav-item {{ request()->routeIs('admin.sla-policies.*') ? 'active' : '' }}">
                    SLA Policies
                </a>
                <a href="{{ route('admin.tax-rates.index') }}"
                   class="nav-item {{ request()->routeIs('admin.tax-rates.*') ? 'active' : '' }}">
                    Tax Rates
                </a>

                <a href="{{ route('admin.hold-reasons.index') }}"
                class="nav-item {{ request()->routeIs('admin.hold-reasons.*') ? 'active' : '' }}">
                Hold Reasons
                </a>

                  <a href="{{ route('admin.expense-categories.index') }}"
                class="nav-item {{ request()->routeIs('admin.expense-categories.*') ? 'active' : '' }}">
                Expense Categories
                </a>

                <a href="{{ route('admin.tenant-holidays.index') }}"
   class="nav-item {{ request()->routeIs('admin.tenant-holidays.*') ? 'active' : '' }}">
    Holidays
</a>

<a href="{{ route('admin.vendors.index') }}"
   class="nav-item {{ request()->routeIs('admin.vendors.*') ? 'active' : '' }}">
    Vendors
</a>

<a href="{{ route('admin.tenant-business-hours.index') }}"
   class="nav-item {{ request()->routeIs('admin.tenant-business-hours.*') ? 'active' : '' }}">
    Business Hours
</a>


            @endif
        </div>
    </details>
    @endif

    <div class="foot">
        <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
        <div style="flex-grow:1">
            <b>{{ auth()->user()->name }}</b>
            <form method="POST" action="{{ Auth::guard('platform')->check() ? route('platform.logout') : route('logout') }}" style="margin:0">
                @csrf
                <button type="submit" class="logout-btn">Sign out</button>
            </form>
        </div>
    </div>
</nav>

<main class="content">
    @yield('content')
</main>

</div>

{{-- SweetAlert2 — loaded globally so any page can call Swal.fire() --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Success toast — fires whenever session('success') is set
    @if (session('success'))
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: @json(session('success')),
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true
    });
    @endif

    // Error popup — fires whenever validation errors exist
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

    // Global confirm — any form with class="needs-confirm" shows SweetAlert instead of browser confirm
    document.querySelectorAll('form.needs-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed === '1') return;   // already confirmed, let it submit
            e.preventDefault();

            Swal.fire({
                icon: 'warning',
                title: form.dataset.confirmTitle || 'Are you sure?',
                text:  form.dataset.confirmText  || '',
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmOk || 'Yes, continue',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true,
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