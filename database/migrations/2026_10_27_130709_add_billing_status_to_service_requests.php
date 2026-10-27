<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $t) {
            if (! Schema::hasColumn('service_requests', 'billing_status')) {
                $t->enum('billing_status', [
                    'not_applicable',
                    'pending_quote',
                    'quote_draft',
                    'quoted',
                    'awaiting_invoice',
                    'invoiced',
                ])->default('not_applicable')->after('status');
            }
        });
    }
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $t) {
            if (Schema::hasColumn('service_requests', 'billing_status')) {
                $t->dropColumn('billing_status');
            }
        });
    }
};