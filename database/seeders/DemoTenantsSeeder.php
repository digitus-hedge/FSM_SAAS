<?php
// database/seeders/DemoTenantsSeeder.php
namespace Database\Seeders;

use App\Jobs\ProvisionCompany;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class DemoTenantsSeeder extends Seeder
{
    public function run(): void
    {
        $pro = Plan::where('code', 'professional')->firstOrFail();

        // Acme — Starter plan to exercise feature-gating
        ProvisionCompany::dispatchSync([
            'name'              => 'Acme Industries LLC',
            'slug'              => 'acme',
            'plan_id'           => Plan::where('code', 'starter')->firstOrFail()->id,
            'country'           => 'SA',
            'timezone'          => 'Asia/Riyadh',
            'base_currency'     => 'SAR',
            'start_as'          => 'active',
            'trial_ends_on'     => null,
            'first_admin_name'  => 'Acme Admin',
            'first_admin_email' => 'admin@acme.test',
            'source'            => 'platform_admin',
            'checkout_reference'=> null,
        ]);

        // Wayne — Professional, exercises portal_basic + team_lead gating
        ProvisionCompany::dispatchSync([
            'name'              => 'Wayne Enterprises',
            'slug'              => 'wayne',
            'plan_id'           => $pro->id,
            'country'           => 'AE',
            'timezone'          => 'Asia/Dubai',
            'base_currency'     => 'AED',
            'start_as'          => 'trial',
            'trial_ends_on'     => now()->addDays(14)->toDateString(),
            'first_admin_name'  => 'Wayne Admin',
            'first_admin_email' => 'admin@wayne.test',
            'source'            => 'platform_admin',
            'checkout_reference'=> null,
        ]);
    }
}