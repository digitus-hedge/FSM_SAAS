<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Platform admin (SaaS owner) ─────────────────────────────
        User::updateOrCreate(
            ['email' => 'owner@aiywah.app'],
            [
                'name'              => 'Platform Owner',
                'password'          => Hash::make('password'),
                'is_platform_admin' => true,
            ]
        );

        // ─── Tenant A: Acme ──────────────────────────────────────────
        $acme = Tenant::updateOrCreate(
            ['slug' => 'acme'],
            [
                'name'          => 'Acme Industries LLC',
                'status'        => 'active',
                'contact_email' => 'ops@acme.test',
                'activated_at'  => now(),
                'primary_color' => '#FFC61A',
            ]
        );
        $acmeAdmin = User::updateOrCreate(
            ['email' => 'admin@acme.test'],
            ['name' => 'Acme Admin', 'password' => Hash::make('password')]
        );
        $acme->members()->syncWithoutDetaching([
            $acmeAdmin->id => ['role' => 'company_admin', 'status' => 'active', 'joined_at' => now()],
        ]);

        // ─── Tenant B: Wayne ─────────────────────────────────────────
        $wayne = Tenant::updateOrCreate(
            ['slug' => 'wayne'],
            [
                'name'          => 'Wayne Enterprises',
                'status'        => 'active',
                'contact_email' => 'ops@wayne.test',
                'activated_at'  => now(),
                'primary_color' => '#2E6CD9',
            ]
        );
        $wayneAdmin = User::updateOrCreate(
            ['email' => 'admin@wayne.test'],
            ['name' => 'Wayne Admin', 'password' => Hash::make('password')]
        );
        $wayne->members()->syncWithoutDetaching([
            $wayneAdmin->id => ['role' => 'company_admin', 'status' => 'active', 'joined_at' => now()],
        ]);
    }
}