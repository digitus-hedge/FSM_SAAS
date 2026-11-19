<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Currency\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Money figures for the dashboard, always in the company's base currency (CUR-06, spec 5.9, 6.7, D9).
 * Amounts in different currencies are never added as they are: each invoice balance is converted at the invoice's own
 * fixed rate (rounded half up to the base currency's decimals), and payments use their stored base_amount.
 * Release A documents (rate 1) count at face value.
 */
class BaseCurrencyFigures
{
    /**
     * @return array{currency: string, unpaid: string, overdue: string, received: string, converted: bool}
     */
    public function forPeriod(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        $base = strtoupper($tenant->base_currency ?: 'AED');
        $dec  = Money::decimals($base);
        $tz   = $tenant->timezone ?: config('app.timezone');
        $today = Carbon::now($tz)->toDateString();

        $open = Invoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])->where('balance', '>', 0);

        $sumBase = fn ($q) => (string) ($q->toBase()->sum(DB::raw("ROUND(balance * exchange_rate, {$dec})")) ?: 0);

        $unpaid  = $sumBase(clone $open);
        // Overdue = still unpaid after the due date, the same rule as the aging report (the daily job may not have run yet).
        $overdue = $sumBase((clone $open)->whereNotNull('due_date')->whereDate('due_date', '<', $today));

        $payments = Payment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('reversed_at')
            ->whereBetween('received_at', [$from, $to]);
        $received = (string) ($payments->clone()->toBase()->sum('base_amount') ?: 0);

        // True when a figure includes a document in another currency, so the page can say it was converted.
        $converted = (clone $open)->where(fn ($q) => $q->where('currency_code', '!=', $base)->orWhere('exchange_rate', '!=', 1))->exists()
            || $payments->clone()->where('currency_code', '!=', $base)->exists();

        $fmt = fn (string $v) => Money::format($v, $base);

        return [
            'currency'  => $base,
            'unpaid'    => $fmt($unpaid),
            'overdue'   => $fmt($overdue),
            'received'  => $fmt($received),
            'converted' => $converted,
            'raw'       => ['unpaid' => $unpaid, 'overdue' => $overdue, 'received' => $received],
        ];
    }
}
