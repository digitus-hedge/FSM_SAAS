<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public const BUNDLES = [
        'company_admin' => '*',  // all permissions

        'coordinator' => [
            'sr.view', 'sr.create', 'sr.edit',
            'sr.approve', 'sr.reject', 'sr.dispatch',
            'qc.review', 'qc.pass', 'qc.fail',
            'clients.view', 'clients.create', 'clients.edit',
            'members.view',
            'reports.view',
        ],

        'technician' => [
            'sr.view',
            'tech.punch_in', 'tech.punch_out', 'tech.upload_evidence',
        ],

        'finance' => [
            'sr.view',
            'quote.create', 'quote.send', 'quote.approve',
            'invoice.create', 'invoice.send', 'invoice.collect',
            'expense.view', 'expense.approve',
            'reports.view',
        ],

        'customer' => [
            'sr.view', 'sr.create',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create one copy of each role per tenant (team-scoped).
        foreach (Tenant::all() as $tenant) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            foreach (array_keys(self::BUNDLES) as $name) {
                $role = Role::findOrCreate($name, 'web');
                $perms = self::BUNDLES[$name] === '*'
                    ? PermissionSeeder::KEYS
                    : self::BUNDLES[$name];
                $role->syncPermissions($perms);
            }
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}