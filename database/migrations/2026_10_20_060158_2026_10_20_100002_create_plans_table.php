<?php
// 2026_10_20_100002_create_plans_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();            // starter | professional | enterprise
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            // Limits — blank (null) means unlimited (per §5.2)
            $table->unsignedInteger('user_limit')->nullable();
            $table->unsignedInteger('requests_per_month_limit')->nullable();
            $table->unsignedInteger('storage_gb_limit')->nullable();
            $table->unsignedInteger('api_requests_per_minute')->default(60);
            // Features as JSON — keys from blueprint §6, values are bool or number
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('plans'); }
};