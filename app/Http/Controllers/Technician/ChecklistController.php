<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\FormTemplateVersion;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAsset;
use App\Services\Forms\BatchService;
use App\Services\Forms\Checklists;
use App\Services\Forms\FormException;
use App\Services\Forms\FormSchema;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The checklist and the batch screens of the Field View (FRM-05, BAT-03). Same rule as the jobs screens: the assigned
 * technician works the job, the Team Lead of that job may look.
 */
class ChecklistController extends Controller
{
    private function authorizeTech(ServiceRequest $sr): void
    {
        abort_unless((int) $sr->assigned_tech_user_id === (int) auth()->id(), 403);
    }

    private function authorizeView(ServiceRequest $sr): void
    {
        $uid = auth()->id();
        $ok  = (int) $sr->assigned_tech_user_id === (int) $uid
            || ($sr->team_lead_user_id && (int) $sr->team_lead_user_id === (int) $uid);
        abort_unless($ok, 403);
    }

    private function rowOf(ServiceRequest $sr, ServiceRequestAsset $sra): ServiceRequestAsset
    {
        abort_unless((int) $sra->service_request_id === (int) $sr->id, 404);

        return $sra;
    }

    private function editable(ServiceRequest $sr): bool
    {
        return $sr->status === 'in_progress' && (int) $sr->assigned_tech_user_id === (int) auth()->id();
    }

    private function mustBeInProgress(ServiceRequest $sr): void
    {
        abort_unless($sr->status === 'in_progress', 422, 'Start the job before you fill in the checklist.');
    }

    // ───────────── the request's own checklist ─────────────

    public function show(ServiceRequest $serviceRequest)
    {
        $this->authorizeView($serviceRequest);
        $version = $serviceRequest->form_template_version_id ? FormTemplateVersion::withoutGlobalScopes()->find($serviceRequest->form_template_version_id) : null;
        abort_unless($version, 404);

        return view('technician.jobs.checklist', [
            'serviceRequest' => $serviceRequest, 'version' => $version, 'row' => null,
            'answers'        => (array) $serviceRequest->sr_checklist_payload,
            'result'         => $serviceRequest->checklist_result,
            'editable'       => $this->editable($serviceRequest),
            'action'         => route('technician.jobs.checklist.save', $serviceRequest),
            'back'           => route('technician.jobs.show', $serviceRequest),
            'title'          => 'Checklist',
        ]);
    }

    public function save(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $this->mustBeInProgress($serviceRequest);

        return $this->store($request, $serviceRequest, null);
    }

    // ───────────── batch ─────────────

    public function assets(ServiceRequest $serviceRequest)
    {
        $this->authorizeView($serviceRequest);
        $rows = ServiceRequestAsset::where('service_request_id', $serviceRequest->id)->with('asset:id,name,asset_code')->get();
        abort_if($rows->isEmpty(), 404);
        $batch = app(BatchService::class);

        return view('technician.jobs.batch', [
            'serviceRequest' => $serviceRequest, 'rows' => $rows, 'line' => $batch->summaryLine($serviceRequest),
            'done' => $rows->where('inspection_status', '!=', 'pending')->count(),
        ]);
    }

    public function asset(ServiceRequest $serviceRequest, ServiceRequestAsset $sra)
    {
        $this->authorizeView($serviceRequest);
        $sra = $this->rowOf($serviceRequest, $sra)->load('asset');

        return view('technician.jobs.batch_asset', [
            'serviceRequest' => $serviceRequest, 'row' => $sra, 'version' => $sra->version,
            'answers'        => (array) $sra->asset_checklist_payload, 'result' => $sra->checklist_result,
            'editable'       => $this->editable($serviceRequest),
            'statuses'       => ServiceRequestAsset::STATUSES,
        ]);
    }

    /** Status, remarks, meter reading and (when the asset has a checklist) its answers, in one save. */
    public function saveAsset(Request $request, ServiceRequest $serviceRequest, ServiceRequestAsset $sra)
    {
        $this->authorizeTech($serviceRequest);
        $this->mustBeInProgress($serviceRequest);
        $sra = $this->rowOf($serviceRequest, $sra);

        $data = $request->validate([
            'inspection_status'      => ['required', 'in:' . implode(',', ServiceRequestAsset::STATUSES)],
            'technician_remarks'     => ['nullable', 'string', 'max:2000'],
            'recorded_meter_reading' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($request->filled('client_event_uuid') && $sra->version) {
            $this->store($request, $serviceRequest, $sra);
        }
        app(BatchService::class)->setStatus($sra, $data['inspection_status'], $data['technician_remarks'] ?? null);
        if (isset($data['recorded_meter_reading'])) {
            $sra->forceFill(['recorded_meter_reading' => $data['recorded_meter_reading']])->save();
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'summary' => app(BatchService::class)->summary($serviceRequest)])
            : redirect()->route('technician.jobs.batch', $serviceRequest)->with('success', 'Saved.');
    }

    // ───────────── shared ─────────────

    private function store(Request $request, ServiceRequest $sr, ?ServiceRequestAsset $row)
    {
        $request->validate([
            'client_event_uuid' => ['required', 'uuid'],
            'recorded_at'       => ['nullable', 'date'],
            'version_id'        => ['nullable', 'integer'],
            'answers'           => ['nullable', 'array'],
            'files'             => ['nullable', 'array'],
            'files.*'           => ['nullable', 'array'],
            'files.*.*'         => ['file', 'max:10240', 'mimes:jpg,jpeg,png'],
        ]);

        $checklists = app(Checklists::class);
        $version    = ($row ? $row->version : null) ?? ($sr->form_template_version_id ? FormTemplateVersion::withoutGlobalScopes()->find($sr->form_template_version_id) : null);
        if (! $version) {
            throw new FormException('CHECKLIST_NOT_BOUND', 'This job has no checklist.');
        }

        $input = $checklists->prepareInput($sr, $row, $version, (array) $request->input('answers', []), (array) $request->file('files', []));
        $state = $checklists->save(
            $sr, $row, $request->user(), $input, $request->input('client_event_uuid'),
            $request->filled('recorded_at') ? Carbon::parse($request->input('recorded_at')) : null,
            $request->filled('version_id') ? (int) $request->input('version_id') : null,
        );

        if ($request->expectsJson()) {
            return response()->json($state);
        }
        if ($row) {
            return null;   // the asset page redirects itself after saving the status
        }

        return back()->with('success', $state['complete'] ? 'Checklist saved.' : "Saved. {$state['missing']} required answers are still missing.");
    }
}
