
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_punches', function (Blueprint $table) {
            $table->id();

            // Tenant and technician details
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('technician_user_id');

            // Idempotency key for duplicate offline sync prevention
            $table->char('client_event_uuid', 36);

            // Punch event details
            $table->enum('punch_type', [
                'punch_in',
                'hold_start',
                'hold_resume',
                'punch_out',
            ]);

            $table->unsignedBigInteger('hold_reason_id')->nullable();

            // Device and server timestamps
            $table->timestamp('recorded_at_device');
            $table->timestamp('synced_to_server_at')
                ->useCurrent();

            $table->boolean('is_offline_sync')->default(false);
            $table->integer('clock_drift_seconds')->nullable();

            // GPS coordinates
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->unsignedInteger('gps_accuracy_meters')->nullable();

            $table->enum('gps_status', [
                'ok',
                'low_accuracy',
                'denied',
                'unavailable',
            ])->default('ok');

            // Geofence verification
            $table->unsignedInteger('site_distance_meters')->nullable();
            $table->boolean('is_within_geofence')->nullable();

            $table->string('geofence_override_reason', 255)->nullable();
            $table->unsignedBigInteger('geofence_override_by_user_id')->nullable();

            // Synchronization result
            $table->enum('sync_status', [
                'synced',
                'conflict_rejected',
            ])->default('synced');

            $table->string('conflict_note', 255)->nullable();
            $table->string('device_info', 255)->nullable();

            // Indexes
            $table->unique(
                ['tenant_id', 'client_event_uuid'],
                'uq_tenant_event'
            );

            $table->index(
                ['tenant_id', 'service_request_id', 'recorded_at_device'],
                'idx_tenant_sr_punches'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_punches');
    }
};
