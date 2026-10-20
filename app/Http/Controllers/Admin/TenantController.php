<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenants\SeedStandardRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'name'           => ['required', 'string', 'min:2', 'max:160'],
            // D2: 3-40, lowercase letters/digits/hyphens, starts with a letter, no double hyphens.
            'slug'           => [
                'required', 'string', 'min:3', 'max:40',
                'regex:/^[a-z][a-z0-9-]*$/', 'not_regex:/--/',
                'unique:tenants,slug',
            ],
            'contact_email'  => ['nullable', 'email', 'max:120'],
            'primary_color'  => ['nullable', 'string', 'size:7'],
            'admin_name'     => ['required', 'string', 'max:150'],
            'admin_email'    => ['required', 'email', 'max:190'],
            'admin_password' => ['required', 'string', 'min:10'],  // D3: 10 chars minimum
        ], [
            'slug.regex'       => 'Use 3 to 40 lowercase letters, numbers or hyphens, starting with a letter.',
            'slug.not_regex'   => 'The address must not contain double hyphens.',
            'admin_password.min' => 'Admin password must be at least 10 characters.',
        ]);

        // One transaction — if role seeding or admin creation fails, nothing is kept.
        $tenant = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name'          => $data['name'],
                'slug'          => Str::slug($data['slug']),
                'contact_email' => $data['contact_email'] ?? null,
                'primary_color' => $data['primary_color'] ?: '#FFC61A',
                'status'        => 'active',
                'activated_at'  => now(),
                'source'        => 'platform_admin',
            ]);

            // Seed 7 standard roles + 42 permissions × 7 levels for this tenant.
            app(SeedStandardRoles::class)->handle($tenant);

            // Create the Company Admin as a tenant-scoped user with role_id FK.
            // AT-22: same email can exist in two companies as two separate users,
            // so we don't do a global firstOrCreate on email.
            $companyAdminRole = Role::where('tenant_id', $tenant->id)
                ->where('code', 'company_admin')
                ->firstOrFail();

            User::create([
                'tenant_id'            => $tenant->id,
                'role_id'              => $companyAdminRole->id,
                'name'                 => $data['admin_name'],
                'email'                => $data['admin_email'],
                'password'             => Hash::make($data['admin_password']),
                'must_change_password' => true,
            ]);

            return $tenant;
        });

        return redirect()
            ->route('admin.dashboard')
            ->with('success', "Company '{$tenant->name}' created. Admin: {$data['admin_email']}");
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