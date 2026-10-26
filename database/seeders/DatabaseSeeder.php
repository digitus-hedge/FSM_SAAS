<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Always
        $this->call(PermissionCatalogSeeder::class);
        $this->call(PlanSeeder::class);
        $this->call(PlatformUserSeeder::class);
 $this->call(HoldReasonSeeder::class);
        // Local and staging only — never prod
        if (app()->environment('local', 'staging', 'testing')) {
            // $this->call(DemoTenantsSeeder::class);
        }
    }
}

