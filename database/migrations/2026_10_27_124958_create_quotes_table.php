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
        Schema::create('quotes', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tenant_id');
            $table->string('quote_number', 50);

            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('client_id');

            $table->enum('source', ['builder', 'uploaded_pdf']);

            $table->enum('status', [
                'draft',
                'sent',
                'accepted',
                'rejected',
                'expired',
                'superseded',
            ])->default('draft');

            $table->unsignedInteger('version_no')->default(1);
            $table->unsignedBigInteger('supersedes_quote_id')->nullable();

            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1); // to base
            $table->enum('rate_source', ['provider', 'manual'])->default('provider');

            $table->decimal('subtotal', 14, 3)->default(0);
            $table->decimal('tax_total', 14, 3)->default(0);
            $table->decimal('discount_total', 14, 3)->default(0);
            $table->decimal('total', 14, 3)->default(0);
            $table->decimal('base_total', 14, 3)->default(0);

            $table->date('valid_until')->nullable();
            $table->text('terms')->nullable();
            $table->unsignedBigInteger('pdf_attachment_id')->nullable();

            $table->enum('discount_type', ['none', 'percent', 'amount'])->default('none');
            $table->decimal('discount_value', 12, 3)->default(0);

            $table->timestamp('expired_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('responded_by_name', 150)->nullable();
            $table->string('responded_ip', 45)->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedBigInteger('created_by_user_id');

            $table->timestamps();

            // Unique + indexes
            $table->unique(['tenant_id', 'quote_number'], 'uq_tenant_quote_number');
            $table->index(['tenant_id', 'service_request_id'], 'idx_tenant_sr');
            $table->index(['tenant_id', 'status'], 'idx_tenant_status');
            $table->index('client_id', 'idx_client');
            $table->index('supersedes_quote_id', 'idx_supersedes');
            $table->index('created_by_user_id', 'idx_created_by');

            // Foreign keys (adjust referenced tables/columns to your schema)
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('service_request_id')
                ->references('id')->on('service_requests')
                ->cascadeOnDelete();

            $table->foreign('client_id')
                ->references('id')->on('clients')
                ->restrictOnDelete();

            $table->foreign('supersedes_quote_id')
                ->references('id')->on('quotes')
                ->nullOnDelete();

            $table->foreign('pdf_attachment_id')
                ->references('id')->on('attachments')
                ->nullOnDelete();

            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};