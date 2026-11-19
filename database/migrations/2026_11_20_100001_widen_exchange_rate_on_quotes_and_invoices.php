<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D2: a document's rate is stored with 8 decimals, like exchange_rates.rate. The columns were DECIMAL(14,6),
 * which would round a carried rate and make a document's base total differ from its payments' base amounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotes', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('exchange_rate', 18, 8)->default(1)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['quotes', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('exchange_rate', 14, 6)->default(1)->change();
            });
        }
    }
};
