<?php
// 2026_10_20_100009_create_user_skills_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // service_categories table arrives with Master data (slice 2).
            // Not FK-constrained yet to avoid a cross-slice dependency.
            $table->unsignedBigInteger('service_category_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'service_category_id'], 'us_t_u_c_unique');
            $table->index(['tenant_id', 'service_category_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('user_skills'); }
};