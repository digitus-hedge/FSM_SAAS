<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('service_domain', 80)->nullable();
            $table->unsignedBigInteger('active_form_template_id')->nullable();   // FK added in Release B
            $table->json('custom_workflow_json')->nullable();
            $table->enum('approval_mode', [
                'manual','auto_if_in_contract','always_auto','finance_and_manager',
            ])->default('manual');

            // Proof rules
            $table->boolean('require_before_photo')->default(true);
            $table->boolean('require_after_photo')->default(true);
            $table->boolean('require_signature')->default(true);
            $table->boolean('allow_signature_waiver')->default(false);           // v3.2
            $table->boolean('require_checklist')->default(false);
            $table->boolean('require_gps_geofence')->default(false);
            $table->unsignedInteger('geofence_radius_meters')->default(250);

            // Batch + depot
            $table->boolean('allow_multi_asset_batch')->default(true);
            $table->boolean('require_per_asset_checklist')->default(false);
            $table->boolean('allow_depot_mode')->default(false);                 // v3.2

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id','code'], 'uq_tenant_category_code');
            $table->index(['tenant_id','is_active'], 'idx_tenant_active');
        });
    }
    public function down(): void { Schema::dropIfExists('service_categories'); }
};