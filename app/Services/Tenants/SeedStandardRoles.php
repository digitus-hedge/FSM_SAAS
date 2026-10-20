<?php
// app/Services/Tenants/SeedStandardRoles.php
namespace App\Services\Tenants;

use App\Models\{Permission, Role, RolePermission, Tenant};
use Illuminate\Support\Facades\DB;

class SeedStandardRoles
{
    private const STANDARD_ROLES = [
        'company_admin'      => 'Company Admin',
        'operations_manager' => 'Operations Manager',
        'team_lead'          => 'Team Lead',
        'field_technician'   => 'Field Technician',
        'front_desk'         => 'Front Desk',
        'finance'            => 'Finance',
        'client_user'        => 'Client User',
    ];

    public function handle(Tenant $tenant): void
    {
        $csv = database_path('seeders/data/permission_matrix.csv');
        $rows = array_map('str_getcsv', file($csv));
        $header = array_shift($rows);

        DB::transaction(function () use ($tenant, $rows, $header) {

            foreach (self::STANDARD_ROLES as $code => $name) {
                $role = Role::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $code],
                    ['name' => $name, 'is_standard' => true],
                );

                foreach ($rows as $row) {
                    if (count(array_filter($row)) === 0) continue;
                    $r = array_combine($header, $row);

                    $permission = Permission::where('key', $r['permission_key'])->first();
                    if (! $permission) {
                        throw new \RuntimeException(
                            "Permission '{$r['permission_key']}' from CSV is not in the catalog. "
                            . "Run PermissionCatalogSeeder first."
                        );
                    }

                    RolePermission::updateOrCreate(
                        [
                            'tenant_id'     => $tenant->id,
                            'role_id'       => $role->id,
                            'permission_id' => $permission->id,
                        ],
                        ['level' => $r[$code]],  // 'full' | 'grantable' | 'record_level' |
                                                 // 'view_only' | 'view_own' | 'edit_own' | 'none'
                    );
                }
            }
        });
    }

    /**
     * ACC-10 "Reset to signed defaults" calls this for one role.
     * Re-reads the CSV and overwrites that role's permission levels for this tenant.
     */
    public function resetRole(Tenant $tenant, Role $role): void
    {
        if (! $role->is_standard) {
            throw new \DomainException('Only standard roles can be reset to signed defaults.');
        }

        $csv = database_path('seeders/data/permission_matrix.csv');
        $rows = array_map('str_getcsv', file($csv));
        $header = array_shift($rows);

        DB::transaction(function () use ($tenant, $role, $rows, $header) {
            foreach ($rows as $row) {
                if (count(array_filter($row)) === 0) continue;
                $r = array_combine($header, $row);
                $permission = Permission::where('key', $r['permission_key'])->firstOrFail();

                RolePermission::updateOrCreate(
                    [
                        'tenant_id'     => $tenant->id,
                        'role_id'       => $role->id,
                        'permission_id' => $permission->id,
                    ],
                    ['level' => $r[$role->code]],
                );
            }
        });
    }
}