<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE service_requests
            MODIFY COLUMN billing_status
            ENUM('not_applicable','pending_quote','quoted','awaiting_invoice','invoiced','paid')
            NOT NULL
            DEFAULT 'not_applicable'
        ");
    }

    public function down(): void
    {
        // Fail safely if any row is already 'paid' — convert back to 'invoiced' first
        DB::statement("UPDATE service_requests SET billing_status = 'invoiced' WHERE billing_status = 'paid'");

        DB::statement("
            ALTER TABLE service_requests
            MODIFY COLUMN billing_status
            ENUM('not_applicable','pending_quote','quoted','awaiting_invoice','invoiced')
            NOT NULL
            DEFAULT 'not_applicable'
        ");
    }
};