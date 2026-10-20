<?php
// 2026_10_20_000002_create_roles_table.php
// Per-tenant roles. 7 standard rows seeded for every new company by TEN-04.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // One of the 7 standard codes, or a slug for a custom role.
            $table->string('code', 60);
            $table->string('name', 80);
            $table->boolean('is_standard')->default(false);
            // For a custom role, which standard code it was based on (D13).
            $table->string('based_on_code', 60)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite unique starts with tenant_id (CLAUDE.md tenancy rule).
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_standard']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};