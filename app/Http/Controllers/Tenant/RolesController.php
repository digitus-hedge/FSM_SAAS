<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\{Permission, Role, RolePermission, Tenant, User};
use App\Services\Audit\AuditLogger;              // ← added
use App\Services\Tenants\SeedStandardRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RolesController extends Controller
{
    private const LEVELS = ['full', 'grantable', 'record_level', 'view_only', 'view_own', 'edit_own', 'none'];

    public function __construct(private AuditLogger $audit) {}   // ← added

    public function index()
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $roles = Role::where('tenant_id', $tenant->id)
            ->withCount('users')
            ->orderByDesc('is_standard')
            ->orderBy('name')
            ->get();

        return view('tenant.roles.index', compact('roles'));
    }

    public function create()
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $standardRoles = Role::where('tenant_id', $tenant->id)
            ->where('is_standard', true)
            ->orderBy('name')
            ->get();

        return view('tenant.roles.create', compact('standardRoles'));
    }

    public function store(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $data = $request->validate([
            'name'      => ['required', 'string', 'min:2', 'max:80',
                            Rule::unique('roles', 'name')->where('tenant_id', $tenant->id)],
            'based_on'  => ['required',
                            Rule::exists('roles', 'code')
                                ->where('tenant_id', $tenant->id)
                                ->where('is_standard', true)],
        ], [
            'name.required'    => 'Enter a role name of 2 to 80 characters.',
            'name.unique'      => 'A role with this name already exists.',
            'based_on.required' => 'Pick a standard role to base this one on.',
        ]);

        $code = \Illuminate\Support\Str::slug($data['name']);

        $role = DB::transaction(function () use ($tenant, $data, $code) {
            $base = Role::where('tenant_id', $tenant->id)
                ->where('code', $data['based_on'])
                ->firstOrFail();

            $role = Role::create([
                'tenant_id'     => $tenant->id,
                'code'          => $code,
                'name'          => $data['name'],
                'is_standard'   => false,
                'based_on_code' => $base->code,
            ]);

            foreach (RolePermission::where('role_id', $base->id)->get() as $rp) {
                RolePermission::create([
                    'tenant_id'     => $tenant->id,
                    'role_id'       => $role->id,
                    'permission_id' => $rp->permission_id,
                    'level'         => $rp->level,
                ]);
            }

            return $role;
        });

        // ↓ audit
        $this->audit->log('role.created', [
            'item_type' => Role::class,
            'item_id'   => $role->id,
            'new'       => $role->only(['code', 'name', 'based_on_code', 'is_standard']),
        ]);

        return redirect()
            ->route('tenant.roles.edit', $role)
            ->with('success', "Role '{$role->name}' was created from {$data['based_on']}.");
    }

        public function edit(Role $role)
    {
        $this->ensureSameTenant($role);

        $permissions = Permission::orderBy('group')->orderBy('key')->get();

        $currentLevels = RolePermission::where('role_id', $role->id)
            ->pluck('level', 'permission_id')
            ->toArray();

        $matrix = app(\App\Services\Permissions\PermissionMatrix::class);
        $defaultsByKey = $matrix->defaultsForRole($role->code);

        $defaultLevels = [];
        $allowedLevels = [];
        $scopeNotes    = [];

        foreach ($permissions as $p) {
            $defaultLevels[$p->id] = $defaultsByKey[$p->key] ?? 'none';
            $allowedLevels[$p->id] = $matrix->allowedLevelsForPermission($p->key);
            $scopeNotes[$p->id]    = $matrix->scopeNoteFor($p->key);
        }

        return view('tenant.roles.edit', [
            'role'          => $role,
            'permissions'   => $permissions->groupBy('group'),
            'currentLevels' => $currentLevels,
            'defaultLevels' => $defaultLevels,
            'allowedLevels' => $allowedLevels,
            'scopeNotes'    => $scopeNotes,
            'levelLabels'   => \App\Services\Permissions\PermissionMatrix::LEVEL_LABELS,
            'levels'        => self::LEVELS,
            'affectedUsers' => User::where('role_id', $role->id)->count(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureSameTenant($role);

        $data = $request->validate([
            'levels'   => ['required', 'array'],
            'levels.*' => ['required', 'in:' . implode(',', self::LEVELS)],
        ]);

        // ↓ snapshot the levels BEFORE the save so we can diff
        $before = RolePermission::where('role_id', $role->id)
            ->pluck('level', 'permission_id')
            ->toArray();

        DB::transaction(function () use ($role, $data) {
            foreach ($data['levels'] as $permissionId => $level) {
                RolePermission::updateOrCreate(
                    [
                        'tenant_id'     => $role->tenant_id,
                        'role_id'       => $role->id,
                        'permission_id' => $permissionId,
                    ],
                    ['level' => $level],
                );
            }
        });

        // ↓ keep only the keys that actually changed, mapped by permission key for readability
        $changedIds = array_keys(array_filter(
            $data['levels'],
            fn ($lvl, $pid) => ($before[$pid] ?? 'none') !== $lvl,
            ARRAY_FILTER_USE_BOTH
        ));

        if ($changedIds) {
            $keyMap = Permission::whereIn('id', $changedIds)->pluck('key', 'id');
            $oldReadable = [];
            $newReadable = [];
            foreach ($changedIds as $pid) {
                $k = $keyMap[$pid] ?? "id:{$pid}";
                $oldReadable[$k] = $before[$pid] ?? 'none';
                $newReadable[$k] = $data['levels'][$pid];
            }

            $this->audit->log('role.permissions_changed', [
                'item_type' => Role::class,
                'item_id'   => $role->id,
                'old'       => $oldReadable,
                'new'       => $newReadable,
            ]);
        }

        return redirect()
            ->route('tenant.roles.edit', $role)
            ->with('success', 'Role saved. Changes apply within a minute.');
    }

    public function resetDefaults(Role $role)
    {
        $this->ensureSameTenant($role);

        if (! $role->is_standard) {
            throw ValidationException::withMessages([
                'role' => 'Only standard roles can be reset to signed defaults.',
            ]);
        }

        app(SeedStandardRoles::class)->resetRole($role->tenant, $role);

        // ↓ audit
        $this->audit->log('role.reset_to_defaults', [
            'item_type' => Role::class,
            'item_id'   => $role->id,
            'new'       => ['code' => $role->code],
        ]);

        return back()->with('success', "Role '{$role->name}' was reset to the signed defaults.");
    }

    public function destroy(Role $role)
    {
        $this->ensureSameTenant($role);

        if ($role->is_standard) {
            throw ValidationException::withMessages([
                'role' => 'Standard roles cannot be deleted.',
            ]);
        }

        $userCount = User::where('role_id', $role->id)->count();
        if ($userCount > 0) {
            throw ValidationException::withMessages([
                'role' => 'Move the users on this role first.',
            ]);
        }

        // ↓ snapshot before delete so the trail shows what existed
        $snapshot = $role->only(['code', 'name', 'based_on_code']);
        $roleId   = $role->id;

        DB::transaction(function () use ($role) {
            RolePermission::where('role_id', $role->id)->delete();
            $role->delete();
        });

        // ↓ audit (after the delete — row is gone, we log the snapshot)
        $this->audit->log('role.deleted', [
            'item_type' => Role::class,
            'item_id'   => $roleId,
            'old'       => $snapshot,
        ]);

        return redirect()->route('tenant.roles.index')
            ->with('success', "Role '{$snapshot['name']}' was deleted.");
    }

    private function ensureSameTenant(Role $role): void
    {
        abort_unless($role->tenant_id === Tenant::current()?->id, 404);
    }
}