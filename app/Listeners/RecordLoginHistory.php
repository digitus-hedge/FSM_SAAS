<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\Tenant;
use Illuminate\Auth\Events\Login;

class RecordLoginHistory
{
    public function handle(Login $event): void
    {
        LoginHistory::create([
            'user_id'      => $event->user->id,
            'tenant_id'    => Tenant::current()?->id,
            'ip'           => request()?->ip(),
            'user_agent'   => substr((string) request()?->userAgent(), 0, 500),
            'logged_in_at' => now(),
        ]);
    }
}