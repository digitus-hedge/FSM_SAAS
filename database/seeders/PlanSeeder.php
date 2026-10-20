<?php
// database/seeders/PlanSeeder.php
namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Feature keys come from blueprint §6. Shortened illustrative set below —
        // full feature catalog goes in once we read blueprint §6 end to end.
        Plan::updateOrCreate(['code' => 'starter'], [
            'name' => 'Starter', 'is_active' => true,
            'user_limit' => 5, 'requests_per_month_limit' => 200, 'storage_gb_limit' => 5,
            'api_requests_per_minute' => 60,
            'features' => [
                'portal_basic' => false,  // D7: client sign-in link is Pro+
                'team_lead'    => false,  // D12
                'whatsapp'     => false,
                'form_builder' => false,
                'reports_pdf'  => false,
            ],
        ]);

        Plan::updateOrCreate(['code' => 'professional'], [
            'name' => 'Professional', 'is_active' => true,
            'user_limit' => 25, 'requests_per_month_limit' => 2000, 'storage_gb_limit' => 50,
            'api_requests_per_minute' => 120,
            'features' => [
                'portal_basic' => true,
                'team_lead'    => true,
                'whatsapp'     => true,
                'form_builder' => true,
                'reports_pdf'  => true,
            ],
        ]);

        Plan::updateOrCreate(['code' => 'enterprise'], [
            'name' => 'Enterprise', 'is_active' => true,
            'user_limit' => null, 'requests_per_month_limit' => null, 'storage_gb_limit' => null,
            'api_requests_per_minute' => 300,
            'features' => [
                'portal_basic' => true,
                'team_lead'    => true,
                'whatsapp'     => true,
                'form_builder' => true,
                'reports_pdf'  => true,
            ],
        ]);
    }
}