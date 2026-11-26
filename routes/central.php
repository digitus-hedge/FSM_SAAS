<?php

/*
| Root address routes (no company): the central sign-in, its two-factor step, old root links, and the local email preview.
| Registered before the company group (routes/web.php). In path mode the session cookie here is fsm_central (path /).
| In subdomain mode the company file registers the same URIs later and wins the match; its controllers still branch on
| Tenant::current(), so the names below (central.*) point at the same pages.
*/

use App\Http\Controllers\CentralLoginController;
use App\Http\Controllers\LegacyLinkController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth('platform')->check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('central.login');
})->name('home');

// One sign-in for every company: email + password, workspace picker, then a one-time hand-off to the company address.
Route::get('/login',         [CentralLoginController::class, 'show'])->name('central.login');
Route::post('/login',        [CentralLoginController::class, 'attempt'])->name('central.login.attempt');
Route::post('/login/choose', [CentralLoginController::class, 'choose'])->name('login.choose');

// Two-factor step of the central sign-in (session-protected, guard web, no company yet).
Route::get('/two-factor/challenge',  [TwoFactorController::class, 'challenge'])->name('central.two-factor.challenge');
Route::post('/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])->name('central.two-factor.verify');

// Path mode: invoice and quote links sent by the queue before the switch pointed at the root (no company). Look the token up and
// send the visitor to the company's own address. A /q/ value that is no quote token is an asset QR hash.
if (\App\Support\Tenancy::isPath()) {
    Route::get('/i/{token}',     [LegacyLinkController::class, 'invoice'])->name('legacy.invoice');
    Route::get('/i/{token}/pdf', [LegacyLinkController::class, 'invoicePdf'])->name('legacy.invoice.pdf');
    Route::get('/q/{token}',     [LegacyLinkController::class, 'quote'])->name('legacy.quote');
    Route::get('/q/{token}/pdf', [LegacyLinkController::class, 'quotePdf'])->name('legacy.quote.pdf');
}

// Local-only email preview: every email that replaces a WhatsApp message, with sample data. Registered only in the local environment,
// and the controller refuses to run anywhere else as well (Dev\EmailPreviewController::guard).
if (app()->environment('local')) {
    Route::prefix('dev/emails')->name('dev.emails.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Dev\EmailPreviewController::class, 'index'])->name('index');
        Route::get('/{key}', [\App\Http\Controllers\Dev\EmailPreviewController::class, 'show'])->name('show');
        Route::get('/{key}/html', [\App\Http\Controllers\Dev\EmailPreviewController::class, 'html'])->name('html');
    });
}
