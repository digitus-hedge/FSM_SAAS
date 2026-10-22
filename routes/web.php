<?php

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\ClientsController;
use App\Http\Controllers\Admin\ClientContactsController;
use App\Http\Controllers\Admin\ProjectSitesController;
use App\Http\Controllers\Admin\SlaPoliciesController;
use App\Http\Controllers\Admin\TaxRatesController;
use App\Http\Controllers\Admin\ServiceCategoriesController;
use App\Http\Controllers\Admin\HoldReasonController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\TenantHolidayController;
use App\Http\Controllers\Admin\TenantBusinessHourController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Web\PlatformAuthView;
use App\Http\Controllers\Tenant\MembersController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform admin sign-in (platform_users table — owner@aiywah.app, etc.)
|--------------------------------------------------------------------------
*/
Route::get('/admin/login',   [PlatformAuthView::class, 'login'])->name('platform.login');
Route::post('/admin/login',  [PlatformAuthView::class, 'loginStub'])->name('platform.login.attempt');
Route::post('/admin/logout', [PlatformAuthView::class, 'logout'])
    ->middleware('auth:platform')->name('platform.logout');

/*
|--------------------------------------------------------------------------
| Company user sign-in (staff + clients on a tenant subdomain)
|--------------------------------------------------------------------------
*/
Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Root — redirect to the right dashboard or login
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    if (auth('platform')->check()) {
        return redirect()->route('admin.dashboard');
    }
    if (auth('web')->check()) {
        return Tenant::current()
            ? redirect()->route('tenant.dashboard')
            : redirect()->route('login');
    }
    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Platform admin area  (/admin/*, platform admins only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:platform', 'platform.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                              [TenantController::class, 'index'])->name('dashboard');
    Route::get('/tenants/create',                [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants',                      [TenantController::class, 'store'])->name('tenants.store');
    Route::post('/tenants/{tenant}/suspend',     [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/reactivate',  [TenantController::class, 'reactivate'])->name('tenants.reactivate');
});

/*
|--------------------------------------------------------------------------
| Tenant workspace  (authenticated company users)
|--------------------------------------------------------------------------
*/
// Invitation accept — public, token-gated.
Route::get('/invitation/{token}',  [AuthController::class, 'showAcceptInvitation'])->name('invitation.accept');
Route::post('/invitation/{token}', [AuthController::class, 'acceptInvitation'])->name('invitation.accept.submit');

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);
        return view('tenant.dashboard', compact('tenant'));
    })->name('tenant.dashboard');

        // Users (was Members) — Company Admins only
    Route::prefix('manage/users')->name('tenant.users.')->group(function () {
        Route::get('/',                        [MembersController::class, 'index'])->name('index');
        Route::get('/create',                  [MembersController::class, 'create'])->name('create');
        Route::post('/',                       [MembersController::class, 'store'])->name('store');
        Route::get('/{user}/edit',             [MembersController::class, 'edit'])->name('edit');
        Route::put('/{user}',                  [MembersController::class, 'update'])->name('update');
        Route::post('/{user}/deactivate',      [MembersController::class, 'deactivate'])->name('deactivate');
        Route::post('/{user}/reactivate',      [MembersController::class, 'reactivate'])->name('reactivate');
        Route::post('/{user}/temporary-password', [MembersController::class, 'temporaryPassword'])->name('temp-password');
        Route::post('/{user}/unlock',          [MembersController::class, 'unlock'])->name('unlock');
        Route::get('/{user}/devices', [MembersController::class, 'devices'])->name('devices');
    });

    // Clients + contacts
    Route::prefix('admin/clients')->name('admin.clients.')->group(function () {
        Route::get('/',                      [ClientsController::class, 'index'])->name('index');
        Route::get('/create',                [ClientsController::class, 'create'])->name('create');
        Route::post('/',                     [ClientsController::class, 'store'])->name('store');
        Route::get('/{client}/edit',         [ClientsController::class, 'edit'])->name('edit');
        Route::put('/{client}',              [ClientsController::class, 'update'])->name('update');
        Route::post('/{client}/suspend',     [ClientsController::class, 'suspend'])->name('suspend');
        Route::post('/{client}/reactivate',  [ClientsController::class, 'reactivate'])->name('reactivate');
        Route::delete('/{client}',           [ClientsController::class, 'destroy'])->name('destroy');

        Route::prefix('{client}/contacts')->name('contacts.')->group(function () {
            Route::post('/',             [ClientContactsController::class, 'store'])->name('store');
            Route::put('/{contact}',     [ClientContactsController::class, 'update'])->name('update');
            Route::delete('/{contact}',  [ClientContactsController::class, 'destroy'])->name('destroy');
        });
    });

    // Sites
    Route::prefix('admin/sites')->name('admin.sites.')->group(function () {
        Route::get('/',                      [ProjectSitesController::class, 'index'])->name('index');
        Route::get('/create',                [ProjectSitesController::class, 'create'])->name('create');
        Route::post('/',                     [ProjectSitesController::class, 'store'])->name('store');
        Route::get('/{site}/edit',           [ProjectSitesController::class, 'edit'])->name('edit');
        Route::put('/{site}',                [ProjectSitesController::class, 'update'])->name('update');
        Route::post('/{site}/suspend',       [ProjectSitesController::class, 'suspend'])->name('suspend');
        Route::post('/{site}/reactivate',    [ProjectSitesController::class, 'reactivate'])->name('reactivate');
        Route::delete('/{site}',             [ProjectSitesController::class, 'destroy'])->name('destroy');
    });

    // Service Categories
    Route::prefix('admin/service-categories')->name('admin.service-categories.')->group(function () {
        Route::get('/',                        [ServiceCategoriesController::class, 'index'])->name('index');
        Route::get('/create',                  [ServiceCategoriesController::class, 'create'])->name('create');
        Route::post('/',                       [ServiceCategoriesController::class, 'store'])->name('store');
        Route::get('/{category}/edit',         [ServiceCategoriesController::class, 'edit'])->name('edit');
        Route::put('/{category}',              [ServiceCategoriesController::class, 'update'])->name('update');
        Route::post('/{category}/suspend',     [ServiceCategoriesController::class, 'suspend'])->name('suspend');
        Route::post('/{category}/reactivate',  [ServiceCategoriesController::class, 'reactivate'])->name('reactivate');
        Route::delete('/{category}',           [ServiceCategoriesController::class, 'destroy'])->name('destroy');
    });

    // SLA Policies
    Route::prefix('admin/sla-policies')->name('admin.sla-policies.')->group(function () {
        Route::get('/',                   [SlaPoliciesController::class, 'index'])->name('index');
        Route::get('/create',             [SlaPoliciesController::class, 'create'])->name('create');
        Route::post('/',                  [SlaPoliciesController::class, 'store'])->name('store');
        Route::get('/{policy}/edit',      [SlaPoliciesController::class, 'edit'])->name('edit');
        Route::put('/{policy}',           [SlaPoliciesController::class, 'update'])->name('update');
        Route::delete('/{policy}',        [SlaPoliciesController::class, 'destroy'])->name('destroy');
    });

    // Tax Rates
    Route::prefix('admin/tax-rates')->name('admin.tax-rates.')->group(function () {
        Route::get('/',                       [TaxRatesController::class, 'index'])->name('index');
        Route::get('/create',                 [TaxRatesController::class, 'create'])->name('create');
        Route::post('/',                      [TaxRatesController::class, 'store'])->name('store');
        Route::get('/{rate}/edit',            [TaxRatesController::class, 'edit'])->name('edit');
        Route::put('/{rate}',                 [TaxRatesController::class, 'update'])->name('update');
        Route::delete('/{rate}',              [TaxRatesController::class, 'destroy'])->name('destroy');
        Route::post('/{rate}/suspend',        [TaxRatesController::class, 'suspend'])->name('suspend');
        Route::post('/{rate}/reactivate',     [TaxRatesController::class, 'reactivate'])->name('reactivate');
        Route::post('/{rate}/set-default',    [TaxRatesController::class, 'setDefault'])->name('set-default');
    });
    // Forced password change — must stay accessible even when must_change_password=true.
    Route::get('/change-password',  [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('password.change.submit');

    Route::prefix('manage/roles')->name('tenant.roles.')->group(function () {
        Route::get('/',                       [\App\Http\Controllers\Tenant\RolesController::class, 'index'])->name('index');
        Route::get('/create',                 [\App\Http\Controllers\Tenant\RolesController::class, 'create'])->name('create');
        Route::post('/',                      [\App\Http\Controllers\Tenant\RolesController::class, 'store'])->name('store');
        Route::get('/{role}/edit',            [\App\Http\Controllers\Tenant\RolesController::class, 'edit'])->name('edit');
        Route::put('/{role}',                 [\App\Http\Controllers\Tenant\RolesController::class, 'update'])->name('update');
        Route::post('/{role}/reset-defaults', [\App\Http\Controllers\Tenant\RolesController::class, 'resetDefaults'])->name('reset-defaults');
        Route::delete('/{role}',              [\App\Http\Controllers\Tenant\RolesController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('manage/audit')->name('tenant.audit.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Tenant\AuditLogsController::class, 'index'])->name('index');
    });
});

// Profile — platform admins AND tenant users.
Route::middleware('auth:platform,web')->prefix('profile')->name('profile.')->group(function () {
    Route::get('/devices',                [\App\Http\Controllers\ProfileDevicesController::class, 'index'])->name('devices.index');
    Route::delete('/devices/{device}',    [\App\Http\Controllers\ProfileDevicesController::class, 'revoke'])->name('devices.revoke');
    Route::post('/devices/revoke-others', [\App\Http\Controllers\ProfileDevicesController::class, 'revokeOthers'])->name('devices.revoke-others');
});
// Two-factor — works for both the platform guard and the web guard.
Route::middleware('auth:platform,web')->group(function () {
    Route::get('/two-factor/setup',    [\App\Http\Controllers\TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup',   [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('/two-factor/disable', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('two-factor.disable');
});

// Challenge after password — session-protected, not guard-protected.
Route::get('/two-factor/challenge',  [\App\Http\Controllers\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
Route::post('/two-factor/challenge', [\App\Http\Controllers\TwoFactorController::class, 'verifyChallenge'])->name('two-factor.verify');


    Route::prefix('admin/hold-reasons')->name('admin.hold-reasons.')->group(function () {
        Route::get('/',                               [HoldReasonController::class, 'index'])->name('index');
        Route::get('/create',                         [HoldReasonController::class, 'create'])->name('create');
        Route::post('/',                              [HoldReasonController::class, 'store'])->name('store');
        Route::get('/{hold_reason}/edit',             [HoldReasonController::class, 'edit'])->name('edit');
        Route::put('/{hold_reason}',                  [HoldReasonController::class, 'update'])->name('update');
        Route::delete('/{hold_reason}',               [HoldReasonController::class, 'destroy'])->name('destroy');
        Route::post('/{hold_reason}/suspend',         [HoldReasonController::class, 'suspend'])->name('suspend');
        Route::post('/{hold_reason}/reactivate',      [HoldReasonController::class, 'reactivate'])->name('reactivate');
    });


    // Expense Categories
    Route::prefix('admin/expense-categories')->name('admin.expense-categories.')->group(function () {
        Route::get('/',                              [ExpenseCategoryController::class, 'index'])->name('index');
        Route::get('/create',                        [ExpenseCategoryController::class, 'create'])->name('create');
        Route::post('/',                             [ExpenseCategoryController::class, 'store'])->name('store');
        Route::get('/{expense_category}/edit',       [ExpenseCategoryController::class, 'edit'])->name('edit');
        Route::put('/{expense_category}',            [ExpenseCategoryController::class, 'update'])->name('update');
        Route::delete('/{expense_category}',         [ExpenseCategoryController::class, 'destroy'])->name('destroy');
        Route::post('/{expense_category}/suspend',   [ExpenseCategoryController::class, 'suspend'])->name('suspend');
        Route::post('/{expense_category}/reactivate',[ExpenseCategoryController::class, 'reactivate'])->name('reactivate');
    });


    // Tenant Holidays
    Route::prefix('admin/tenant-holidays')->name('admin.tenant-holidays.')->group(function () {
    Route::get('/',                            [TenantHolidayController::class, 'index'])->name('index');
    Route::get('/create',                      [TenantHolidayController::class, 'create'])->name('create');
    Route::post('/',                           [TenantHolidayController::class, 'store'])->name('store');
    Route::get('/{tenant_holiday}/edit',       [TenantHolidayController::class, 'edit'])->name('edit');
    Route::put('/{tenant_holiday}',            [TenantHolidayController::class, 'update'])->name('update');
    Route::delete('/{tenant_holiday}',         [TenantHolidayController::class, 'destroy'])->name('destroy');
    });

    // Vendors
    Route::prefix('admin/vendors')->name('admin.vendors.')->group(function () {
    Route::get('/',                      [VendorController::class, 'index'])->name('index');
    Route::get('/create',                [VendorController::class, 'create'])->name('create');
    Route::post('/',                     [VendorController::class, 'store'])->name('store');
    Route::get('/{vendor}/edit',         [VendorController::class, 'edit'])->name('edit');
    Route::put('/{vendor}',              [VendorController::class, 'update'])->name('update');
    Route::delete('/{vendor}',           [VendorController::class, 'destroy'])->name('destroy');
    Route::post('/{vendor}/suspend',     [VendorController::class, 'suspend'])->name('suspend');
    Route::post('/{vendor}/reactivate',  [VendorController::class, 'reactivate'])->name('reactivate');
    });

    // Business Hours
    Route::prefix('admin/business-hours')->name('admin.tenant-business-hours.')->group(function () {
        Route::get('/',  [TenantBusinessHourController::class, 'index'])->name('index');
        Route::put('/',  [TenantBusinessHourController::class, 'update'])->name('update');
    });

    
});
