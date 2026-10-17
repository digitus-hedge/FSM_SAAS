<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class MembersController extends Controller
{
    public function index()
    {
        $tenant = Tenant::current();
        $members = $tenant->members()->orderBy('name')->get();

        return view('tenant.members.index', compact('tenant', 'members'));
    }

    public function create()
    {
        return view('tenant.members.create', [
            'roles' => array_keys(RoleSeeder::BUNDLES),
        ]);
    }

    public function store(Request $request)
    {
        $tenant = Tenant::current();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120'],
            'role'  => ['required', 'string', 'in:' . implode(',', array_keys(RoleSeeder::BUNDLES))],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Reuse existing user if the email is already in the system; otherwise create.
        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name'                 => $data['name'],
                'password'             => Hash::make($data['password']),
                'must_change_password' => true,
            ]
        );

        // Add (or re-activate) membership.
        $tenant->members()->syncWithoutDetaching([
            $user->id => [
                'role'      => $data['role'],
                'status'    => 'active',
                'joined_at' => now(),
            ],
        ]);

        // Assign Spatie role in this tenant's scope.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('tenant.members.index')
            ->with('success', "{$user->name} added as {$data['role']}.");
    }

    public function suspend(User $user)
    {
        $tenant = Tenant::current();
        $tenant->members()->updateExistingPivot($user->id, ['status' => 'suspended']);

        return back()->with('success', "{$user->name} suspended from this workspace.");
    }

    public function reactivate(User $user)
    {
        $tenant = Tenant::current();
        $tenant->members()->updateExistingPivot($user->id, ['status' => 'active']);

        return back()->with('success', "{$user->name} reactivated.");
    }
}