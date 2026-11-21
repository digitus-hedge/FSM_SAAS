<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blueprint 7.1 / section 10 (service_requests.billing_status): after QC pass on an out-of-coverage job in an
 * invoice-mandatory category the billing status is 'pending_invoice' (RequestsReviewController writes it).
 * The live enum did not allow it, so that QC pass failed. This adds the value and keeps every existing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE service_requests
            MODIFY COLUMN billing_status
            ENUM('not_applicable','pending_quote','quoted','awaiting_invoice','pending_invoice','invoiced','paid')
            NOT NULL
            DEFAULT 'not_applicable'
        ");
    }

    public function down(): void
    {
        // Rows written with the new value go back to the old equivalent before the value is removed.
        DB::statement("UPDATE service_requests SET billing_status = 'awaiting_invoice' WHERE billing_status = 'pending_invoice'");
        DB::statement("
            ALTER TABLE service_requests
            MODIFY COLUMN billing_status
            ENUM('not_applicable','pending_quote','quoted','awaiting_invoice','invoiced','paid')
            NOT NULL
            DEFAULT 'not_applicable'
        ");
    }
};
