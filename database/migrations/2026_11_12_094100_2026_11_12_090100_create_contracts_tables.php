<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Blueprint 11.6: contracts, contract_sites, contract_categories. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->string('contract_number', 60);
            $t->foreignId('client_id')->constrained('clients');
            $t->enum('contract_type', ['amc', 'warranty', 'adhoc', 'dlp']);
            $t->enum('status', ['draft', 'active', 'expiring_soon', 'expired', 'renewed', 'cancelled'])->default('draft');
            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedInteger('response_sla_minutes')->nullable();
            $t->unsignedInteger('resolution_sla_minutes')->nullable();
            $t->unsignedInteger('max_calls')->nullable();
            $t->enum('call_period', ['contract_term', 'monthly', 'quarterly', 'annual'])->default('contract_term');
            $t->unsignedInteger('calls_used_current_period')->default(0);
            $t->date('period_started_on')->nullable();
            $t->decimal('annual_value', 14, 3)->nullable();
            $t->string('payment_terms', 120)->nullable();
            $t->boolean('auto_renew')->default(false);
            $t->unsignedBigInteger('renewed_from_contract_id')->nullable();
            $t->text('notes')->nullable();
            $t->softDeletes();
            $t->timestamps();

            $t->unique(['tenant_id', 'contract_number'], 'uq_tenant_contract_number');
            $t->index(['tenant_id', 'client_id', 'status', 'end_date'], 'idx_tenant_client_status');
            $t->foreign('renewed_from_contract_id')->references('id')->on('contracts');
        });

        Schema::create('contract_sites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->foreignId('project_site_id')->constrained('project_sites');
            $t->timestamps();
            $t->unique(['contract_id', 'project_site_id'], 'uq_contract_site');
        });

        Schema::create('contract_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants');
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->foreignId('service_category_id')->constrained('service_categories');
            $t->timestamps();
            $t->unique(['contract_id', 'service_category_id'], 'uq_contract_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_categories');
        Schema::dropIfExists('contract_sites');
        Schema::dropIfExists('contracts');
    }
};
