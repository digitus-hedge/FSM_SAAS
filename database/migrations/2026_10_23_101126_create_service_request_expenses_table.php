
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_expenses', function (Blueprint $table) {
            $table->id();

            // Tenant and service request
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('asset_id')->nullable();

            // Expense creator and offline sync identifier
            $table->unsignedBigInteger('logged_by_user_id');
            $table->char('client_event_uuid', 36);

            // Vendor and expense category
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('expense_category_id');

            // Expense details
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_rate', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);

            // Billing and receipt
            $table->boolean('is_billable_to_client')->default(true);
            $table->unsignedBigInteger('receipt_attachment_id')->nullable();

            // Approval details
            $table->enum('approval_status', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending');

            $table->unsignedBigInteger('approved_by_user_id')->nullable();

            // Indexes
            $table->unique(
                ['tenant_id', 'client_event_uuid'],
                'uq_tenant_event'
            );

            $table->index(
                ['tenant_id', 'service_request_id', 'approval_status'],
                'idx_tenant_sr_expenses'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_expenses');
    }
};
