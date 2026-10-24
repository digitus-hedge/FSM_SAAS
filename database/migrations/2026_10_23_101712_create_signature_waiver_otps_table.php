
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_waiver_otps', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('client_contact_id');
            $table->unsignedBigInteger('requested_by_user_id');

            $table->enum('channel', [
                'whatsapp',
                'email',
            ]);

            $table->char('code_hash', 64);
            $table->timestamp('expires_at');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index(
                ['tenant_id', 'service_request_id'],
                'idx_tenant_sr'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_waiver_otps');
    }
};
