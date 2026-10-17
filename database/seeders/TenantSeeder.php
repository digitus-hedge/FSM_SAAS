<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
        $this->seedTenant(
            slug: 'acme',
            name: 'Acme Industries LLC',
            color: '#FFC61A',
            adminEmail: 'admin@acme.test',
            adminName: 'Acme Admin',
        );

        // ─── Tenant B: Wayne ─────────────────────────────────────────
        $this->seedTenant(
            slug: 'wayne',
            name: 'Wayne Enterprises',
            color: '#2E6CD9',
            adminEmail: 'admin@wayne.test',
            adminName: 'Wayne Admin',
        );

        // Reset team context so nothing downstream inherits it.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    /**
     * Create a tenant, its 5 roles with permission bundles, and attach a
     * company_admin user. One method, so the two tenants stay in sync.
     */
    private function seedTenant(string $slug, string $name, string $color, string $adminEmail, string $adminName): void
    {
        $tenant = Tenant::updateOrCreate(
            ['slug' => $slug],
            [
                'name'          => $name,
                'status'        => 'active',
                'contact_email' => "ops@{$slug}.test",
                'activated_at'  => now(),
                'primary_color' => $color,
            ]
        );

        // All role work below is scoped to this tenant's team context.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create the 5 roles for this tenant and sync their permission bundles.
        foreach (RoleSeeder::BUNDLES as $roleName => $perms) {
            $role = Role::firstOrCreate(
                [
                    'name'       => $roleName,
                    'guard_name' => 'web',
                    'tenant_id'  => $tenant->id,
                ]
            );

            $permissionKeys = $perms === '*' ? PermissionSeeder::KEYS : $perms;
            $role->syncPermissions($permissionKeys);
        }

        // Create (or reuse) the admin user and attach them as company_admin.
        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name'     => $adminName,
                'password' => Hash::make('password'),
            ]
        );

        $tenant->members()->syncWithoutDetaching([
            $admin->id => [
                'role'      => 'company_admin',
                'status'    => 'active',
                'joined_at' => now(),
            ],
        ]);

        // Assign the Spatie role AFTER the role exists in this tenant's scope.
        $admin->syncRoles(['company_admin']);
    }
}