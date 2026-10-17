<?php

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\AuthController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth (public)
|--------------------------------------------------------------------------
*/
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Platform admin area — requires login + is_platform_admin flag
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                 [TenantController::class, 'index'])->name('dashboard');
    Route::get('/tenants/create',   [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants',         [TenantController::class, 'store'])->name('tenants.store');
    Route::post('/tenants/{tenant}/suspend',   [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/reactivate',[TenantController::class, 'reactivate'])->name('tenants.reactivate');
});

/*
|--------------------------------------------------------------------------
| Root — redirect to login or admin
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect(auth()->check() ? route('admin.dashboard') : route('login'));
})->name('home');

/*
|--------------------------------------------------------------------------
| Tenant dashboard (subdomain)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    $tenant = Tenant::current();
    abort_unless($tenant, 404);
    return view('tenant.dashboard', compact('tenant'));
})->name('tenant.dashboard');