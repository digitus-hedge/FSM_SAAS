<?php

/*
| Platform owner console, /admin (platform_users, guard platform). Registered before the company group (routes/web.php).
| In path mode the console's session cookie is fsm_console with path /admin, so everything the console needs lives under /admin:
| its sign-in, two-factor and devices pages are copies of the company ones with platform.* names.
| The console's {company} parameter is a Tenant (not {tenant}, which is the company prefix and its URL default).
*/

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\ProfileDevicesController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\Web\PlatformAuthView;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

Route::model('company', Tenant::class);

Route::get('/admin/login',   [PlatformAuthView::class, 'login'])->name('platform.login');
Route::post('/admin/login',  [PlatformAuthView::class, 'loginStub'])->name('platform.login.attempt');
Route::post('/admin/logout', [PlatformAuthView::class, 'logout'])
    ->middleware('auth:platform')->name('platform.logout');

// Two-factor challenge after the password (session-protected, not guard-protected)
Route::get('/admin/two-factor/challenge',  [TwoFactorController::class, 'challenge'])->name('platform.two-factor.challenge');
Route::post('/admin/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])->name('platform.two-factor.verify');

// Two-factor setup and devices of the signed-in platform user
Route::prefix('admin')->middleware(['platform.host', 'auth:platform'])->name('platform.')->group(function () {
    Route::get('/two-factor/setup',    [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup',   [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::post('/two-factor/reset',   [TwoFactorController::class, 'reset'])->middleware('throttle:6,1')->name('two-factor.reset');

    Route::get('/profile/devices',                [ProfileDevicesController::class, 'index'])->name('profile.devices.index');
    Route::delete('/profile/devices/{device}',    [ProfileDevicesController::class, 'revoke'])->name('profile.devices.revoke');
    Route::post('/profile/devices/revoke-others', [ProfileDevicesController::class, 'revokeOthers'])->name('profile.devices.revoke-others');
});

Route::middleware(['platform.host', 'auth:platform', 'platform.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                              [TenantController::class, 'index'])->name('dashboard');
    Route::get('/tenants/create',                [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants',                      [TenantController::class, 'store'])->name('tenants.store');
    Route::put('/platform/branding',             [\App\Http\Controllers\Platform\BrandingController::class, 'update'])->name('platform.branding.update');
    Route::post('/tenants/{company}/suspend',     [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{company}/reactivate',  [TenantController::class, 'reactivate'])->name('tenants.reactivate');
    Route::post('/tenants/{company}/resend-invitation', [TenantController::class, 'resendInvitation'])->name('tenants.resend-invitation');
    // Support impersonation: owner and support roles only (checked in the service), reason required, handoff to the company address
    Route::post('/tenants/{company}/impersonate', [\App\Http\Controllers\ImpersonationController::class, 'start'])->name('tenants.impersonate');
});
