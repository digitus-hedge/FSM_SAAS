<?php
// 2026_10_20_100003_add_tenancy_columns_to_tenants.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // SCH-05 — where the company came from, and website payment ref
            $table->enum('source', ['platform_admin', 'website'])
                  ->default('platform_admin')->after('status');
            $table->string('checkout_reference', 120)->nullable()->after('source');

            // Plan the company is on (TEN-02, needed before TEN-04 provisioning)
            $table->foreignId('plan_id')->nullable()->after('checkout_reference')
                  ->constrained()->nullOnDelete();

            // Base currency locks after first quote/invoice (CLAUDE.md §4)
            $table->char('base_currency', 3)->nullable()->after('plan_id');

            // D10 — trial end date, blank = no end
            $table->date('trial_ends_on')->nullable()->after('base_currency');

            // Idempotency for TEN-04 set-up job (safe to run twice)
            $table->unique('checkout_reference');
            $table->index(['status', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['checkout_reference']);
            $table->dropIndex(['status', 'plan_id']);
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['source', 'checkout_reference', 'plan_id', 'base_currency', 'trial_ends_on']);
        });
    }
};