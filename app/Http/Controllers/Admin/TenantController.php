<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionCompany;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Mail\InvitationMail;
use App\Services\Auth\SecureLinkService;
use Illuminate\Support\Facades\Mail;

class TenantController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index()
    {
        $tenants = Tenant::with('plan')->orderByDesc('created_at')->get();

        $stats = [
            'total'     => $tenants->count(),
            'active'    => $tenants->where('status', 'active')->count(),
            'suspended' => $tenants->where('status', 'suspended')->count(),
        ];

        return view('admin.dashboard', compact('tenants', 'stats'));
    }

    public function create()
    {
        return view('admin.tenants-create', [
            'plans' => Plan::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'min:2', 'max:160'],
            'slug'              => [
                'required', 'string', 'min:3', 'max:40',
                'regex:/^[a-z][a-z0-9-]*$/', 'not_regex:/--/',
                'unique:tenants,slug',
            ],
            'plan_id'           => ['required', 'exists:plans,id'],
            'country'           => ['nullable', 'string', 'size:2'],
            'timezone'          => ['nullable', 'timezone'],
            'base_currency'     => ['nullable', 'string', 'size:3'],
            'start_as'          => ['required', 'in:trial,active'],
            'trial_ends_on'     => ['nullable', 'date', 'after:today', 'required_if:start_as,trial'],
            'contact_email'     => ['nullable', 'email', 'max:120'],
            'primary_color'     => ['nullable', 'string', 'size:7'],
            'first_admin_name'  => ['required', 'string', 'min:2', 'max:150'],
            'first_admin_email' => ['required', 'email', 'max:190'],
        ], [
            'slug.regex'     => 'Use 3 to 40 lowercase letters, numbers or hyphens, starting with a letter.',
            'slug.not_regex' => 'The address must not contain double hyphens.',
            'slug.unique'    => 'That address is already taken.',
        ]);

        $job = new ProvisionCompany($data + ['source' => 'platform_admin']);
        $job->handle(
            app(\App\Services\Tenants\SeedStandardRoles::class),
            app(\App\Services\Auth\SecureLinkService::class),
            app(AuditLogger::class),
        );

        $flash = $job->invitation
            ? ['invitation_link' => $job->invitation]
            : [];

        return redirect()
            ->route('admin.dashboard')
            ->with('success', "Company '{$data['name']}' created. An invitation was sent to {$data['first_admin_email']}.")
            ->with($flash);
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'reason.required' => 'Enter a reason (5 to 255 characters).',
        ]);

        $old = ['status' => $tenant->status];

        $tenant->update([
            'status'           => 'suspended',
            'suspended_at'     => now(),
            'suspended_reason' => $data['reason'],
        ]);

        $this->audit->log('company.status_changed', [
            'tenant_id' => $tenant->id,
            'item_type' => Tenant::class,
            'item_id'   => $tenant->id,
            'old'       => $old,
            'new'       => ['status' => 'suspended', 'reason' => $data['reason']],
        ]);

        return back()->with('success', "{$tenant->name} suspended.");
    }

    public function reactivate(Tenant $tenant)
    {
        $old = ['status' => $tenant->status];

        $tenant->update([
            'status'           => 'active',
            'suspended_at'     => null,
            'suspended_reason' => null,
            'activated_at'     => $tenant->activated_at ?? now(),
        ]);

        $this->audit->log('company.status_changed', [
            'tenant_id' => $tenant->id,
            'item_type' => Tenant::class,
            'item_id'   => $tenant->id,
            'old'       => $old,
            'new'       => ['status' => 'active'],
        ]);

        return back()->with('success', "{$tenant->name} reactivated.");
    }

    public function resendInvitation(Tenant $tenant, SecureLinkService $links)
    {
        // Find the first admin for this tenant (role_id=1 is Company Admin from your seeder).
        $admin = $tenant->users()
            ->whereNull('deactivated_at')
            ->orderBy('id')
            ->first();

        abort_unless($admin, 404, 'No admin user exists for this tenant.');

        $token = $links->create($admin, 'user_invite', 60 * 24 * 7);

        // Build URL against the tenant's own subdomain, not APP_URL.
        $host   = parse_url(config('app.url'), PHP_URL_HOST);
        $port   = parse_url(config('app.url'), PHP_URL_PORT);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $portPart = $port ? ":{$port}" : '';
        $url = "{$scheme}://{$tenant->slug}.{$host}{$portPart}/invitation/{$token}";

        // Send the email via configured mailer (Mailtrap in dev).
        try {
            Mail::to($admin->email)->send(new InvitationMail($admin, $tenant, $url));
            $flashKey = 'success';
            $msg = "Invitation re-sent to {$admin->email}.";
        } catch (\Throwable $e) {
            $flashKey = 'warning';
            $msg = "Link regenerated, but email failed: {$e->getMessage()}";
        }

        // Also flash the URL so the admin can copy it immediately.
        $this->audit->log('tenant.invitation_resent', [
            'tenant_id' => $tenant->id,
            'item_type' => Tenant::class,
            'item_id'   => $tenant->id,
            'new'       => ['email' => $admin->email],
        ]);

        return back()
            ->with($flashKey, $msg)
            ->with('invitation_link', [
                'user'  => $admin->name,
                'email' => $admin->email,
                'url'   => $url,
            ]);
    }
}