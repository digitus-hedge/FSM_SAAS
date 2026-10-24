
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sr_status_history', function (Blueprint $table) {
            $table->id();

            // Tenant and service request
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_request_id');

            // Status transition
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('custom_stage_code', 40)->nullable();

            // Who performed the change
            $table->enum('actor_type', [
                'user',
                'client',
                'system',
            ]);

            $table->unsignedBigInteger('actor_id')->nullable();

            // Reason and additional metadata
            $table->text('reason')->nullable();
            $table->json('meta_json')->nullable();

            // Creation timestamp used by the history index
            $table->timestamps();

            // Index
            $table->index(
                ['tenant_id', 'service_request_id', 'created_at'],
                'idx_tenant_sr_time'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sr_status_history');
    }
};
