<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * The payments ledger (LED-01, spec 5.5 and 6.8, D11). Every payment of the company, newest first.
 * Totals use base_amount and leave out reversed payments. The query always carries the tenant id.
 */
class PaymentLedgerQuery
{
    public const EXPORT_LIMIT = 5000;

    /** Normalise the request filters. Dates are in the company's time zone and default to this month (5.5). */
    public function filters(Tenant $tenant, array $input): array
    {
        $tz  = $tenant->timezone ?: config('app.timezone');
        $now = Carbon::now($tz);

        return [
            'from'             => $this->date($input['from'] ?? null, $tz) ?? $now->copy()->startOfMonth()->toDateString(),
            'to'               => $this->date($input['to'] ?? null, $tz) ?? $now->copy()->endOfMonth()->toDateString(),
            'client_id'        => ($input['client_id'] ?? null) ? (int) $input['client_id'] : null,
            'method'           => in_array($input['method'] ?? null, ['cash', 'bank_transfer', 'cheque', 'card', 'other'], true) ? $input['method'] : null,
            'currency'         => preg_match('/^[A-Za-z]{3}$/', (string) ($input['currency'] ?? '')) ? strtoupper($input['currency']) : null,
            'include_reversed' => filter_var($input['include_reversed'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public function query(Tenant $tenant, array $f): Builder
    {
        $tz    = $tenant->timezone ?: config('app.timezone');
        $start = Carbon::parse($f['from'], $tz)->startOfDay()->setTimezone(config('app.timezone'));
        $end   = Carbon::parse($f['to'], $tz)->endOfDay()->setTimezone(config('app.timezone'));

        return Payment::query()
            ->where('payments.tenant_id', $tenant->id)
            ->whereBetween('payments.received_at', [$start, $end])
            ->when($f['client_id'], fn ($q, $id) => $q->whereHas('invoice', fn ($i) => $i->where('client_id', $id)))
            ->when($f['method'], fn ($q, $m) => $q->where('payments.method', $m))
            ->when($f['currency'], fn ($q, $c) => $q->where('payments.currency_code', $c))
            ->when(! $f['include_reversed'], fn ($q) => $q->whereNull('payments.reversed_at'))
            ->with(['invoice.client', 'receivedBy', 'reversedBy'])
            ->orderByDesc('payments.received_at')->orderByDesc('payments.id');
    }

    /** Total in the base currency, without reversed payments (CP-053). */
    public function totalBase(Tenant $tenant, array $f): string
    {
        return (string) ($this->query($tenant, $f)->whereNull('payments.reversed_at')->toBase()->reorder()->sum('payments.base_amount') ?: 0);
    }

    /** Companies' currencies seen on payments, for the filter list. */
    public function currencies(Tenant $tenant): array
    {
        return Payment::query()->where('tenant_id', $tenant->id)->whereNotNull('currency_code')
            ->distinct()->orderBy('currency_code')->pluck('currency_code')->all();
    }

    private function date(?string $v, string $tz): ?string
    {
        if (! $v) {
            return null;
        }
        try {
            return Carbon::parse($v, $tz)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
