<?php

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Tenant\MembersController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth (public)
|--------------------------------------------------------------------------
*/
Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Root — redirect to login OR the right dashboard
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    // Signed in on a tenant subdomain → tenant dashboard
    if (Tenant::current()) {
        return redirect()->route('tenant.dashboard');
    }

    // Signed in on the platform host → platform console (if admin)
    if (auth()->user()->is_platform_admin) {
        return redirect()->route('admin.dashboard');
    }

    // Signed in but has nowhere to go — log out
    auth()->logout();
    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Platform admin area  (/admin/* on the root host, platform admins only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                              [TenantController::class, 'index'])->name('dashboard');
    Route::get('/tenants/create',                [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants',                      [TenantController::class, 'store'])->name('tenants.store');
    Route::post('/tenants/{tenant}/suspend',     [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/reactivate',  [TenantController::class, 'reactivate'])->name('tenants.reactivate');
});

/*
|--------------------------------------------------------------------------
| Tenant workspace  (on a tenant subdomain, authenticated members)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);
        return view('tenant.dashboard', compact('tenant'));
    })->name('tenant.dashboard');

    // Members — Company Admins only
    Route::middleware(['tenant.role:company_admin'])
        ->prefix('members')
        ->name('tenant.members.')
        ->group(function () {
            Route::get('/',                   [MembersController::class, 'index'])->name('index');
            Route::get('/create',             [MembersController::class, 'create'])->name('create');
            Route::post('/',                  [MembersController::class, 'store'])->name('store');
            Route::post('/{user}/suspend',    [MembersController::class, 'suspend'])->name('suspend');
            Route::post('/{user}/reactivate', [MembersController::class, 'reactivate'])->name('reactivate');
        });
});