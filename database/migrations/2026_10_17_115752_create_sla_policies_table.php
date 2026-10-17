<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->nullable()
                  ->constrained('service_categories')->cascadeOnDelete();        // null = tenant default
            $table->enum('priority', ['low','medium','high','critical','emergency']);
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->boolean('uses_business_hours')->default(false);
            $table->timestamps();

            $table->index(['tenant_id','service_category_id','priority'],
                          'idx_tenant_cat_priority');
        });
    }
    public function down(): void { Schema::dropIfExists('sla_policies'); }
};