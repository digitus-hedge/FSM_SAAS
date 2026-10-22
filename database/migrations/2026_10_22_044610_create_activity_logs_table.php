<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Null for platform-level actions (not tied to a tenant).
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();

            // Who did it. Polymorphic because actors can be users, platform_users, or system.
            $table->string('actor_type', 40);         // 'user' | 'platform_user' | 'system'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 150)->nullable();   // denorm for display

            $table->string('action', 80);             // e.g. 'user.created', 'role.permissions_changed'
            $table->string('item_type', 120)->nullable();    // e.g. 'App\\Models\\User'
            $table->unsignedBigInteger('item_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();    // IPv6-safe
            $table->string('user_agent', 500)->nullable();

            // Hash chain — any tampering breaks the chain, which the audit page can show.
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'actor_type', 'actor_id']);
            $table->index(['tenant_id', 'item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};