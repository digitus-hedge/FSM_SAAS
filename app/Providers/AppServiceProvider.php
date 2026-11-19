<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Listeners\RecordLoginHistory;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The daily rates provider is chosen in config/rates.php (CUR-01).
        $this->app->bind(\App\Services\Currency\RateProvider::class, function ($app) {
            $class = config('rates.providers.' . config('rates.provider'));
            if (! $class) {
                throw new \RuntimeException('Unknown rates provider: ' . config('rates.provider'));
            }
            return $app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191); 

        Blueprint::macro('tenantId', function () {
            /** @var Blueprint $this */
            $this->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();
            $this->index('tenant_id');
        });
        Event::listen(Login::class, RecordLoginHistory::class);

        // Scoped route binding for records that belong to one company. Implicit binding runs before TenantResolver sets the
        // current company, so the model scope is not yet active here: take the company from the signed-in user (or the host).
        // Another company's id behaves as not found (404). User and Role are bound here without a global scope on purpose:
        // authentication, the central sign-in and the invitation flow look users up across companies.
        // BAT / D17: a completed batch gives each finished asset its service date, whichever screen completes the request.
        \App\Models\ServiceRequest::updated(function (\App\Models\ServiceRequest $sr) {
            if ($sr->wasChanged('status') && $sr->status === 'completed') {
                app(\App\Services\Forms\BatchService::class)->onCompleted($sr);
            }
        });

        // sr.assigned (blueprint 9.2): the technician is told whichever screen or job assigned the request.
        \App\Models\ServiceRequest::updated(function (\App\Models\ServiceRequest $sr) {
            if ($sr->wasChanged('assigned_tech_user_id') && $sr->assigned_tech_user_id) {
                $tech = \App\Models\User::withoutGlobalScopes()->find($sr->assigned_tech_user_id);
                if ($tech) {
                    app(\App\Services\Notifications\NotificationService::class)->notify('sr.assigned', [$tech], [
                        'sr_number' => $sr->sr_number, 'site' => $sr->site?->name,
                        'when' => $sr->scheduled_eta_at ? 'Due ' . $sr->scheduled_eta_at->format('j M Y H:i') . '.' : '',
                    ], route('technician.jobs.show', $sr, false));
                }
            }
        });

        // MTR-02: a trade changed, so the master's status is worked out again; a trade is created with its master.
        \App\Models\ServiceRequest::updated(function (\App\Models\ServiceRequest $sr) {
            if ($sr->parent_sr_id && $sr->wasChanged('status')) {
                $master = \App\Models\ServiceRequest::withoutGlobalScopes()->find($sr->parent_sr_id);
                if ($master) {
                    app(\App\Services\Trades\MasterRequests::class)->recompute($master);
                    if ($sr->status === 'completed') {
                        $svc = app(\App\Services\Notifications\NotificationService::class);
                        $svc->notify('master.child_completed', $svc->usersWithRole('operations_manager', $sr->tenant_id), [
                            'sr_number' => $sr->sr_number, 'trade' => $sr->category?->name, 'master_number' => $master->sr_number,
                        ], route('service-requests.show', $master, false));
                    }
                }
            }
        });

        foreach ([
            'invoice'  => \App\Models\Invoice::class,
            'quote'    => \App\Models\Quote::class,
            'payment'  => \App\Models\Payment::class,
            'expense'  => \App\Models\ServiceRequestExpense::class,
            'override' => \App\Models\GeofenceOverrideRequest::class,
            'log'      => \App\Models\FieldEventLog::class,
            'user'     => \App\Models\User::class,
            'role'     => \App\Models\Role::class,
            'template' => \App\Models\FormTemplate::class,
            'version'  => \App\Models\FormTemplateVersion::class,
            'sra'      => \App\Models\ServiceRequestAsset::class,
            'schedule' => \App\Models\PmSchedule::class,
            'vendor'   => \App\Models\Vendor::class,
        ] as $name => $model) {
            \Illuminate\Support\Facades\Route::bind($name, function ($value) use ($model) {
                $tenantId = auth()->user()?->tenant_id ?? \App\Models\Tenant::current()?->id;
                abort_unless($tenantId, 404);

                return $model::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($value);
            });
        }
    }
}
