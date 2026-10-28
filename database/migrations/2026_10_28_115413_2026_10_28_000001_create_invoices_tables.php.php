<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('invoice_number', 32);
            $t->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();

            $t->enum('source', ['builder', 'uploaded_pdf'])->default('builder');
            $t->enum('status', [
                'draft', 'pending_approval', 'approved', 'sent',
                'partially_paid', 'paid', 'overdue', 'written_off', 'void',
            ])->default('draft');

            $t->char('currency_code', 3)->default('AED');
            $t->decimal('exchange_rate', 14, 6)->default(1);
            $t->string('rate_source', 40)->nullable();

            $t->decimal('subtotal',       14, 3)->default(0);
            $t->decimal('tax_total',      14, 3)->default(0);
            $t->decimal('discount_total', 14, 3)->default(0);
            $t->decimal('total',          14, 3)->default(0);
            $t->decimal('balance',        14, 3)->default(0);
            $t->decimal('base_total',     14, 3)->default(0);

            $t->enum('discount_type', ['none', 'percent', 'amount'])->default('none');
            $t->decimal('discount_value', 14, 3)->default(0);

            $t->date('due_date')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('sent_at')->nullable();

            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamp('send_back_at')->nullable();
            $t->string('send_back_reason', 500)->nullable();

            $t->timestamp('voided_at')->nullable();
            $t->string('void_reason', 500)->nullable();

            $t->timestamp('written_off_at')->nullable();
            $t->decimal('write_off_amount', 14, 3)->nullable();
            $t->string('write_off_reason', 500)->nullable();

            $t->string('legal_invoice_number', 80)->nullable();
            $t->text('notes')->nullable();

            $t->foreignId('pdf_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $t->json('bank_snapshot_json')->nullable();

            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['tenant_id', 'invoice_number']);
            $t->index(['tenant_id', 'status']);
            $t->index('service_request_id');
            $t->index('client_id');
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->string('description', 255);
            $t->decimal('quantity',   10, 2);
            $t->string('unit', 20)->nullable();
            $t->decimal('unit_rate',  14, 3);
            $t->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $t->decimal('line_total', 14, 3);
            $t->foreignId('source_expense_id')->nullable();
            $t->timestamps();

            $t->index(['invoice_id', 'line_no']);
        });

        Schema::create('invoice_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 40);
            $t->string('label', 255)->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();

            $t->index(['invoice_id', 'occurred_at']);
        });

        Schema::create('invoice_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_contact_id')->nullable()->constrained('client_contacts')->nullOnDelete();
            $t->string('recipient_name', 150);
            $t->string('token', 64)->unique();
            $t->enum('sent_via', ['whatsapp', 'email'])->default('email');
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('viewed_first_at')->nullable();
            $t->unsignedInteger('viewed_count')->default(0);
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('payment_number', 32);
            $t->decimal('amount', 14, 3);
            $t->timestamp('received_at');
            $t->enum('method', ['cash', 'bank_transfer', 'cheque', 'card', 'other'])->default('bank_transfer');
            $t->string('reference', 120)->nullable();
            $t->text('note')->nullable();
            $t->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamp('reversed_at')->nullable();
            $t->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reversal_reason', 500)->nullable();

            $t->timestamps();

            $t->unique(['tenant_id', 'payment_number']);
            $t->index('invoice_id');
        });

        // secure_links.purpose — add 'client_invoice' (MySQL only; adjust if using a different DB)
        if (Schema::hasTable('secure_links') && Schema::hasColumn('secure_links', 'purpose')) {
            try {
                DB::statement("ALTER TABLE secure_links MODIFY COLUMN purpose ENUM(
                    'technician_job','client_quote','client_invoice','client_feedback','client_tracking',
                    'user_invite','password_reset','client_sign_in','email_change'
                ) NOT NULL");
            } catch (\Throwable $e) {
                // Non-MySQL or already correct — skip silently.
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_tokens');
        Schema::dropIfExists('invoice_events');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};