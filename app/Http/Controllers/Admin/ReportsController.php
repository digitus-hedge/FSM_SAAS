<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use App\Services\Export\XlsxExporter;
use App\Services\Forms\FormException;
use App\Services\Reports\Figures;
use App\Services\Reports\ReportAccess;
use App\Services\Reports\ReportData;
use App\Services\Reports\ReportPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The Reports page: four reports, each on screen, as PDF and as Excel (Reports-Summaries-Preferences spec RPT-01, RPT-02, D2, D5, D18).
 * Plan gate `reports_full`, then report.view for the page and report.export for the files.
 */
class ReportsController extends Controller
{
    private function tenant(): Tenant
    {
        return Tenant::findOrFail(auth()->user()->tenant_id);
    }

    public function index()
    {
        abort_unless(ReportAccess::mayView(auth()->user()), 403);
        $user = auth()->user();

        return view('admin.reports.index', [
            'reports' => collect(['sla' => 'Monthly SLA', 'scorecard' => 'Technician scorecard', 'contracts' => 'Contract performance'])
                ->filter(fn ($l, $k) => ReportAccess::mayOpen($user, $k)),
            'mayExport' => ReportAccess::mayExport($user),
            'month' => Carbon::now($this->tenant()->timezone ?: config('app.timezone'))->format('Y-m'),
            'recent' => \App\Models\ActivityLog::where('tenant_id', $user->tenant_id)->where('action', 'report.exported')->latest('id')->limit(10)->get(),
        ]);
    }

    /** One report on screen. */
    public function show(Request $request, string $report)
    {
        [$tenant, $title, $headings, $rows, $month] = $this->load($request, $report);

        return view('admin.reports.show', ['report' => $report, 'title' => $title, 'headings' => $headings, 'rows' => $rows, 'month' => $month,
            'mayExport' => ReportAccess::mayExport(auth()->user()), 'monthToDate' => $this->monthToDate($tenant, $month)]);
    }

    public function pdf(Request $request, string $report)
    {
        abort_unless(ReportAccess::mayExport(auth()->user()), 403);
        [$tenant, $title, $headings, $rows, $month] = $this->load($request, $report);
        $this->audit($tenant, $report, 'pdf', $month);

        $pdf = app(ReportPdf::class)->render('reports.pdf.table', ['headings' => $headings, 'rows' => $rows, 'month' => $month, 'monthToDate' => $this->monthToDate($tenant, $month)], $tenant, $title);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $report . '-' . $month . '.pdf"']);
    }

    public function excel(Request $request, string $report, XlsxExporter $xlsx)
    {
        abort_unless(ReportAccess::mayExport(auth()->user()), 403);
        [$tenant, $title, $headings, $rows, $month] = $this->load($request, $report);

        return $xlsx->download($report . '-' . $month . '.xlsx', $title, $headings, $rows,
            ['action' => 'report.exported', 'tenant_id' => $tenant->id, 'new' => ['report' => $report, 'format' => 'xlsx', 'month' => $month]]);
    }

    /** The completion certificate of one completed request (D21): PDF, no price. */
    public function certificate(ServiceRequest $service_request)
    {
        $user = auth()->user();
        abort_unless($this->tenant()->hasFeature('reports_full'), 403);   // blueprint 6: PDF reports are Professional and above
        abort_unless(ReportAccess::mayCertificate($user, $service_request), 404);
        abort_unless(ReportAccess::mayExport($user) || in_array($user->role?->code, ['client_user', 'client_admin', 'client_viewer'], true), 403);
        if ($service_request->status !== 'completed') {
            throw new FormException('REPORT_NOT_COMPLETED', 'A completion certificate is only for a completed request.');
        }

        $tenant = $this->tenant();
        $this->audit($tenant, 'certificate', 'pdf', $service_request->sr_number);
        $pdf = app(ReportPdf::class)->render('reports.pdf.certificate', app(ReportData::class)->certificate($service_request), $tenant, 'Completion certificate');

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="certificate-' . $service_request->sr_number . '.pdf"']);
    }

    // ───────────── helpers ─────────────

    private function monthToDate(Tenant $tenant, string $month): bool
    {
        return $month === Carbon::now($tenant->timezone ?: config('app.timezone'))->format('Y-m');
    }

    /** @return array{0:Tenant,1:string,2:string[],3:array,4:string} */
    private function load(Request $request, string $report): array
    {
        $user = auth()->user();
        abort_unless(in_array($report, ReportAccess::REPORTS, true) && $report !== 'certificate' && ReportAccess::mayOpen($user, $report), 403);

        $tenant = $this->tenant();
        $month  = (string) $request->input('month', Carbon::now($tenant->timezone ?: config('app.timezone'))->format('Y-m'));
        // D18: a month that has ended or the current one
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $month > Carbon::now($tenant->timezone ?: config('app.timezone'))->format('Y-m')) {
            throw new FormException('REPORT_PERIOD_INVALID', 'Choose a month that has ended or the current month.');
        }

        $data = app(ReportData::class);
        switch ($report) {
            case 'sla':
                $d = $data->sla($tenant, $month);
                $headings = ['Priority', 'Requests', 'Completed', 'Response met', 'Response total', 'Resolution met', 'Resolution total', 'Breaches'];
                $rows = array_map(fn ($r) => [ucfirst($r['priority']), $r['requests'], $r['completed'], $r['response_met'], $r['response_total'], $r['resolution_met'], $r['resolution_total'], $r['breaches']], $d['rows']);
                return [$tenant, 'Monthly SLA', $headings, $rows, $month];
            case 'scorecard':
                $d = $data->scorecard($tenant, $month, ReportAccess::technicianIds($user));
                $headings = ['Technician', 'Jobs', 'On-time %', 'First-time fix %', 'Average minutes', 'Callbacks'];
                $rows = array_map(fn ($r) => [$r['technician'], $r['jobs'], $r['on_time_percent'], $r['first_time_fix_percent'], $r['avg_minutes'], $r['callbacks']], $d['rows']);
                return [$tenant, 'Technician scorecard', $headings, $rows, $month];
            default:
                $clientId = in_array($user->role?->code, ['client_user', 'client_admin', 'client_viewer'], true) ? (int) $user->client_id : null;
                $d = $data->contractPerformance($tenant, $month, $clientId);
                $headings = ['Contract', 'Client', 'Status', 'Calls used', 'Max calls', 'Requests', 'Billed (' . $d['currency'] . ')', 'Ends'];
                $rows = array_map(fn ($r) => [$r['contract'], $r['client'] ?? '', $r['status'], $r['calls_used'], $r['max_calls'] ?? '', $r['requests'], $r['billed'], $r['ends'] ?? ''], $d['rows']);
                return [$tenant, 'Contract performance', $headings, $rows, $month];
        }
    }

    private function audit(Tenant $tenant, string $report, string $format, string $what): void
    {
        app(AuditLogger::class)->log('report.exported', ['tenant_id' => $tenant->id, 'new' => ['report' => $report, 'format' => $format, 'for' => $what]]);
    }
}
