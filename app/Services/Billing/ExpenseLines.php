<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ServiceRequestExpense;
use App\Models\Tenant;
use App\Services\Currency\DocumentCurrency;
use App\Services\Currency\Money;
use Illuminate\Support\Collection;

/**
 * Approved expenses on an invoice (blueprint 7.7a, spec D8 and 6.6). Expenses stay in the base currency. When one becomes an
 * invoice line on an invoice in another currency, its rate is the expense rate divided by the invoice's rate, rounded half up to
 * the invoice currency's decimals. The rate used is kept on the line (converted_at_rate): a later change of the invoice's rate
 * does not change the line, and the builder says so.
 */
class ExpenseLines
{
    /** @return int[] the invoice's request, and its trades when it is a multi-trade master */
    private function requestIds(Invoice $invoice): array
    {
        $sr = \App\Models\ServiceRequest::withoutGlobalScopes()->find($invoice->service_request_id);

        return $sr ? app(\App\Services\Trades\MasterRequests::class)->ids($sr) : [$invoice->service_request_id];
    }

    /** Approved, billable expenses of the invoice's request that are not already on an invoice that is not void. */
    public function eligible(Invoice $invoice): Collection
    {
        $taken = InvoiceItem::query()
            ->whereNotNull('source_expense_id')
            ->whereHas('invoice', fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $invoice->tenant_id)->where('status', '!=', 'void'))
            ->pluck('source_expense_id');

        return ServiceRequestExpense::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->whereIn('service_request_id', $this->requestIds($invoice))   // a master's invoice takes its trades' expenses (D7)
            ->where('approval_status', 'approved')
            ->where('is_billable_to_client', true)
            ->whereNotIn('id', $taken)
            ->orderBy('id')
            ->get();
    }

    /** The line an expense becomes on this invoice: [description, quantity, unit_rate, line_total, converted_at_rate]. */
    public function convert(ServiceRequestExpense $expense, Invoice $invoice, Tenant $tenant): array
    {
        $base = DocumentCurrency::base($tenant);
        $cur  = strtoupper($invoice->currency_code ?: $base);
        $rate = (float) $invoice->exchange_rate;

        if ($cur === $base || $rate <= 0) {
            $unit = Money::round($expense->unit_rate, $cur);
            $used = null;
        } else {
            $unit = Money::round((float) $expense->unit_rate / $rate, $cur);
            $used = Money::rate($rate);
        }

        return [
            'description'       => $expense->description,
            'quantity'          => $expense->quantity,
            'unit_rate'         => $unit,
            'line_total'        => Money::round((float) $expense->quantity * (float) $unit, $cur),
            'converted_at_rate' => $used,
        ];
    }

    /** The rate the expense lines of this invoice were converted at, when it differs from the invoice's rate now (D8 note). */
    public function staleRate(Invoice $invoice): ?string
    {
        $item = $invoice->items->first(fn ($i) => $i->source_expense_id && $i->converted_at_rate !== null
            && Money::rate($i->converted_at_rate) !== Money::rate($invoice->exchange_rate));

        return $item ? Money::rate($item->converted_at_rate) : null;
    }
}
