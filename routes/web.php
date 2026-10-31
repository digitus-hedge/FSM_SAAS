<?php

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\ClientsController;
use App\Http\Controllers\Admin\ClientContactsController;
use App\Http\Controllers\Admin\ProjectSitesController;
use App\Http\Controllers\Admin\TerminologyController;
use App\Http\Controllers\Admin\SlaPoliciesController;
use App\Http\Controllers\Admin\TaxRatesController;
use App\Http\Controllers\Admin\ServiceCategoriesController;
use App\Http\Controllers\Admin\HoldReasonController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ServiceRequestController;
use App\Http\Controllers\Admin\TenantHolidayController;
use App\Http\Controllers\Admin\TenantBusinessHourController;
use App\Http\Controllers\Admin\ExpenseReviewController;
use App\Http\Controllers\Admin\RequestsReviewController;
use App\Http\Controllers\Admin\ClientImportController;
use App\Http\Controllers\Admin\ProjectSiteImportController;
use App\Http\Controllers\Admin\QuotesController;
use App\Http\Controllers\PublicQuoteController;

use App\Http\Controllers\AuthController;

use App\Http\Controllers\Web\PlatformAuthView;
use App\Http\Controllers\Tenant\MembersController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\InvoicesController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\FieldLoginController;
use App\Http\Controllers\Technician\JobsController;
use App\Http\Controllers\Technician\TeamController;

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

Route::get('/login/technician', [FieldLoginController::class, 'showTechnician'])->name('login.technician');
Route::get('/login/team-lead',  [FieldLoginController::class, 'showTeamLead'])->name('login.team-lead');

Route::get('/login/technician', [FieldLoginController::class, 'showTechnician'])->name('login.technician');
Route::get('/login/team-lead',  [FieldLoginController::class, 'showTeamLead'])->name('login.team-lead');
Route::get('/field/login',           [AuthController::class, 'showFieldLogin'])->name('field.login');
Route::get('/field/login/team-lead', [AuthController::class, 'showFieldTeamLeadLogin'])->name('field.login.team-lead');
Route::post('/field/login',          [AuthController::class, 'fieldLogin'])->name('field.login.attempt');
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
    Route::post('/tenants/{tenant}/resend-invitation', [TenantController::class, 'resendInvitation'])->name('tenants.resend-invitation');
});

/*
|--------------------------------------------------------------------------
| Tenant workspace  (authenticated company users)
|--------------------------------------------------------------------------
*/
// Invitation accept — public, token-gated.
Route::get('/invitation/{token}',  [AuthController::class, 'showAcceptInvitation'])->name('invitation.accept');
Route::post('/invitation/{token}', [AuthController::class, 'acceptInvitation'])->name('invitation.accept.submit');



Route::get('/q/{token}',          [PublicQuoteController::class, 'show'])->name('public.quote.show');
Route::get('/q/{token}/pdf',      [PublicQuoteController::class, 'pdf'])->name('public.quote.pdf');
Route::post('/q/{token}/accept',   [PublicQuoteController::class, 'accept'])->name('public.quote.accept');
Route::post('/q/{token}/reject',   [PublicQuoteController::class, 'reject'])->name('public.quote.reject');

// Public client-facing (no auth, token-based)
Route::get('/i/{token}',     [PublicInvoiceController::class, 'show'])->name('public.invoice.show');
Route::get('/i/{token}/pdf', [PublicInvoiceController::class, 'pdf'])->name('public.invoice.pdf');

// Invoices
Route::prefix('admin/invoices')->name('admin.invoices.')->group(function () {
    Route::get('/',                            [InvoicesController::class, 'index'])->name('index');
    Route::get('/build/{sr}',                  [InvoicesController::class, 'build'])->name('build');
    Route::get('/upload/{sr}',                 [InvoicesController::class, 'uploadForm'])->name('upload');
    Route::post('/save/{sr}',                   [InvoicesController::class, 'saveBuilt'])->name('save-built');
    Route::post('/upload/{sr}',                 [InvoicesController::class, 'storeUpload'])->name('store-upload');
    Route::get('/{invoice}',                   [InvoicesController::class, 'show'])->name('show');
    Route::get('/{invoice}/pdf',               [InvoicesController::class, 'pdf'])->name('pdf');
    Route::get('/{invoice}/edit',              [InvoicesController::class, 'edit'])->name('edit');
    Route::put('/{invoice}',                   [InvoicesController::class, 'update'])->name('update');
    Route::post('/{invoice}/submit',            [InvoicesController::class, 'submit'])->name('submit');
    Route::post('/{invoice}/approve',           [InvoicesController::class, 'approve'])->name('approve');
    Route::post('/{invoice}/send-back',         [InvoicesController::class, 'sendBack'])->name('send-back');
    Route::post('/{invoice}/send',              [InvoicesController::class, 'send'])->name('send');
    Route::post('/{invoice}/payment',           [InvoicesController::class, 'recordPayment'])->name('payment');
    Route::post('/payment/{payment}/reverse',   [InvoicesController::class, 'reversePayment'])->name('payment.reverse');
    Route::post('/{invoice}/write-off',         [InvoicesController::class, 'writeOff'])->name('write-off');
    Route::post('/{invoice}/undo-write-off',    [InvoicesController::class, 'undoWriteOff'])->name('undo-write-off');
    Route::post('/{invoice}/void',              [InvoicesController::class, 'void'])->name('void');
});

// Client portal sign-in (public, on tenant subdomain)
Route::prefix('client')->name('client.')->group(function () {
    Route::get('/sign-in',         [ClientAuthController::class, 'showSignIn'])->name('sign-in');
    Route::post('/sign-in',         [ClientAuthController::class, 'requestLink'])->name('sign-in.request');
    Route::get('/sign-in/sent',    [ClientAuthController::class, 'showSent'])->name('sign-in.sent');
    Route::get('/sign-in/{token}', [ClientAuthController::class, 'verifyLink'])->name('sign-in.verify');
});

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', function () {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $clientCodes = ['client_user', 'client_admin', 'client_viewer'];
        if (in_array(auth()->user()->role?->code, $clientCodes, true)) {
            return redirect()->route('portal.dashboard');
        }

        return view('tenant.dashboard', compact('tenant'));
    })->name('tenant.dashboard');

    // Client portal (authenticated client user)
    Route::prefix('portal')->name('portal.')->group(function () {
        Route::get('/',         [ClientAuthController::class, 'dashboard'])->name('dashboard');
        Route::post('/sign-out', [ClientAuthController::class, 'signOut'])->name('sign-out');
    });
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

    Route::prefix('manage/users/import')->name('tenant.users.import.')->group(function () {
        Route::get('/',           [\App\Http\Controllers\Tenant\UserImportController::class, 'show'])->name('show');
        Route::post('/preview',   [\App\Http\Controllers\Tenant\UserImportController::class, 'preview'])->name('preview');
        Route::post('/commit',    [\App\Http\Controllers\Tenant\UserImportController::class, 'commit'])->name('commit');
        Route::get('/sample.csv', [\App\Http\Controllers\Tenant\UserImportController::class, 'sample'])->name('sample');
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


        Route::prefix('import')->name('import.')->group(function () {
            Route::get('/',              [ClientImportController::class, 'show'])->name('show');
            Route::post('/',              [ClientImportController::class, 'store'])->name('store');
            Route::get('/sample.csv',    [ClientImportController::class, 'sample'])->name('sample');
        });


        Route::prefix('{client}/contacts')->name('contacts.')->group(function () {
            Route::post('/',                          [ClientContactsController::class, 'store'])->name('store');
            Route::put('/{contact}',                  [ClientContactsController::class, 'update'])->name('update');
            Route::delete('/{contact}',               [ClientContactsController::class, 'destroy'])->name('destroy');
            Route::patch('/{contact}/active',         [ClientContactsController::class, 'setActive'])->name('active');
            Route::post('/{contact}/make-primary',    [ClientContactsController::class, 'makePrimary'])->name('make-primary');
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


        Route::prefix('import')->name('import.')->group(function () {
            Route::get('/',           [ProjectSiteImportController::class, 'show'])->name('show');
            Route::post('/',           [ProjectSiteImportController::class, 'store'])->name('store');
            Route::get('/sample.csv', [ProjectSiteImportController::class, 'sample'])->name('sample');
        });
    });


    Route::prefix('admin/terminology')->name('admin.terminology.')->group(function () {
        Route::get('/',          [TerminologyController::class, 'index'])->name('index');
        Route::put('/',          [TerminologyController::class, 'update'])->name('update');
        Route::delete('/{key}',  [TerminologyController::class, 'resetOne'])->name('reset');
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
    // Route::prefix('admin/sla-policies')->name('admin.sla-policies.')->group(function () {
    //     Route::get('/',                   [SlaPoliciesController::class, 'index'])->name('index');
    //     Route::get('/create',             [SlaPoliciesController::class, 'create'])->name('create');
    //     Route::post('/',                  [SlaPoliciesController::class, 'store'])->name('store');
    //     Route::get('/{policy}/edit',      [SlaPoliciesController::class, 'edit'])->name('edit');
    //     Route::put('/{policy}',           [SlaPoliciesController::class, 'update'])->name('update');
    //     Route::delete('/{policy}',        [SlaPoliciesController::class, 'destroy'])->name('destroy');
    // });

       // Team Lead categories
    Route::get('/admin/team-lead-categories', [\App\Http\Controllers\Admin\TeamLeadCategoriesController::class, 'index'])->name('admin.team-lead-categories.index');
    Route::post('/admin/team-lead-categories/{teamLead}', [\App\Http\Controllers\Admin\TeamLeadCategoriesController::class, 'update'])->name('admin.team-lead-categories.update');
    
    Route::prefix('admin/sla-policies')->name('admin.sla-policies.')->group(function () {
        Route::get('/',           [SlaPoliciesController::class, 'index'])->name('index');
        Route::post('/save-all',  [SlaPoliciesController::class, 'saveAll'])->name('save-all');
    });

    Route::prefix('admin/branding')->name('admin.branding.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\BrandingController::class, 'index'])->name('index');
        Route::put('/', [\App\Http\Controllers\Admin\BrandingController::class, 'update'])->name('update');
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
    Route::post('/{expense_category}/reactivate', [ExpenseCategoryController::class, 'reactivate'])->name('reactivate');
});


// Requests Review (billing)
Route::prefix('admin/requests-review')->name('admin.requests-review.')->group(function () {
    Route::get('/', [RequestsReviewController::class, 'index'])->name('index');
    Route::get('/{sr}',  [RequestsReviewController::class, 'show'])->name('show');
    Route::post('/{sr}/qc-pass',  [RequestsReviewController::class, 'qcPass'])->name('qc-pass');
    Route::post('/{sr}/cancel',   [RequestsReviewController::class, 'cancel'])->name('cancel');
});

// Quotes
Route::prefix('admin/quotes')->name('admin.quotes.')->group(function () {
    Route::get('/',                    [QuotesController::class, 'index'])->name('index');  // ← NEW

    Route::get('/build/{sr}',          [QuotesController::class, 'build'])->name('build');          // start/edit builder
    Route::get('/upload/{sr}',         [QuotesController::class, 'uploadForm'])->name('upload');    // upload PDF form
    Route::post('/save/{sr}',           [QuotesController::class, 'saveBuilt'])->name('save-built'); // save builder draft
    Route::post('/upload/{sr}',         [QuotesController::class, 'storeUpload'])->name('store-upload'); // save upload
    Route::get('/{quote}',             [QuotesController::class, 'show'])->name('show');
    Route::get('/{quote}/pdf',         [QuotesController::class, 'pdf'])->name('pdf');
    Route::get('/{quote}/edit',        [QuotesController::class, 'edit'])->name('edit');
    Route::put('/{quote}',             [QuotesController::class, 'update'])->name('update');
    Route::post('/{quote}/send',        [QuotesController::class, 'send'])->name('send');
    Route::post('/{quote}/revise',      [QuotesController::class, 'revise'])->name('revise');
});


// Expenses to review
Route::prefix('admin/expenses-review')->name('admin.expenses-review.')->group(function () {
    Route::get('/',                              [ExpenseReviewController::class, 'index'])->name('index');
    Route::post('/{expense}/approve',             [ExpenseReviewController::class, 'approve'])->name('approve');
    Route::post('/{expense}/reject',              [ExpenseReviewController::class, 'reject'])->name('reject');
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

// Service Requests
// Service Requests
Route::prefix('service-requests')->name('service-requests.')->group(function () {
    Route::get('/',                       [ServiceRequestController::class, 'index'])->name('index');
    Route::get('/create',                 [ServiceRequestController::class, 'create'])->name('create');
    Route::post('/',                      [ServiceRequestController::class, 'store'])->name('store');

    // AJAX — sites for a client
    Route::get('/sites/for-client',       [ServiceRequestController::class, 'sitesForClient'])->name('sites.for-client');

    // Serve attachment files via Laravel (no need for storage:link symlink to work)
    Route::get('/file/{service_request}/attachments/{filename}', function (
        \App\Models\ServiceRequest $service_request,
        string $filename
    ) {
        if (str_contains($filename, '/') || str_contains($filename, '..') || str_contains($filename, '\\')) {
            abort(400);
        }
        $path = "sr/{$service_request->id}/attachments/{$filename}";
        abort_unless(\Storage::disk('public')->exists($path), 404);

        return response()->file(storage_path('app/public/' . $path));
    })->name('file');

    Route::get('/{service_request}',       [ServiceRequestController::class, 'show'])->name('show');

    Route::get('/{service_request}/edit', [ServiceRequestController::class, 'edit'])->name('edit');
    Route::put('/{service_request}',      [ServiceRequestController::class, 'update'])->name('update');
    Route::delete('/{service_request}',   [ServiceRequestController::class, 'destroy'])->name('destroy');

    // State transitions
    Route::post('/{service_request}/approve',        [ServiceRequestController::class, 'approve'])->name('approve');
    Route::post('/{service_request}/reject',         [ServiceRequestController::class, 'reject'])->name('reject');
    Route::post('/{service_request}/cancel',         [ServiceRequestController::class, 'cancel'])->name('cancel');
    Route::post('/{service_request}/mark-review',    [ServiceRequestController::class, 'markReview'])->name('mark-review');
    Route::post('/{service_request}/assign',         [ServiceRequestController::class, 'assign'])->name('assign');
    Route::post('/{service_request}/send-for-quote', [ServiceRequestController::class, 'sendForQuote'])->name('send-for-quote');
    Route::post('/{service_request}/change-priority', [ServiceRequestController::class, 'changePriority'])->name('change-priority');
    // Route::delete('/{service_request}/attachments/{filename}', [ServiceRequestController::class, 'deleteAttachment'])
    //     ->name('attachments.delete');
});

    Route::prefix('technician')->middleware(['auth'])->name('technician.')->group(function () {
        Route::get('/jobs', [JobsController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/all', [JobsController::class, 'all'])->name('jobs.all');
        Route::get('/jobs/counts', [JobsController::class, 'liveCounts'])->name('jobs.counts');
        Route::get('/jobs/{serviceRequest}', [JobsController::class, 'show'])->name('jobs.show');

        Route::post('/jobs/{serviceRequest}/accept', [JobsController::class, 'accept'])->name('jobs.accept');
        Route::post('/jobs/{serviceRequest}/change-time', [JobsController::class, 'changeTime'])->name('jobs.change-time');
        Route::post('/jobs/{serviceRequest}/punch-in', [JobsController::class, 'punchIn'])->name('jobs.punch-in');
        Route::post('/jobs/{serviceRequest}/hold', [JobsController::class, 'hold'])->name('jobs.hold');
        Route::post('/jobs/{serviceRequest}/resume', [JobsController::class, 'resume'])->name('jobs.resume');
        Route::post('/jobs/{serviceRequest}/punch-out', [JobsController::class, 'punchOut'])->name('jobs.punch-out');
        Route::post('/jobs/{serviceRequest}/geofence-override', [JobsController::class, 'requestGeofenceOverride'])->name('jobs.geofence-override');

        Route::get('/jobs/{serviceRequest}/photos', [JobsController::class, 'photos'])->name('jobs.photos');
        Route::post('/jobs/{serviceRequest}/photos', [JobsController::class, 'storePhoto'])->name('jobs.photos.store');
        Route::delete('/jobs/{serviceRequest}/photos/{attachment}', [JobsController::class, 'deletePhoto'])->name('jobs.photos.delete');

        Route::get('/jobs/{serviceRequest}/notes', [JobsController::class, 'notes'])->name('jobs.notes');
        Route::post('/jobs/{serviceRequest}/notes', [JobsController::class, 'storeNote'])->name('jobs.notes.store');

        Route::get('/jobs/{serviceRequest}/expenses', [JobsController::class, 'expenses'])->name('jobs.expenses');
        Route::get('/jobs/{serviceRequest}/expenses/create', [JobsController::class, 'createExpense'])->name('jobs.expenses.create');
        Route::post('/jobs/{serviceRequest}/expenses', [JobsController::class, 'storeExpense'])->name('jobs.expenses.store');

        Route::get('/jobs/{serviceRequest}/signature', [JobsController::class, 'signature'])->name('jobs.signature');
        Route::post('/jobs/{serviceRequest}/signature', [JobsController::class, 'storeSignature'])->name('jobs.signature.store');

        Route::get('/notifications', [JobsController::class, 'notifications'])->name('notifications');
        Route::get('/profile', [JobsController::class, 'profile'])->name('profile');

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team/{serviceRequest}/approve', [TeamController::class, 'approve'])->name('team.approve');
        Route::post('/team/{serviceRequest}/reject', [TeamController::class, 'reject'])->name('team.reject');
        Route::post('/team/{serviceRequest}/assign', [TeamController::class, 'assign'])->name('team.assign');
        Route::post('/team/overrides/{override}/approve', [TeamController::class, 'approveOverride'])->name('team.overrides.approve');
        Route::post('/team/overrides/{override}/reject', [TeamController::class, 'rejectOverride'])->name('team.overrides.reject');
        Route::post('/team/conflicts/{log}/reviewed', [TeamController::class, 'reviewConflict'])->name('team.conflicts.reviewed');
    });