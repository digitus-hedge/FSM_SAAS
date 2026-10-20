<?php
// app/Jobs/ProvisionCompany.php — the TEN-04 nine-step job
namespace App\Jobs;

use App\Models\{Tenant, Plan, Role, Permission, RolePermission, User, SecureLink};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\DB;

class ProvisionCompany implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private array $input) {}

    public function handle(): void
    {
        // Idempotent on checkout_reference (D24, SCH-05).
        if ($ref = $this->input['checkout_reference'] ?? null) {
            if (Tenant::where('checkout_reference', $ref)->exists()) return;
        }

        DB::transaction(function () {
            // Step 1 — company row
            $tenant = Tenant::create([
                'name'                 => $this->input['name'],
                'slug'                 => $this->input['slug'],
                'plan_id'              => $this->input['plan_id'],
                'country'              => $this->input['country'],
                'timezone'             => $this->input['timezone'],
                'base_currency'        => $this->input['base_currency'],
                'status'               => $this->input['start_as'],
                'trial_ends_on'        => $this->input['trial_ends_on'] ?? null,
                'source'               => $this->input['source'],
                'checkout_reference'   => $this->input['checkout_reference'] ?? null,
                'primary_color'        => '#FFC61A',
                'locale'               => 'en',
            ]);

            // Step 2 — seven standard roles + their permissions from the signed CSV
            app(\App\Services\Tenants\SeedStandardRoles::class)->handle($tenant);

            // Step 3 — number sequences (D23)
            foreach (['SR','QT','INV','CT','PAY'] as $prefix) {
                $tenant->sequences()->create([
                    'prefix' => $prefix,
                    'pattern' => "{$prefix}-YYYY-000001",
                    'reset_yearly' => true,
                ]);
            }

            // Step 4 — default settings (6.2 step 4)
            $tenant->settings()->create([
                'reopen_window_days'        => 14,
                'feedback_link_days'        => 7,
                'client_sign_in_link_hours' => 24,
                'require_two_factor'        => false,
            ]);

            // Step 5 — default vocabulary (10 renamable terms)
            // Step 6 — this month's usage counter row
            // Step 7 — first admin + invitation email
            $admin = User::create([
                'tenant_id'  => $tenant->id,
                'role_id'    => Role::where('tenant_id', $tenant->id)
                                   ->where('code', 'company_admin')->first()->id,
                'name'       => $this->input['first_admin_name'],
                'email'      => $this->input['first_admin_email'],
                'password'   => bcrypt(bin2hex(random_bytes(16))), // placeholder, invite flow sets real one
                'must_change_password' => false,
            ]);
            SecureLink::createInvitation($admin, lifetimeDays: 7);  // D3

            // Step 8 — master data placeholder (slice 2 will fill)
            // Step 9 — audit entry
            app(\App\Services\Audit\AuditLogger::class)->log('company.created', [
                'tenant_id' => $tenant->id,
                'admin_email' => $this->input['first_admin_email'],
            ]);
        });
    }

    public function failed(\Throwable $e): void
    {
        // "The company could not be set up. Nothing was created. Try again."
        logger()->error('company_provisioning_failed', [
            'input' => $this->input,
            'error' => $e->getMessage(),
        ]);
    }
}