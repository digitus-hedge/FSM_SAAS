
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sr_notes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('user_id')->nullable();

            $table->enum('visibility', [
                'internal',
                'client_visible',
            ])->default('internal');

            $table->enum('source', [
                'user',
                'whatsapp_inbound',
                'system',
            ])->default('user');

            $table->text('body');

            $table->timestamps();

            $table->index(
                ['tenant_id', 'service_request_id'],
                'idx_tenant_sr'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sr_notes');
    }
};
