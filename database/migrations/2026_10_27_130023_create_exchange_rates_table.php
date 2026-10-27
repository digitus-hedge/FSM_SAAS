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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tenant_id');

            $table->char('from_currency', 3);
            $table->char('to_currency', 3);

            $table->decimal('rate', 18, 8);
            $table->date('rate_date');

            $table->enum('source', ['provider', 'manual'])->default('provider');

            $table->timestamps();

            // Unique + indexes
            $table->unique(
                ['tenant_id', 'from_currency', 'to_currency', 'rate_date'],
                'uq_tenant_pair_date'
            );
            $table->index(['tenant_id', 'rate_date'], 'idx_tenant_rate_date');
            $table->index(['from_currency', 'to_currency'], 'idx_pair');

            // Foreign keys (adjust referenced table/column to your schema)
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};