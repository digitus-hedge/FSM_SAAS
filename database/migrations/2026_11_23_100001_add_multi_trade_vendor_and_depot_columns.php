<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-Trade-Vendors-Depot spec: SCH-B6-01 to SCH-B6-07.
 * service_request_expenses.vendor_id (SCH-B6-06) and project_sites.client_id / site_type (SCH-B6-03) already exist in the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->boolean('may_log_expenses')->default(true);
        });

        Schema::table('vendors', function (Blueprint $t) {
            $t->boolean('default_may_log_expenses')->default(false);
        });

        Schema::table('service_categories', function (Blueprint $t) {
            $t->unsignedBigInteger('intake_form_template_id')->nullable();
            $t->unsignedBigInteger('return_form_template_id')->nullable();
        });

        Schema::table('service_requests', function (Blueprint $t) {
            $t->enum('depot_stage', ['received', 'in_workshop', 'ready_for_return', 'returned'])->nullable();
            $t->timestamp('depot_received_at')->nullable();
            $t->timestamp('depot_returned_at')->nullable();
            $t->unsignedBigInteger('home_site_id')->nullable();
            // intake and return checklists (D15), bound at creation like the main checklist
            $t->unsignedBigInteger('intake_form_version_id')->nullable();
            $t->unsignedBigInteger('return_form_version_id')->nullable();
            $t->json('intake_payload')->nullable();
            $t->json('return_payload')->nullable();
            $t->enum('intake_result', ['ok', 'issues'])->nullable();
            $t->enum('return_result', ['ok', 'issues'])->nullable();
            // a trade of a master: who and when it is planned for, applied when the master is approved
            $t->unsignedBigInteger('planned_tech_user_id')->nullable();
            $t->timestamp('planned_at')->nullable();
        });

        foreach (Plan::all() as $plan) {
            $f = $plan->features ?? [];
            foreach (['multi_trade', 'vendors', 'depot'] as $key) {
                $f[$key] = $plan->code !== 'starter';   // blueprint section 6: Professional and Enterprise
            }
            $plan->features = $f;
            $plan->save();
        }
    }

    public function down(): void
    {
        Schema::table('service_requests', fn (Blueprint $t) => $t->dropColumn([
            'depot_stage', 'depot_received_at', 'depot_returned_at', 'home_site_id', 'intake_form_version_id', 'return_form_version_id',
            'intake_payload', 'return_payload', 'intake_result', 'return_result', 'planned_tech_user_id', 'planned_at',
        ]));
        Schema::table('service_categories', fn (Blueprint $t) => $t->dropColumn(['intake_form_template_id', 'return_form_template_id']));
        Schema::table('vendors', fn (Blueprint $t) => $t->dropColumn('default_may_log_expenses'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['vendor_id', 'may_log_expenses']));

        foreach (Plan::all() as $plan) {
            $f = $plan->features ?? [];
            unset($f['multi_trade'], $f['vendors'], $f['depot']);
            $plan->features = $f;
            $plan->save();
        }
    }
};
