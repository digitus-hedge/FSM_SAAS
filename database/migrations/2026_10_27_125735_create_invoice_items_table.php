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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('invoice_id');
            $table->unsignedSmallInteger('line_no');

            $table->string('description', 255);
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_rate', 12, 3);

            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('line_total', 14, 3);

            $table->unsignedBigInteger('source_expense_id')->nullable();

            $table->timestamps();

            // Unique + indexes
            $table->unique(['invoice_id', 'line_no'], 'uq_invoice_line');
            $table->index('tax_rate_id', 'idx_tax_rate');
            $table->index('source_expense_id', 'idx_source_expense');

            // Foreign keys (adjust referenced tables/columns to your schema)
            $table->foreign('invoice_id')
                ->references('id')->on('invoices')
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
        Schema::dropIfExists('invoice_items');
    }
};