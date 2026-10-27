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
        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tenant_id');
            $table->string('invoice_number', 50);

            $table->unsignedBigInteger('service_request_id')->nullable();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('quote_id')->nullable();

            $table->enum('source', ['builder', 'uploaded_pdf']);

            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'sent',
                'partially_paid',
                'paid',
                'overdue',
                'written_off',
                'void',
            ])->default('draft');

            // Currency / FX
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->enum('rate_source', ['provider', 'manual'])->default('provider');

            // Dates
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();

            // Monetary totals
            $table->decimal('subtotal', 14, 3)->default(0);
            $table->decimal('tax_total', 14, 3)->default(0);
            $table->decimal('discount_total', 14, 3)->default(0);
            $table->decimal('total', 14, 3)->default(0);
            $table->decimal('amount_paid', 14, 3)->default(0);
            $table->decimal('balance_due', 14, 3)->default(0);
            $table->decimal('base_total', 14, 3)->default(0);

            // Discount config
            $table->enum('discount_type', ['none', 'percent', 'amount'])->default('none');
            $table->decimal('discount_value', 12, 3)->default(0);

            // Snapshots / attachments
            $table->json('bank_details_snapshot_json')->nullable();
            $table->unsignedBigInteger('pdf_attachment_id')->nullable();

            // Approval workflow
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_rejection_reason', 255)->nullable()
                ->comment('v3.4, set when a manager sends the invoice back');

            // Sending
            $table->timestamp('sent_at')->nullable();

            // Void
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();

            // Write-off
            $table->decimal('write_off_amount', 14, 3)->default(0);
            $table->timestamp('written_off_at')->nullable();
            $table->string('write_off_reason', 255)->nullable();

            // Misc
            $table->string('legal_invoice_number', 60)->nullable();
            $table->string('notes', 500)->nullable();

            $table->timestamps();

            // Unique + indexes
            $table->unique(['tenant_id', 'invoice_number'], 'uq_tenant_invoice_number');
            $table->index(['tenant_id', 'status', 'due_date'], 'idx_tenant_status_due');
            $table->index('client_id', 'idx_client');
            $table->index('service_request_id', 'idx_service_request');
            $table->index('quote_id', 'idx_quote');
            $table->index(['tenant_id', 'issue_date'], 'idx_tenant_issue_date');

            // Foreign keys (adjust referenced tables/columns to your schema)
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('client_id')
                ->references('id')->on('clients')
                ->restrictOnDelete();

            $table->foreign('service_request_id')
                ->references('id')->on('service_requests')
                ->nullOnDelete();

            $table->foreign('quote_id')
                ->references('id')->on('quotes')
                ->nullOnDelete();

            $table->foreign('pdf_attachment_id')
                ->references('id')->on('attachments')
                ->nullOnDelete();

            $table->foreign('submitted_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->foreign('approved_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};