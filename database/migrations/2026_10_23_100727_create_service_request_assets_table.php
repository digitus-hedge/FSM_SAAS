
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_assets', function (Blueprint $table) {
            $table->id();

            // Tenant and asset relationships
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('asset_id');

            // Inspection details
            $table->enum('inspection_status', [
                'pending',
                'serviced_ok',
                'repaired',
                'needs_parts',
                'failed_unsafe',
                'skipped',
            ])->default('pending');

            // QR verification
            $table->boolean('qr_scanned_verify')->default(false);
            $table->timestamp('scanned_at')->nullable();

            // Checklist and meter reading
            $table->unsignedBigInteger('form_template_version_id')->nullable();
            $table->json('asset_checklist_payload')->nullable();
          $table->decimal('recorded_meter_reading', 12, 2)->unsigned()->nullable();

            // Inspection photos
            $table->json('before_photo_paths')->nullable();
            $table->json('after_photo_paths')->nullable();

            // Technician notes and completion
            $table->text('technician_remarks')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Indexes
            $table->unique(
                ['tenant_id', 'service_request_id', 'asset_id'],
                'uq_sr_asset'
            );

            $table->index(
                ['tenant_id', 'asset_id', 'completed_at'],
                'idx_tenant_asset_history'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_assets');
    }
};
