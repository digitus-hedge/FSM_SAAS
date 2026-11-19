<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Services\Billing\PaymentLedgerQuery;
use App\Services\Currency\Money;
use App\Services\Export\XlsxExporter;
use Illuminate\Http\Request;

/** Finance, Payments (LED-01, spec 5.5). Plan: billing_full. Seen with payment.record, invoice.manage or invoice.approve. */
class PaymentsLedgerController extends Controller
{
    private function tenant()
    {
        $user = auth()->user();
        abort_unless($user && $user->tenant_id, 404);

        // Technician, Front Desk and the other roles do not know this page exists (CP-101).
        abort_unless(\App\Services\Permissions\PermissionLevel::canView($user, 'payment.record', 'invoice.manage', 'invoice.approve'), 404);

        return $user->tenant;
    }

    public function index(Request $request, PaymentLedgerQuery $ledger)
    {
        $tenant  = $this->tenant();
        $filters = $ledger->filters($tenant, $request->query());

        return view('admin.payments.index', [
            'tenant'     => $tenant,
            'f'          => $filters,
            'payments'   => $ledger->query($tenant, $filters)->paginate(25)->withQueryString(),
            'totalBase'  => $ledger->totalBase($tenant, $filters),
            'hasPayments' => Payment::query()->where('tenant_id', $tenant->id)->exists(),
            'clients'    => Client::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
            'currencies' => $ledger->currencies($tenant),
            'canExport'  => \App\Services\Permissions\PermissionLevel::canView(auth()->user(), 'report.export'),
        ]);
    }

    public function export(Request $request, PaymentLedgerQuery $ledger, XlsxExporter $xlsx)
    {
        $tenant = $this->tenant();
        abort_unless(\App\Services\Permissions\PermissionLevel::canView(auth()->user(), 'report.export'), 403); // CP-057

        $filters = $ledger->filters($tenant, $request->query());
        $query   = $ledger->query($tenant, $filters);

        if ($query->clone()->toBase()->count() > PaymentLedgerQuery::EXPORT_LIMIT) { // CP-060
            return back()->withErrors(['export' => 'This export has more than ' . number_format(PaymentLedgerQuery::EXPORT_LIMIT) . ' rows. Narrow the filters and try again.']);
        }

        $tz   = $tenant->timezone ?: config('app.timezone');
        $rows = $query->get()->map(fn (Payment $p) => [
            $p->payment_number,
            $p->received_at->copy()->setTimezone($tz)->format('Y-m-d'),
            $p->invoice?->client?->name ?? '',
            $p->invoice?->invoice_number ?? '',
            (float) $p->amount,
            $p->currency_code ?? '',
            (float) $p->base_amount,
            $tenant->base_currency,
            str_replace('_', ' ', $p->method),
            $p->reference ?? '',
            $p->receivedBy?->name ?? '',
            $p->reversed_at ? 'Reversed: ' . ($p->reversal_reason ?? '') : '',
        ])->all();

        return $xlsx->download(
            'payments-' . now($tz)->format('Ymd') . '.xlsx',
            'Payments',
            ['Payment', 'Date', 'Client', 'Invoice', 'Amount', 'Currency', 'Base amount', 'Base currency', 'Method', 'Reference', 'Recorded by', 'Reversed'],
            $rows,
            ['action' => 'ledger.exported', 'tenant_id' => $tenant->id, 'new' => ['filters' => $filters, 'rows' => count($rows)]],
        );
    }
}
