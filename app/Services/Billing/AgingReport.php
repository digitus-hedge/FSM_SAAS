<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Currency\Money;
use Carbon\Carbon;

/**
 * The aging report (LED-02, spec 5.6 and 6.9, D12). Unpaid balances of sent, part-paid and overdue invoices
 * by client, in bands of days past the due date, in the base currency. Each balance is converted at the invoice's
 * own fixed rate. Void, written-off and paid invoices never count. An invoice with no due date counts as Current.
 */
class AgingReport
{
    public const BANDS = [
        'current' => 'Current',
        'd1_30'   => '1 to 30',
        'd31_60'  => '31 to 60',
        'd61_90'  => '61 to 90',
        'over90'  => 'Over 90',
    ];

    public static function bandFor(int $daysPastDue): string
    {
        return match (true) {
            $daysPastDue <= 0  => 'current',
            $daysPastDue <= 30 => 'd1_30',
            $daysPastDue <= 60 => 'd31_60',
            $daysPastDue <= 90 => 'd61_90',
            default            => 'over90',
        };
    }

    /** Today in the company's time zone. */
    public function today(Tenant $tenant): Carbon
    {
        return Carbon::now($tenant->timezone ?: config('app.timezone'))->startOfDay();
    }

    /** The open invoices, each with its days past due, band and base balance. */
    public function invoices(Tenant $tenant, ?int $clientId = null, ?string $band = null)
    {
        $today = Carbon::parse($this->today($tenant)->toDateString(), 'UTC');
        $base  = $tenant->base_currency;

        return Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->where('balance', '>', 0)
            ->when($clientId, fn ($q, $id) => $q->where('client_id', $id))
            ->with('client:id,name')
            ->orderBy('due_date')->get()
            ->map(function (Invoice $inv) use ($today, $base) {
                $days = $inv->due_date ? (int) Carbon::parse($inv->due_date->toDateString(), 'UTC')->diffInDays($today, false) : 0;
                $inv->setAttribute('days_past_due', $days);
                $inv->setAttribute('band', self::bandFor($days));
                $inv->setAttribute('base_balance', Money::toBase((string) $inv->balance, (string) $inv->exchange_rate, $base));

                return $inv;
            })
            ->when($band, fn ($c, $b) => $c->where('band', $b))
            ->values();
    }

    /**
     * @return array{rows: array<int, array>, totals: array<string, string>, today: string}
     * rows: client_id, client, bands (base amounts as strings), total
     */
    public function report(Tenant $tenant, ?int $clientId = null): array
    {
        $base   = $tenant->base_currency;
        $rows   = [];
        $totals = array_fill_keys(array_keys(self::BANDS), '0') + ['total' => '0'];

        foreach ($this->invoices($tenant, $clientId) as $inv) {
            $rows[$inv->client_id] ??= [
                'client_id' => $inv->client_id,
                'client'    => $inv->client?->name ?? '',
                'bands'     => array_fill_keys(array_keys(self::BANDS), '0'),
                'total'     => '0',
            ];
            $rows[$inv->client_id]['bands'][$inv->band] = self::add($rows[$inv->client_id]['bands'][$inv->band], $inv->base_balance);
            $rows[$inv->client_id]['total']             = self::add($rows[$inv->client_id]['total'], $inv->base_balance);
            $totals[$inv->band]                         = self::add($totals[$inv->band], $inv->base_balance);
            $totals['total']                            = self::add($totals['total'], $inv->base_balance);
        }

        usort($rows, fn ($a, $b) => strcasecmp($a['client'], $b['client']));

        // Show every amount with the base currency's decimals.
        $fmt = fn (string $v) => Money::round($v, $base);
        foreach ($rows as &$r) {
            $r['bands'] = array_map($fmt, $r['bands']);
            $r['total'] = $fmt($r['total']);
        }
        unset($r);

        return ['rows' => array_values($rows), 'totals' => array_map($fmt, $totals), 'today' => $this->today($tenant)->toDateString()];
    }

    /** Adds two amounts as decimal strings without float drift (three decimals is the storage precision). */
    private static function add(string $a, string $b): string
    {
        return number_format(round((float) $a * 1000 + (float) $b * 1000) / 1000, 3, '.', '');
    }
}
