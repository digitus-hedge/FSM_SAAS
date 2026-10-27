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
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('invoice_id');

            $table->string('payment_number', 50);

            // Monetary (invoice currency + base currency conversion)
            $table->decimal('amount', 14, 3)->comment('invoice currency');
            $table->decimal('base_amount', 14, 3);

            $table->date('paid_on');

            $table->enum('method', [
                'cash',
                'bank_transfer',
                'card',
                'cheque',
                'online',
                'other',
            ]);

            $table->string('reference', 120)->nullable();
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->string('notes', 255)->nullable();

            // Reversal
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 255)->nullable();
            $table->unsignedBigInteger('reversed_by_user_id')->nullable();

            $table->timestamps();

            // Unique + indexes
            $table->unique(['tenant_id', 'payment_number'], 'uq_tenant_payment_number');
            $table->index(['tenant_id', 'invoice_id'], 'idx_tenant_invoice');
            $table->index('recorded_by_user_id', 'idx_recorded_by');
            $table->index('reversed_by_user_id', 'idx_reversed_by');
            $table->index(['tenant_id', 'paid_on'], 'idx_tenant_paid_on');
            $table->index('method', 'idx_method');

            // Foreign keys (adjust referenced tables/columns to your schema)
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('invoice_id')
                ->references('id')->on('invoices')
                ->cascadeOnDelete();

            $table->foreign('recorded_by_user_id')
                ->references('id')->on('users')
                ->restrictOnDelete();

            $table->foreign('reversed_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};