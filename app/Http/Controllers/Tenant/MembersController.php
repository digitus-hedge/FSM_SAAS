<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;          // ← added
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MembersController extends Controller
{
    public function __construct(private AuditLogger $audit) {}   // ← added

    public function index(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $users = User::where('tenant_id', $tenant->id)
            ->with('role')
            ->when($request->filled('role'), fn ($q) =>
                $q->whereHas('role', fn ($r) => $r->where('code', $request->input('role'))))
            ->when($request->filled('status'), function ($q) use ($request) {
                return match ($request->input('status')) {
                    'active'      => $q->whereNull('deactivated_at'),
                    'deactivated' => $q->whereNotNull('deactivated_at'),
                    'invited'     => $q->where('must_change_password', true)->whereNull('last_sign_in_at'),
                    default       => $q,
                };
            })
            ->when($request->filled('q'), fn ($q) =>
                $q->where(fn ($w) => $w->where('name', 'like', "%{$request->input('q')}%")
                                       ->orWhere('email', 'like', "%{$request->input('q')}%")
                                       ->orWhere('phone', 'like', "%{$request->input('q')}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $roles = Role::where('tenant_id', $tenant->id)->orderBy('name')->get();
        $activeAdminCount = $this->activeAdminCount($tenant);

        return view('tenant.users.index', compact('users', 'roles', 'activeAdminCount'));
    }

    public function create()
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        return view('tenant.members.create', [
            'user'   => null,
            'roles'  => Role::where('tenant_id', $tenant->id)->orderBy('name')->get(),
            'tenant' => $tenant,
        ]);
    }

    public function store(Request $request)
{
    $tenant = Tenant::current();
    abort_unless($tenant, 404);

    $data = $this->validatedPayload($request, $tenant);

    $clientRoleCodes = ['client_user', 'client_admin', 'client_viewer'];
    $role = Role::find($data['role_id']);
    $isClientRole = in_array($role?->code, $clientRoleCodes, true);

    if ($isClientRole && empty($data['email'])) {
        throw ValidationException::withMessages([
            'email' => 'Client accounts need an email — that is how they sign in.',
        ]);
    }

    do {
        $employeeCode = 'EMP-' . $tenant->id . '-' . strtoupper(Str::random(6));
    } while (
        User::where('tenant_id', $tenant->id)
            ->where('employee_code', $employeeCode)
            ->exists()
    );

    $user = User::create([
        'tenant_id'            => $tenant->id,
        'role_id'              => $data['role_id'],
        'name'                 => $data['name'],
        'email'                => $data['email']  ?: null,
        'phone'                => $data['phone']  ?: null,
        'employee_code'        => $employeeCode,
        'password'             => Hash::make(Str::random(32)),
        'must_change_password' => ! $isClientRole,
    ]);

    $this->audit->log('user.created', [
        'item_type' => User::class,
        'item_id'   => $user->id,
        'new'       => $user->only(['name', 'email', 'phone', 'role_id', 'employee_code']),
    ]);

    if ($isClientRole) {
        $signInUrl = $this->buildClientSignInUrl($tenant);
        return redirect()->route('tenant.users.index')
            ->with('success', "{$user->name} was created.")
            ->with('client_info', [
                'user'  => $user->name,
                'email' => $user->email,
                'url'   => $signInUrl,
            ]);
    }

    if ($user->email) {
    $token = app(\App\Services\Auth\SecureLinkService::class)
                ->create($user, 'user_invite', 7 * 24 * 60);

    $url = url("/invitation/{$token}");

    \Illuminate\Support\Facades\Mail::to($user->email)->send(
        (new \App\Mail\InvitationMail($user, $tenant, $url))->afterCommit()
    );

    return redirect()->route('tenant.users.index')
        ->with('success', "{$user->name} was created.")
        ->with('invitation_link', ['user' => $user->name, 'url' => $url]);
}

    $tempPassword = Str::random(12);
    $user->update(['password' => Hash::make($tempPassword)]);

    return redirect()->route('tenant.users.index')
        ->with('success', "{$user->name} was created.")
        ->with('temp_password', ['user' => $user->name, 'password' => $tempPassword]);
}

    public function edit(User $user)
    {
        $this->ensureSameTenant($user);

        return view('tenant.users.form', [
            'user'   => $user,
            'roles'  => Role::where('tenant_id', $user->tenant_id)->orderBy('name')->get(),
            'tenant' => $user->tenant,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureSameTenant($user);
        $data = $this->validatedPayload($request, $user->tenant, $user);

        if ($this->isLastActiveAdmin($user)
            && Role::find($data['role_id'])->code !== 'company_admin') {
            throw ValidationException::withMessages([
                'role_id' => 'A company needs at least one active admin.',
            ]);
        }

        // ↓ snapshot BEFORE the update so we can diff
        $before = $user->only(['name', 'email', 'phone', 'employee_code', 'role_id']);

        $user->update([
            'role_id'       => $data['role_id'],
            'name'          => $data['name'],
            'email'         => $data['email']  ?: null,
            'phone'         => $data['phone']  ?: null,
        ]);

        // ↓ audit — only the keys that actually changed
        $after   = $user->only(['name', 'email', 'phone', 'employee_code', 'role_id']);
        $changed = array_keys(array_diff_assoc($after, $before));
        if ($changed) {
            // role change is logged separately so the trail shows role_changed, not just updated
            $isRoleChange = in_array('role_id', $changed, true);
            $this->audit->log($isRoleChange ? 'user.role_changed' : 'user.updated', [
                'item_type' => User::class,
                'item_id'   => $user->id,
                'old'       => array_intersect_key($before, array_flip($changed)),
                'new'       => array_intersect_key($after,  array_flip($changed)),
            ]);
        }

        return redirect()
            ->route('tenant.users.index')
            ->with('success', "{$user->name} was updated.");
    }

    public function deactivate(User $user)
    {
        $this->ensureSameTenant($user);

        if (Auth::id() === $user->id) {
            return back()->withErrors(['user' => 'You cannot turn off your own account.']);
        }
        if ($this->isLastActiveAdmin($user)) {
            return back()->withErrors(['user' => 'A company needs at least one active admin.']);
        }

        $user->update(['deactivated_at' => now()]);
        $user->setRememberToken(Str::random(60));
        $user->save();

        // ↓ audit
        $this->audit->log('user.deactivated', [
            'item_type' => User::class,
            'item_id'   => $user->id,
        ]);

        return back()->with('success', "{$user->name} was turned off.");
    }

    public function reactivate(User $user)
    {
        $this->ensureSameTenant($user);
        $user->update(['deactivated_at' => null]);

        // ↓ audit
        $this->audit->log('user.reactivated', [
            'item_type' => User::class,
            'item_id'   => $user->id,
        ]);

        return back()->with('success', "{$user->name} was turned on.");
    }

    public function temporaryPassword(User $user)
    {
        $this->ensureSameTenant($user);
        $temp = Str::random(12);
        $user->update([
            'password'             => Hash::make($temp),
            'must_change_password' => true,
            'failed_login_count'   => 0,
            'locked_until'         => null,
        ]);

        // ↓ audit — never log the password itself (sanitize strips it anyway, but don't pass it)
        $this->audit->log('user.access_reset', [
            'item_type' => User::class,
            'item_id'   => $user->id,
            'new'       => ['reason' => 'temporary_password_set'],
        ]);

        return back()->with('temp_password', ['user' => $user->name, 'password' => $temp]);
    }

    public function unlock(User $user)
    {
        $this->ensureSameTenant($user);
        $user->update(['failed_login_count' => 0, 'locked_until' => null]);

        // ↓ audit
        $this->audit->log('user.unlocked', [
            'item_type' => User::class,
            'item_id'   => $user->id,
        ]);

        return back()->with('success', "{$user->name} was unlocked.");
    }

    // ───────────────────────── helpers ─────────────────────────

    private function validatedPayload(Request $request, Tenant $tenant, ?User $editingUser = null): array
    {
        $ignoreId = $editingUser?->id;

        return $request->validate([
            'name'          => ['required', 'string', 'min:2', 'max:150'],
            'email'         => ['nullable', 'email', 'max:190',
                                Rule::unique('users', 'email')
                                    ->where('tenant_id', $tenant->id)
                                    ->ignore($ignoreId)],
            'phone'         => ['nullable', 'string', 'max:30',
                                Rule::unique('users', 'phone')
                                    ->where('tenant_id', $tenant->id)
                                    ->ignore($ignoreId)],
            'employee_code' => ['nullable', 'string', 'max:40',
                                Rule::unique('users', 'employee_code')
                                    ->where('tenant_id', $tenant->id)
                                    ->ignore($ignoreId)],
            'role_id'       => ['required',
                                Rule::exists('roles', 'id')->where('tenant_id', $tenant->id)],
        ], [
            'name.required'    => 'Enter a name of 2 to 150 characters.',
            'email.email'      => 'Enter a valid email address.',
            'email.unique'     => 'That email is already used in this company.',
            'phone.unique'     => 'That phone number is already used in this company.',
            'role_id.required' => 'Choose a role.',
        ]);
    }

    private function ensureSameTenant(User $user): void
    {
        abort_unless($user->tenant_id === Tenant::current()?->id, 404);
    }

    private function activeAdminCount(Tenant $tenant): int
    {
        return User::where('tenant_id', $tenant->id)
            ->whereNull('deactivated_at')
            ->whereHas('role', fn ($q) => $q->where('code', 'company_admin'))
            ->count();
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->role?->code === 'company_admin'
            && $user->deactivated_at === null
            && $this->activeAdminCount($user->tenant) === 1;
    }

    private function buildClientSignInUrl(Tenant $tenant): string
{
    $host     = parse_url(config('app.url'), PHP_URL_HOST);
    $port     = parse_url(config('app.url'), PHP_URL_PORT);
    $scheme   = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
    $portPart = $port ? ":{$port}" : '';
    return "{$scheme}://{$tenant->slug}.{$host}{$portPart}/client/sign-in";
}
    public function devices(User $user)
{
    $this->ensureSameTenant($user);
    $devices = \App\Models\UserDevice::where('user_type', 'user')
        ->where('user_id', $user->id)
        ->whereNull('revoked_at')
        ->orderByDesc('last_used_at')
        ->get();

    return view('tenant.users.devices', [
        'targetUser' => $user,
        'devices'    => $devices,
    ]);
}
}