<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');                              // 0=Sunday ... 6=Saturday
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id','weekday'], 'uq_tenant_weekday');
        });
    }
    public function down(): void { Schema::dropIfExists('tenant_business_hours'); }
};