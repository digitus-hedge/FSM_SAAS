<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D8: a line made from an approved expense (base currency) is converted at the invoice's rate when it is added.
 * The rate used is kept on the line, so the builder can say "Lines from expenses were converted at {rate}."
 * when Finance changes the invoice's rate afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->decimal('converted_at_rate', 18, 8)->nullable()->after('source_expense_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $t) {
            $t->dropColumn('converted_at_rate');
        });
    }
};
