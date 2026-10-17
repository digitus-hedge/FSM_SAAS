<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /** 27 permission keys covering the FSM operation set. */
    public const KEYS = [
        // Service requests
        'sr.view', 'sr.create', 'sr.edit', 'sr.delete',
        'sr.approve', 'sr.reject', 'sr.dispatch',

        // Technician execution
        'tech.punch_in', 'tech.punch_out', 'tech.upload_evidence',

        // QC
        'qc.review', 'qc.pass', 'qc.fail',

        // Finance
        'quote.create', 'quote.send', 'quote.approve',
        'invoice.create', 'invoice.send', 'invoice.collect',
        'expense.view', 'expense.approve',

        // Clients
        'clients.view', 'clients.create', 'clients.edit',

        // Members + settings (tenant-level admin)
        'members.view', 'members.manage',
        'settings.view', 'settings.edit',

        // Reports
        'reports.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::KEYS as $key) {
            // Permissions are team-scoped (tenant_id = null means global).
            // Permission definitions themselves are global; the ROLE assignment
            // is what carries the team scope.
            Permission::findOrCreate($key, 'web');
        }
    }
}