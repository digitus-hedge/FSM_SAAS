
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sr_reschedules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_request_id');

            $table->timestamp('previous_eta_at')->nullable();
            $table->timestamp('new_eta_at');

            $table->string('reason', 255)->nullable();

            $table->enum('source', [
                'drag_drop',
                'technician_request',
                'system',
            ]);

            $table->unsignedBigInteger('rescheduled_by_user_id')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['tenant_id', 'service_request_id'],
                'idx_tenant_sr'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sr_reschedules');
    }
};
