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
        Schema::create('field_event_log', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('device_id')->nullable();

            $table->char('client_event_uuid', 36);
            $table->string('event_type', 40);

            $table->unsignedBigInteger('service_request_id')->nullable();

            $table->timestamp('recorded_at_device');
            $table->timestamp('synced_to_server_at')->useCurrent();

            $table->integer('clock_drift_seconds')->nullable();

            $table->enum('result', [
                'applied',
                'duplicate',
                'conflict_rejected',
                'invalid',
            ]);

            $table->string('conflict_code', 40)->nullable();
            $table->string('conflict_note', 255)->nullable();

            $table->char('payload_hash', 64);
            $table->json('payload_json');

            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();

            $table->timestamps();

            // Unique + indexes
            $table->unique(['tenant_id', 'client_event_uuid'], 'uq_tenant_event');
            $table->index(['tenant_id', 'service_request_id', 'result'], 'idx_tenant_sr_result');
            $table->index(['tenant_id', 'user_id', 'recorded_at_device'], 'idx_tenant_user_time');
            $table->index(['tenant_id', 'event_type'], 'idx_tenant_event_type');
            $table->index('device_id', 'idx_device');
            $table->index('reviewed_by_user_id', 'idx_reviewed_by');

            // Foreign keys (adjust referenced tables/columns to match your schema)
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->restrictOnDelete();

            $table->foreign('device_id')
                ->references('id')->on('devices')
                ->nullOnDelete();

            $table->foreign('service_request_id')
                ->references('id')->on('service_requests')
                ->nullOnDelete();

            $table->foreign('reviewed_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_event_log');
    }
};