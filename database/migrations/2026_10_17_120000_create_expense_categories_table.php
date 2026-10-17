<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('default_unit_rate', 12, 2)->nullable();
            $table->boolean('is_billable_default')->default(true);
            $table->boolean('requires_receipt')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id','is_active']);
        });
    }
    public function down(): void { Schema::dropIfExists('expense_categories'); }
};