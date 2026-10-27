<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->unsignedBigInteger('quote_id');
            $table->unsignedSmallInteger('line_no');

            $table->string('description', 255);
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_rate', 12, 3);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('line_total', 14, 3);
            $table->unsignedBigInteger('source_expense_id')->nullable();

            $table->timestamps();

            // Composite primary key
            $table->primary(['quote_id', 'line_no']);

            // Indexes for FK lookups
            $table->index('tax_rate_id', 'idx_tax_rate');
            $table->index('source_expense_id', 'idx_source_expense');

            // Foreign keys (adjust referenced tables to match your schema)
            $table->foreign('quote_id')
                ->references('id')->on('quotes')
                ->cascadeOnDelete();

            $table->foreign('tax_rate_id')
                ->references('id')->on('tax_rates')
                ->nullOnDelete();

            $table->foreign('source_expense_id')
                ->references('id')->on('service_request_expenses')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};