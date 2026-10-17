<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name', 120);
            $table->timestamps();

            $table->unique(['tenant_id','holiday_date'], 'uq_tenant_date');
        });
    }
    public function down(): void { Schema::dropIfExists('tenant_holidays'); }
};