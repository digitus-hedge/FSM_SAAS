<?php

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\ClientsController;
use App\Http\Controllers\Admin\ClientContactsController;
use App\Http\Controllers\Admin\ProjectSitesController;
use App\Http\Controllers\Admin\SlaPoliciesController;
use App\Http\Controllers\Admin\TaxRatesController;
use App\Http\Controllers\Admin\ServiceCategoriesController;
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
Route::middleware(['auth:platform'])->prefix('admin')->name('admin.')->group(function () {
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
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);
        return view('tenant.dashboard', compact('tenant'));
    })->name('tenant.dashboard');

    // Members
    Route::prefix('members')->name('tenant.members.')->group(function () {
        Route::get('/',                   [MembersController::class, 'index'])->name('index');
        Route::get('/create',             [MembersController::class, 'create'])->name('create');
        Route::post('/',                  [MembersController::class, 'store'])->name('store');
        Route::post('/{user}/suspend',    [MembersController::class, 'suspend'])->name('suspend');
        Route::post('/{user}/reactivate', [MembersController::class, 'reactivate'])->name('reactivate');
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
});