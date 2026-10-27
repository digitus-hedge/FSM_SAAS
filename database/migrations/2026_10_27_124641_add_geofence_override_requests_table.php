<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('geofence_override_requests', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('technician_user_id');

            $table->char('client_event_uuid', 36);
            $table->string('reason', 255);

            $table->unsignedBigInteger('photo_attachment_id')->nullable();

            $table->timestamp('requested_at_device');

            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->unsignedInteger('gps_accuracy_meters')->nullable();
            $table->unsignedInteger('site_distance_meters')->nullable();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'expired',
                'used',
                'cancelled',
            ])->default('pending');

            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();

            $table->timestamp('valid_until')->nullable();
            $table->unsignedBigInteger('used_punch_id')->nullable();

            $table->timestamps();

            // Unique + indexes
            $table->unique(['tenant_id', 'client_event_uuid'], 'uq_tenant_event');
            $table->index(['tenant_id', 'service_request_id', 'status'], 'idx_tenant_sr');
            $table->index('technician_user_id', 'idx_technician');
            $table->index('status', 'idx_status');
            $table->index('decided_by_user_id', 'idx_decided_by');

            // Foreign keys (adjust referenced tables/columns to your schema)
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('service_request_id')
                ->references('id')->on('service_requests')
                ->cascadeOnDelete();

            $table->foreign('technician_user_id')
                ->references('id')->on('users')
                ->restrictOnDelete();

            $table->foreign('decided_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->foreign('photo_attachment_id')
                ->references('id')->on('attachments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofence_override_requests');
    }
};