<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequestExpense;
use App\Models\Tenant;
use App\Services\Export\XlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vendor spend (Multi-Trade-Vendors-Depot spec VND-04, D11): approved expenses of vendor technicians, by vendor, month and client,
 * in the company's base currency. The expenses of the company's own technicians are never in it.
 * Expenses are stored in the base currency (they are converted only when they go on an invoice), so the sum needs no conversion.
 */
class VendorSpendController extends Controller
{
    private function rows(Request $request)
    {
        $tid = auth()->user()->tenant_id;

        return ServiceRequestExpense::withoutGlobalScopes()->from('service_request_expenses as e')
            ->join('vendors as v', 'v.id', '=', 'e.vendor_id')
            ->join('service_requests as sr', 'sr.id', '=', 'e.service_request_id')
            ->leftJoin('clients as c', 'c.id', '=', 'sr.client_id')
            ->where('e.tenant_id', $tid)->whereNotNull('e.vendor_id')->where('e.approval_status', 'approved')
            ->when($request->filled('vendor'), fn ($q) => $q->where('e.vendor_id', $request->input('vendor')))
            ->when($request->filled('month'), fn ($q) => $q->whereRaw("DATE_FORMAT(e.created_at, '%Y-%m') = ?", [$request->input('month')]))
            ->groupBy('v.id', 'v.name', DB::raw("DATE_FORMAT(e.created_at, '%Y-%m')"), 'c.id', 'c.name')
            ->orderBy('v.name')->orderByRaw("DATE_FORMAT(e.created_at, '%Y-%m') desc")
            ->get([
                'v.name as vendor', DB::raw("DATE_FORMAT(e.created_at, '%Y-%m') as month"), 'c.name as client',
                DB::raw('SUM(e.total_amount) as total'), DB::raw('COUNT(*) as line_count'),
            ]);
    }

    public function index(Request $request)
    {
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);
        $rows   = $this->rows($request);

        return view('admin.vendor-spend.index', [
            'rows' => $rows, 'currency' => $tenant->base_currency, 'total' => $rows->sum('total'),
            'vendors' => \App\Models\Vendor::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, XlsxExporter $xlsx)
    {
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);
        $rows   = $this->rows($request);

        return $xlsx->download('vendor-spend.xlsx', 'Vendor spend', ['Vendor', 'Month', 'Client', "Spend ({$tenant->base_currency})", 'Expense lines'],
            $rows->map(fn ($r) => [$r->vendor, $r->month, $r->client ?? '', (float) $r->total, (int) $r->line_count])->all(),
            ['action' => 'vendor_spend.exported', 'tenant_id' => $tenant->id, 'new' => ['rows' => $rows->count(), 'filters' => $request->only(['vendor', 'month'])]]);
    }
}
