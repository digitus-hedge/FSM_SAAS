<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::orderByDesc('created_at')->get();

        $stats = [
            'total'     => $tenants->count(),
            'active'    => $tenants->where('status', 'active')->count(),
            'suspended' => $tenants->where('status', 'suspended')->count(),
        ];

        return view('admin.dashboard', compact('tenants', 'stats'));
    }

    public function create()
    {
        return view('admin.tenants-create');
    }

    public function store(Request $request)
{
    $data = $request->validate([
        'name'           => ['required', 'string', 'max:120'],
        'slug'           => ['required', 'string', 'max:50', 'alpha_dash', 'unique:tenants,slug'],
        'contact_email'  => ['nullable', 'email', 'max:120'],
        'primary_color'  => ['nullable', 'string', 'size:7'],
        'admin_name'     => ['required', 'string', 'max:120'],
        'admin_email'    => ['required', 'email', 'max:120'],
        'admin_password' => ['required', 'string', 'min:8'],
    ]);

    $tenant = Tenant::create([
        'name'          => $data['name'],
        'slug'          => Str::slug($data['slug']),
        'contact_email' => $data['contact_email'] ?? null,
        'primary_color' => $data['primary_color'] ?: '#FFC61A',
        'status'        => 'active',
        'activated_at'  => now(),
    ]);

    // Seed the 5 roles into this new tenant's scope.
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    foreach (RoleSeeder::BUNDLES as $roleName => $perms) {
        $role = Role::firstOrCreate([
            'name'       => $roleName,
            'guard_name' => 'web',
            'tenant_id'  => $tenant->id,
        ]);
        $role->syncPermissions($perms === '*' ? PermissionSeeder::KEYS : $perms);
    }

    // Create (or reuse) the Company Admin user.
    $admin = \App\Models\User::firstOrCreate(
        ['email' => $data['admin_email']],
        [
            'name'                 => $data['admin_name'],
            'password'             => \Illuminate\Support\Facades\Hash::make($data['admin_password']),
            'must_change_password' => true,
        ]
    );

    $tenant->members()->syncWithoutDetaching([
        $admin->id => [
            'role'      => 'company_admin',
            'status'    => 'active',
            'joined_at' => now(),
        ],
    ]);

    $admin->syncRoles(['company_admin']);

    return redirect()
        ->route('admin.dashboard')
        ->with('success', "Workspace '{$tenant->name}' created. Admin: {$admin->email}");
}
    public function suspend(Request $request, Tenant $tenant)
    {
        $tenant->update([
            'status'           => 'suspended',
            'suspended_at'     => now(),
            'suspended_reason' => $request->input('reason'),
        ]);

        return back()->with('success', "{$tenant->name} suspended.");
    }

    public function reactivate(Tenant $tenant)
    {
        $tenant->update([
            'status'           => 'active',
            'suspended_at'     => null,
            'suspended_reason' => null,
            'activated_at'     => $tenant->activated_at ?? now(),
        ]);

        return back()->with('success', "{$tenant->name} reactivated.");
    }
}