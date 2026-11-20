<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Blueprint 7.9a: only the Company Admin and the Operations Manager export the request list. The permission key sr.export is new in the matrix
 * (full for those two roles, none for every other role, as in database/seeders/data/permission_matrix.csv, which gives new companies the same rows).
 * This gives the key to the companies that exist already: the two standard roles get full, every other role of every company gets none.
 */
return new class extends Migration {
    private const FULL = ['company_admin', 'operations_manager'];

    public function up(): void
    {
        $permission = Permission::updateOrCreate(['key' => 'sr.export'], [
            'group'            => 'Service requests',
            'description'      => 'Download the request list as an Excel file',
            'feature_required' => null,
        ]);

        foreach (Role::query()->get(['id', 'tenant_id', 'code', 'is_standard']) as $role) {
            RolePermission::firstOrCreate(
                ['tenant_id' => $role->tenant_id, 'role_id' => $role->id, 'permission_id' => $permission->id],
                ['level' => ($role->is_standard && in_array($role->code, self::FULL, true)) ? 'full' : 'none'],
            );
        }
    }

    public function down(): void
    {
        $permission = Permission::where('key', 'sr.export')->first();
        if ($permission) {
            RolePermission::where('permission_id', $permission->id)->delete();
            $permission->delete();
        }
    }
};
