<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Preventive-Maintenance spec: SCH-B4-01 pm_schedules, SCH-B4-02 pm_schedule_assets, SCH-B4-04 pm_schedule_runs, SCH-B4-03 request columns. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_schedules', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('name', 180);
            $t->unsignedBigInteger('project_site_id');
            $t->unsignedBigInteger('service_category_id');
            $t->enum('frequency', ['weekly', 'monthly', 'quarterly', 'half_yearly', 'yearly', 'every_n_days', 'every_n_months']);
            $t->unsignedSmallInteger('interval_value')->nullable();
            $t->date('first_due_date');
            $t->date('next_due_date');
            $t->unsignedTinyInteger('lead_days')->default(0);
            $t->string('priority', 20)->default('medium');
            $t->unsignedBigInteger('form_template_id')->nullable();
            $t->unsignedBigInteger('default_technician_user_id')->nullable();
            $t->unsignedBigInteger('contract_id')->nullable();
            $t->boolean('skip_if_open')->default(true);
            $t->enum('status', ['active', 'paused', 'ended'])->default('active');
            $t->date('end_date')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by_user_id')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status', 'next_due_date'], 'idx_pm_due');
        });

        Schema::create('pm_schedule_assets', function (Blueprint $t) {
            $t->unsignedBigInteger('pm_schedule_id');
            $t->unsignedBigInteger('asset_id');
            $t->unsignedBigInteger('tenant_id');
            $t->primary(['pm_schedule_id', 'asset_id']);
        });

        Schema::create('pm_schedule_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('pm_schedule_id');
            $t->date('due_date');
            $t->enum('result', ['created', 'skipped_open', 'skipped_paused', 'needs_contract', 'error']);
            $t->unsignedBigInteger('service_request_id')->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['pm_schedule_id', 'due_date'], 'uq_pm_run');
        });

        Schema::table('service_requests', function (Blueprint $t) {
            $t->date('pm_due_date')->nullable();
            $t->timestamp('pm_overdue_first_at')->nullable();
            $t->timestamp('pm_overdue_second_at')->nullable();
            $t->unique(['pm_schedule_id', 'pm_due_date'], 'uq_sr_pm_due');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $t) {
            $t->dropUnique('uq_sr_pm_due');
            $t->dropColumn(['pm_due_date', 'pm_overdue_first_at', 'pm_overdue_second_at']);
        });
        Schema::dropIfExists('pm_schedule_runs');
        Schema::dropIfExists('pm_schedule_assets');
        Schema::dropIfExists('pm_schedules');
    }
};
