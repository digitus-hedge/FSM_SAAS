<?php
// 2026_10_20_100011_create_auth_codes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['whatsapp', 'email']);
            $table->char('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Fast lookup of the most recent live code for a user
            $table->index(['tenant_id', 'user_id', 'expires_at'], 'auth_codes_live_lookup');
        });
    }
    public function down(): void { Schema::dropIfExists('auth_codes'); }
};