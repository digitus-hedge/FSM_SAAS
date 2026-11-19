<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Billing\AgingReport;
use App\Services\Export\XlsxExporter;
use Illuminate\Http\Request;

/** Finance, Aging (LED-02, spec 5.6). Plan: billing_full. Seen with payment.record, invoice.manage or invoice.approve. */
class AgingReportController extends Controller
{
    private function tenant()
    {
        $user = auth()->user();
        abort_unless($user && $user->tenant_id, 404);
        abort_unless(\App\Services\Permissions\PermissionLevel::canView($user, 'payment.record', 'invoice.manage', 'invoice.approve'), 404);

        return $user->tenant;
    }

    private function clientId(Request $request, $tenant): ?int
    {
        $id = (int) $request->query('client_id');

        return $id && Client::query()->where('tenant_id', $tenant->id)->whereKey($id)->exists() ? $id : null;
    }

    public function index(Request $request, AgingReport $aging)
    {
        $tenant   = $this->tenant();
        $clientId = $this->clientId($request, $tenant);

        return view('admin.aging.index', [
            'tenant'    => $tenant,
            'report'    => $aging->report($tenant, $clientId),
            'bands'     => AgingReport::BANDS,
            'clients'   => Client::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
            'clientId'  => $clientId,
            'canExport' => \App\Services\Permissions\PermissionLevel::canView(auth()->user(), 'report.export'),
        ]);
    }

    /** A cell of the report: the invoices behind it, with both amounts (CP-067). */
    public function invoices(Request $request, AgingReport $aging)
    {
        $tenant = $this->tenant();
        $band   = $request->query('band');
        abort_unless($band === null || $band === 'all' || isset(AgingReport::BANDS[$band]), 404);

        return view('admin.aging.invoices', [
            'tenant'   => $tenant,
            'invoices' => $aging->invoices($tenant, $this->clientId($request, $tenant), in_array($band, [null, 'all'], true) ? null : $band),
            'bandLabel' => AgingReport::BANDS[$band] ?? 'All',
            'client'   => $this->clientId($request, $tenant) ? Client::query()->where('tenant_id', $tenant->id)->find($request->query('client_id')) : null,
        ]);
    }

    public function export(Request $request, AgingReport $aging, XlsxExporter $xlsx)
    {
        $tenant = $this->tenant();
        abort_unless(\App\Services\Permissions\PermissionLevel::canView(auth()->user(), 'report.export'), 403);

        $clientId = $this->clientId($request, $tenant);
        $report   = $aging->report($tenant, $clientId);

        $rows = array_map(fn ($r) => array_merge([$r['client']], array_map('floatval', array_values($r['bands'])), [(float) $r['total']]), $report['rows']);
        $rows[] = array_merge(['Total'], array_map('floatval', array_values(array_diff_key($report['totals'], ['total' => 1]))), [(float) $report['totals']['total']]);

        return $xlsx->download(
            'aging-' . $report['today'] . '.xlsx',
            'Aging',
            array_merge(['Client'], array_map(fn ($l) => $l . ' (' . $tenant->base_currency . ')', array_values(AgingReport::BANDS)), ['Total (' . $tenant->base_currency . ')']),
            $rows,
            ['action' => 'aging.exported', 'tenant_id' => $tenant->id, 'new' => ['as_of' => $report['today'], 'client_id' => $clientId, 'rows' => count($report['rows'])]],
        );
    }
}
