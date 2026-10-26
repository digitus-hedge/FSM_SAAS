<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\SlaPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlaPoliciesController extends Controller
{
   public function index(Request $request)
{
    $scope = $request->input('category_id', 'default');
    $categoryId = $scope === 'default' ? null : (int) $scope;
    $tenantId = auth()->user()->tenant_id;

    // Correct defaults from the mockup
    $defaults = [
        'emergency' => [60,    240],   // 1h / 4h
        'critical'  => [120,   480],   // 2h / 8h
        'high'      => [240,  1440],   // 4h / 1 day
        'medium'    => [1440, 4320],   // 1 day / 3 days
        'low'       => [4320, 10080],  // 3 days / 7 days
    ];

    // Load company-default rows (always needed for the "Default: X / Y" subtitle)
    $companyRows = SlaPolicy::where('tenant_id', $tenantId)
        ->whereNull('service_category_id')
        ->get()->keyBy('priority');

    // Load this scope's rows
    $scopeRows = $categoryId === null
        ? $companyRows
        : SlaPolicy::where('tenant_id', $tenantId)
            ->where('service_category_id', $categoryId)
            ->get()->keyBy('priority');

    $priorities = ['emergency', 'critical', 'high', 'medium', 'low'];
    $editable = [];

    foreach ($priorities as $p) {
        [$dRes, $dResn] = $defaults[$p];

        $companyRow = $companyRows[$p] ?? null;
        $scopeRow   = $scopeRows[$p]   ?? null;

        // Baseline for the "Default: X / Y" label — company row if present, else mockup default
        $baseResp = $companyRow->response_minutes   ?? $dRes;
        $baseResn = $companyRow->resolution_minutes ?? $dResn;

        // Values shown in the inputs: scope row for category pages, baseline for Company default page
        $isCategoryPage = $categoryId !== null;
        $useDefault     = $isCategoryPage ? !$scopeRow : false;

        $editable[$p] = [
            'is_category_page'    => $isCategoryPage,
            'use_default'         => $useDefault,
            'base_response'       => $baseResp,       // for "Default: X / Y" line
            'base_resolution'     => $baseResn,
            'response_minutes'    => $scopeRow->response_minutes    ?? ($isCategoryPage ? 0 : $baseResp),
            'resolution_minutes'  => $scopeRow->resolution_minutes  ?? ($isCategoryPage ? 0 : $baseResn),
            // 'uses_business_hours' => $scopeRow?->uses_business_hours ?? false,  // never defaulted to true

            'uses_business_hours' => $scopeRow?->uses_business_hours ?? ($p === 'high'),  // ← wrong
        ];
    }

    return view('admin.sla-policies.index', [
        'categories'  => ServiceCategory::where('is_active', true)->orderBy('name')->get(['id','name','code']),
        'scope'       => $scope,
        'categoryId'  => $categoryId,
        // 'priorities'  => SlaPolicy::PRIORITY_LABELS,

         'priorities'  => [
        'emergency' => 'Emergency',
        'critical'  => 'Critical',
        'high'      => 'High',
        'medium'    => 'Medium',
        'low'       => 'Low',
    ],
        'editable'    => $editable,
    ]);
}

public function saveAll(Request $request)
{
    $data = $request->validate([
        'category_id'                => 'nullable|integer|exists:service_categories,id',
        'rows'                       => 'required|array',
        'rows.*.use_default'         => 'sometimes|boolean',
        'rows.*.response_d'          => 'nullable|integer|min:0|max:365',
        'rows.*.response_h'          => 'nullable|integer|min:0|max:23',
        'rows.*.response_m'          => 'nullable|integer|min:0|max:59',
        'rows.*.resolution_d'        => 'nullable|integer|min:0|max:365',
        'rows.*.resolution_h'        => 'nullable|integer|min:0|max:23',
        'rows.*.resolution_m'        => 'nullable|integer|min:0|max:59',
        'rows.*.uses_business_hours' => 'sometimes|boolean',
    ]);

    $tenantId   = auth()->user()->tenant_id;
    $categoryId = $request->input('category_id') ?: null;
    $errors = [];

    DB::transaction(function () use ($data, $tenantId, $categoryId, &$errors) {
        foreach ($data['rows'] as $priority => $r) {
            // Category page + Use default = delete any override and skip
            if ($categoryId !== null && !empty($r['use_default'])) {
                SlaPolicy::where('tenant_id', $tenantId)
                    ->where('service_category_id', $categoryId)
                    ->where('priority', $priority)
                    ->delete();
                continue;
            }

            $resp = (int) ($r['response_d'] ?? 0) * 1440
                  + (int) ($r['response_h'] ?? 0) * 60
                  + (int) ($r['response_m'] ?? 0);
            $resn = (int) ($r['resolution_d'] ?? 0) * 1440
                  + (int) ($r['resolution_h'] ?? 0) * 60
                  + (int) ($r['resolution_m'] ?? 0);

            if ($resp < 1 || $resn < 1) continue;

            if ($resp > $resn) {
                $errors["rows.$priority"] = 'Response time must not be longer than resolution time.';
                continue;
            }

            SlaPolicy::updateOrCreate(
                ['tenant_id' => $tenantId, 'service_category_id' => $categoryId, 'priority' => $priority],
                [
                    'response_minutes'    => $resp,
                    'resolution_minutes'  => $resn,
                    'uses_business_hours' => (bool) ($r['uses_business_hours'] ?? false),
                ],
            );
        }
    });

    if ($errors) {
        return back()->withInput()->withErrors($errors);
    }

    return redirect()
        ->route('admin.sla-policies.index', ['category_id' => $categoryId ?: 'default'])
        ->with('success', 'SLA policies saved.');
}
}