<?php
// 2026_10_20_000004_create_user_permission_overrides_table.php
// One-off per-user grants or revokes on top of the role (ACC-09, D16).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->enum('level', [
                'full', 'grantable', 'record_level',
                'view_only', 'view_own', 'edit_own', 'none',
            ]);
            $table->string('reason', 255)->nullable();
            $table->timestamp('ends_at')->nullable(); // AT-49: expiring overrides
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'permission_id'], 'upo_tenant_user_perm_unique');
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};