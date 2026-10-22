<?php
namespace App\Jobs;

use App\Models\{Tenant, TenantSetting, TenantSequence, TenantVocabulary, TenantUsageCounter,
                 Role, User, Plan};
use App\Services\Tenants\SeedStandardRoles;
use App\Services\Auth\SecureLinkService;
use App\Services\Audit\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class ProvisionCompany implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<string, mixed> */
    public array $invitation = [];

    public function __construct(private array $input) {}

    /**
     * The 9 steps from TEN-ACC §6.2, run inside one transaction so a failure
     * at any step leaves nothing behind.
     */
    public function handle(
        SeedStandardRoles $seedRoles,
        SecureLinkService $secureLinks,
        AuditLogger $audit,
    ): void {
        // Idempotent on checkout_reference (D24, SCH-05).
        if ($ref = $this->input['checkout_reference'] ?? null) {
            if (Tenant::where('checkout_reference', $ref)->exists()) {
                return;
            }
        }

        DB::transaction(function () use ($seedRoles, $secureLinks, $audit) {
            // Step 1 — company row
            $tenant = Tenant::create([
                'slug'               => $this->input['slug'],
                'name'               => $this->input['name'],
                'plan_id'            => $this->input['plan_id']            ?? null,
                'country'            => $this->input['country']            ?? null,
                'timezone'           => $this->input['timezone']           ?? 'Asia/Calcutta',
                'base_currency'      => $this->input['base_currency']      ?? null,
                'status'             => $this->input['start_as']           ?? 'active',
                'trial_ends_on'      => $this->input['trial_ends_on']      ?? null,
                'source'             => $this->input['source']             ?? 'platform_admin',
                'checkout_reference' => $this->input['checkout_reference'] ?? null,
                'contact_email'      => $this->input['contact_email']      ?? null,
                'primary_color'      => $this->input['primary_color']      ?? '#FFC61A',
                'activated_at'       => now(),
                'locale'             => 'en',
            ]);

            // Step 2 — 7 standard roles + 42 permissions each (ACC-12)
            $seedRoles->handle($tenant);

            // Step 3 — 5 number sequences with D23 defaults
            foreach (['SR', 'QT', 'INV', 'CT', 'PAY'] as $prefix) {
                TenantSequence::create([
                    'tenant_id'    => $tenant->id,
                    'prefix'       => $prefix,
                    'pattern'      => "{$prefix}-YYYY-000001",
                    'reset_yearly' => true,
                    'current_year' => (int) now()->format('Y'),
                    'next_number'  => 1,
                ]);
            }

            // Step 4 — default company settings
            TenantSetting::create([
                'tenant_id'                 => $tenant->id,
                'reopen_window_days'        => 14,
                'feedback_link_days'        => 7,
                'client_sign_in_link_hours' => 24,
                'require_two_factor'        => false,
            ]);

            // Step 5 — default vocabulary (the 10 renamable terms from CLAUDE.md §4)
            $defaults = [
                'service_request' => ['Service request', 'Service requests'],
                'technician'      => ['Technician',       'Technicians'],
                'team_lead'       => ['Team Lead',        'Team Leads'],
                'client'          => ['Client',           'Clients'],
                'site'            => ['Site',             'Sites'],
                'asset'           => ['Asset',            'Assets'],
                'contract'        => ['Contract',         'Contracts'],
                'quote'           => ['Quote',            'Quotes'],
                'invoice'         => ['Invoice',          'Invoices'],
                'qc_review'       => ['QC review',        'QC reviews'],
            ];
            foreach ($defaults as $key => [$singular, $plural]) {
                TenantVocabulary::create([
                    'tenant_id' => $tenant->id,
                    'key'       => $key,
                    'singular'  => $singular,
                    'plural'    => $plural,
                ]);
            }

            // Step 6 — this month's usage counter row
            TenantUsageCounter::create([
                'tenant_id' => $tenant->id,
                'year'      => (int) now()->format('Y'),
                'month'     => (int) now()->format('n'),
            ]);

            // Step 7 — first admin + invitation
            $companyAdminRole = Role::where('tenant_id', $tenant->id)
                ->where('code', 'company_admin')
                ->firstOrFail();

            $admin = User::create([
                'tenant_id'            => $tenant->id,
                'role_id'              => $companyAdminRole->id,
                'name'                 => $this->input['first_admin_name'],
                'email'                => $this->input['first_admin_email'],
                'password'             => Hash::make(Str::random(32)),  // placeholder
                'must_change_password' => true,
            ]);

            // D9: issue a 7-day invitation link (ACC-02 flow).
            $token = $secureLinks->create($admin, 'user_invite', 7 * 24 * 60);
            $this->invitation = [
                'user'  => $admin->name,
                'email' => $admin->email,
                'url'   => url("/invitation/{$token}"),
            ];

            // Step 8 — starter master data: placeholder; slice 2 will fill real content.

            // Step 9 — audit entry
            $audit->log('company.created', [
                'tenant_id' => $tenant->id,
                'item_type' => Tenant::class,
                'item_id'   => $tenant->id,
                'new'       => [
                    'slug'          => $tenant->slug,
                    'name'          => $tenant->name,
                    'plan_id'       => $tenant->plan_id,
                    'source'        => $tenant->source,
                    'first_admin'   => $admin->email,
                ],
            ]);
        });
    }
}