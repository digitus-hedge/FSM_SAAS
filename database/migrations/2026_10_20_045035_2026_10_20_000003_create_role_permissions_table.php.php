<?php
// 2026_10_20_000003_create_role_permissions_table.php
// The signed level for each (tenant, role, permission). Seeded from CSV.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            // The 7 access levels. 'none' means the role does not hold this key.
            $table->enum('level', [
                'full', 'grantable', 'record_level',
                'view_only', 'view_own', 'edit_own', 'none',
            ]);
            $table->timestamps();

            $table->unique(['tenant_id', 'role_id', 'permission_id'], 'rp_tenant_role_perm_unique');
            $table->index(['tenant_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};