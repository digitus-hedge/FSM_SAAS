<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contracts step 1b. Requirements SCH-B2-01 to SCH-B2-03.
 *  - contracts: activated_at, cancelled_at, cancel_reason, auto_renewed_at
 *  - contract_calls (new): the call log, used by the cap in a later step
 *  - service_requests: contract_choice_reason, contract_over_cap
 * Every change is guarded, so the migration is safe to run on a database that already has a column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $t) {
            if (! Schema::hasColumn('contracts', 'activated_at'))    $t->timestamp('activated_at')->nullable()->after('notes');
            if (! Schema::hasColumn('contracts', 'cancelled_at'))    $t->timestamp('cancelled_at')->nullable()->after('activated_at');
            if (! Schema::hasColumn('contracts', 'cancel_reason'))   $t->string('cancel_reason', 255)->nullable()->after('cancelled_at');
            if (! Schema::hasColumn('contracts', 'auto_renewed_at')) $t->timestamp('auto_renewed_at')->nullable()->after('cancel_reason');
        });

        if (! Schema::hasTable('contract_calls')) {
            Schema::create('contract_calls', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->constrained('tenants');
                $t->foreignId('contract_id')->constrained('contracts');
                $t->foreignId('service_request_id')->constrained('service_requests');
                $t->enum('action', ['counted', 'given_back']);
                $t->boolean('over_cap')->default(false);
                $t->date('period_started_on');
                $t->foreignId('user_id')->nullable()->constrained('users');
                $t->timestamp('created_at')->useCurrent();

                $t->index(['contract_id', 'period_started_on'], 'idx_cc_contract_period');
                $t->index('service_request_id', 'idx_cc_request');
            });
        }

        Schema::table('service_requests', function (Blueprint $t) {
            if (! Schema::hasColumn('service_requests', 'contract_choice_reason')) {
                $t->enum('contract_choice_reason', ['several', 'cap_reached'])->nullable();
            }
            if (! Schema::hasColumn('service_requests', 'contract_over_cap')) {
                $t->boolean('contract_over_cap')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $t) {
            if (Schema::hasColumn('service_requests', 'contract_over_cap'))      $t->dropColumn('contract_over_cap');
            if (Schema::hasColumn('service_requests', 'contract_choice_reason')) $t->dropColumn('contract_choice_reason');
        });

        Schema::dropIfExists('contract_calls');

        Schema::table('contracts', function (Blueprint $t) {
            foreach (['auto_renewed_at', 'cancel_reason', 'cancelled_at', 'activated_at'] as $c) {
                if (Schema::hasColumn('contracts', $c)) $t->dropColumn($c);
            }
        });
    }
};
