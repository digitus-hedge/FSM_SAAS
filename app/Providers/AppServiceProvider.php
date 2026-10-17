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
        //
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
    }
}
