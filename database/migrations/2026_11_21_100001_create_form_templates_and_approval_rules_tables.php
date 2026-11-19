<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Forms-Approvals-Batch spec: SCH-B3-01 form_templates, SCH-B3-02 form_template_versions, SCH-B3-03 approval_rules. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('name', 180);
            $t->string('description', 500)->nullable();
            $t->enum('status', ['active', 'archived'])->default('active');
            $t->unsignedBigInteger('created_by_user_id')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'name'], 'uq_form_template_name');
        });

        Schema::create('form_template_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('form_template_id');
            $t->unsignedInteger('version_no');
            $t->enum('status', ['draft', 'published', 'retired'])->default('draft');
            $t->json('schema_json');
            $t->timestamp('published_at')->nullable();
            $t->unsignedBigInteger('published_by_user_id')->nullable();
            $t->timestamp('retired_at')->nullable();
            $t->timestamps();
            $t->unique(['form_template_id', 'version_no'], 'uq_template_version');
            $t->index(['tenant_id', 'form_template_id', 'status'], 'idx_template_status');
        });

        Schema::create('approval_rules', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('service_category_id');
            $t->enum('mode', ['manual', 'auto_if_in_contract', 'always_auto', 'finance_and_manager'])->default('manual');
            $t->decimal('amount_limit_base', 14, 3)->nullable();
            $t->unsignedBigInteger('second_approver_user_id')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'service_category_id'], 'uq_approval_rule_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
        Schema::dropIfExists('form_template_versions');
        Schema::dropIfExists('form_templates');
    }
};
