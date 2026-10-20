<?php
// 2026_10_20_100006_restructure_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop anything we're replacing
            if (Schema::hasColumn('users', 'vendor_id')) {
                $table->dropForeign(['vendor_id']);
                $table->dropColumn('vendor_id');
            }
            // client_id stays (client users), but needs reposition after tenant_id
        });

        Schema::table('users', function (Blueprint $table) {
            // Tenancy — the keystone column. CLAUDE.md: every company table has tenant_id.
            $table->foreignId('tenant_id')->after('id')->constrained()->cascadeOnDelete();

            // Role is a FK to the per-tenant roles table, never a string.
            $table->foreignId('role_id')->nullable()->after('tenant_id')
                  ->constrained()->nullOnDelete();

            // Technician may have phone instead of email (D9). Make email nullable.
            $table->string('email', 190)->nullable()->change();
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('employee_code', 40)->nullable()->after('phone');

            // Team lead FK — technicians only (self-reference)
            $table->foreignId('team_lead_id')->nullable()->after('employee_code')
                  ->constrained('users')->nullOnDelete();

            // Client access (D15) — Admin or Viewer. SCH-07.
            $table->enum('client_access', ['admin', 'viewer'])->nullable()->after('client_id');
            $table->json('site_scope_json')->nullable()->after('client_access');

            // Auth — SCH-08, SCH-09, SCH-10
            // (must_change_password already exists; leave it.)
            $table->unsignedSmallInteger('failed_login_count')->default(0)
                  ->after('must_change_password');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');
            $table->text('two_factor_secret')->nullable()->after('locked_until'); // cast as encrypted
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');

            // SCH-06 — reserved for one-login-across-products (D25). Unused in Release A.
            $table->unsignedBigInteger('aiywah_account_id')->nullable()
                  ->after('two_factor_confirmed_at');

            // Lifecycle
            $table->timestamp('last_sign_in_at')->nullable()->after('aiywah_account_id');
            $table->timestamp('deactivated_at')->nullable()->after('last_sign_in_at');

            // Spec says the same email can exist in two companies (AT-22).
            // So uniqueness is per-tenant, not global. Drop any global unique on email.
        });

        // Drop the global unique on email, replace with per-tenant uniques
        Schema::table('users', function (Blueprint $table) {
            // Drop Laravel's default unique index on email if present
            try { $table->dropUnique('users_email_unique'); } catch (\Throwable $e) {}
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
            $table->unique(['tenant_id', 'phone'], 'users_tenant_phone_unique');
            $table->unique(['tenant_id', 'employee_code'], 'users_tenant_empcode_unique');
            $table->index(['tenant_id', 'role_id']);
            $table->index(['tenant_id', 'deactivated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_email_unique');
            $table->dropUnique('users_tenant_phone_unique');
            $table->dropUnique('users_tenant_empcode_unique');
            $table->dropIndex(['tenant_id', 'role_id']);
            $table->dropIndex(['tenant_id', 'deactivated_at']);
            $table->dropForeign(['team_lead_id']);
            $table->dropForeign(['role_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn([
                'tenant_id', 'role_id', 'phone', 'employee_code', 'team_lead_id',
                'client_access', 'site_scope_json',
                'failed_login_count', 'locked_until',
                'two_factor_secret', 'two_factor_confirmed_at',
                'aiywah_account_id', 'last_sign_in_at', 'deactivated_at',
            ]);
            $table->unique('email');
        });
    }
};