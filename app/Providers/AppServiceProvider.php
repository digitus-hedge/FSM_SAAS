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
        foreach ([
            'invoice'  => \App\Models\Invoice::class,
            'quote'    => \App\Models\Quote::class,
            'payment'  => \App\Models\Payment::class,
            'expense'  => \App\Models\ServiceRequestExpense::class,
            'override' => \App\Models\GeofenceOverrideRequest::class,
            'log'      => \App\Models\FieldEventLog::class,
            'user'     => \App\Models\User::class,
            'role'     => \App\Models\Role::class,
        ] as $name => $model) {
            \Illuminate\Support\Facades\Route::bind($name, function ($value) use ($model) {
                $tenantId = auth()->user()?->tenant_id ?? \App\Models\Tenant::current()?->id;
                abort_unless($tenantId, 404);

                return $model::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($value);
            });
        }
    }
}
