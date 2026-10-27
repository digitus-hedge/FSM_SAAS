<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("
            ALTER TABLE service_requests 
            MODIFY COLUMN billing_status 
            ENUM('not_applicable','pending_quote','quoted','awaiting_invoice','invoiced') 
            NOT NULL DEFAULT 'not_applicable'
        ");
    }
    public function down(): void {}
};