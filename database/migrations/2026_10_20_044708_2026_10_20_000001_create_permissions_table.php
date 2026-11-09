<?php
// 2026_10_20_000001_create_permissions_table.php
// The 42 keys from permission_matrix.csv. Global, not per-tenant.
// This is the catalog; starting values go in role_permissions via the seeder.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();      // e.g. 'sr.approve'
            $table->string('group', 60);              // e.g. 'Service requests'
            $table->string('description', 255);
            // Plan feature key this permission requires, or null.
            // Keys live in blueprint section 6. Used by the engine for FEATURE_NOT_IN_PLAN.
            $table->string('feature_required', 60)->nullable();
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};