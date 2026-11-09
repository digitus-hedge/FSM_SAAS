<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUsageCounter;

class UsageController extends Controller
{
    public function index()
    {
        // Blueprint 7.9a: "The Usage page uses settings.manage (view)."
        abort_unless(auth()->user()?->canDo('settings.manage'), 403);

        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $now = now($tenant->timezone ?? 'UTC');
        $counter = TenantUsageCounter::firstOrNew([
            'tenant_id' => $tenant->id,
            'year' => (int) $now->format('Y'),
            'month' => (int) $now->format('n'),
        ]);

        $plan = $tenant->plan; // tell me if this relation is named differently

        $limits = [
            'users' => [
                'label' => 'Users',
                'used' => $counter->user_count,
                'limit' => $plan?->user_limit,
            ],
            'requests' => [
                'label' => 'Service requests this month',
                'used' => $counter->requests_count,
                'limit' => $plan?->requests_per_month_limit,
            ],
            'storage' => [
                'label' => 'File storage',
                'used' => round($counter->storage_bytes / (1024 ** 3), 2), // bytes -> GB
                'limit' => $plan?->storage_gb_limit,
            ],
        ];

        foreach ($limits as $key => &$row) {
            $row['percent'] = $counter->percentOf($row['limit'], (float) $row['used']);
            $row['warn_80'] = $row['percent'] !== null && $row['percent'] >= 80 && $row['percent'] < 100;
            $row['warn_100'] = $row['percent'] !== null && $row['percent'] >= 100;
        }

        return view('admin.usage.index', [
            'counter' => $counter,
            'limits' => $limits,
            'whatsappCount' => $counter->whatsapp_message_count,
            'emailCount' => $counter->email_message_count,
            'periodLabel' => $now->format('F Y'),
        ]);
    }
}