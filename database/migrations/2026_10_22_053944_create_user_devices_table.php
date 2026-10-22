<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            // Polymorphic: 'user' for App\Models\User, 'platform_user' for App\Models\PlatformUser.
            $table->string('user_type', 30);
            $table->unsignedBigInteger('user_id');
            $table->string('label', 150);         // "Chrome 128 on Windows 11"
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('session_id', 120)->nullable();   // tags THIS device
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('trusted_until');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id', 'revoked_at']);
            $table->index(['tenant_id', 'user_id']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};