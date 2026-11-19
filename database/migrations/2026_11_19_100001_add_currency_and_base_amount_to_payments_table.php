<?php

use App\Services\Currency\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** SCH-B7-01. Adds currency_code and base_amount to payments and fills existing rows from their invoice (CP-043). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->char('currency_code', 3)->nullable()->after('amount');
            $t->decimal('base_amount', 14, 3)->nullable()->after('currency_code');
            $t->index(['tenant_id', 'received_at'], 'idx_payments_tenant_received');
        });

        // Backfill: the invoice's currency, and amount x the invoice's fixed rate rounded to the base currency's decimals.
        DB::table('payments')->orderBy('id')->chunkById(500, function ($rows) {
            $invoices = DB::table('invoices')->whereIn('id', $rows->pluck('invoice_id'))->get()->keyBy('id');
            $bases    = DB::table('tenants')->whereIn('id', $rows->pluck('tenant_id')->unique())->pluck('base_currency', 'id');

            foreach ($rows as $p) {
                $inv = $invoices[$p->invoice_id] ?? null;
                if (! $inv) {
                    continue;
                }
                $base = $bases[$p->tenant_id] ?? $inv->currency_code;
                DB::table('payments')->where('id', $p->id)->update([
                    'currency_code' => $inv->currency_code,
                    'base_amount'   => Money::toBase((string) $p->amount, (string) $inv->exchange_rate, (string) $base),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->dropIndex('idx_payments_tenant_received');
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->dropColumn(['currency_code', 'base_amount']);
        });
    }
};
