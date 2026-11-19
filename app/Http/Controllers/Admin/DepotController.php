<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormTemplateVersion;
use App\Models\ServiceRequest;
use App\Services\Forms\Checklists;
use App\Services\Forms\FormException;
use App\Services\Permissions\PermissionLevel;
use App\Services\Permissions\ServiceRequestAccess;
use App\Services\Trades\Depot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The workshop stage bar and the intake and return checklists (Multi-Trade-Vendors-Depot spec DEP-03, DEP-04). */
class DepotController extends Controller
{
    private function authorizeDepot(ServiceRequest $sr, ?string $slot = null): void
    {
        abort_if($slot !== null && ! isset(Checklists::SLOTS[$slot]), 404);
        abort_unless($sr->service_mode === 'depot', 404);
        abort_unless(ServiceRequestAccess::mayAssign(auth()->user(), $sr), 403);
    }

    public function stage(Request $request, ServiceRequest $service_request)
    {
        $this->authorizeDepot($service_request);
        $data = $request->validate([
            'stage'  => ['required', 'in:' . implode(',', Depot::STAGES)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // moving a stage back is a manager's call (sr.assign full)
        $order = array_flip(Depot::STAGES);
        if ($service_request->depot_stage !== null && $order[$data['stage']] <= $order[$service_request->depot_stage]) {
            abort_unless(PermissionLevel::isFull(auth()->user(), 'sr.assign'), 403);
        }

        app(Depot::class)->changeStage($service_request, $data['stage'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Stage: ' . Depot::LABELS[$data['stage']] . '.');
    }

    public function checklist(ServiceRequest $service_request, string $slot)
    {
        $this->authorizeDepot($service_request, $slot);
        $cols = Checklists::SLOTS[$slot];
        $version = $service_request->{$cols[0]} ? FormTemplateVersion::withoutGlobalScopes()->find($service_request->{$cols[0]}) : null;
        abort_unless($version, 404);

        return view('admin.depot.checklist', [
            'sr' => $service_request, 'slot' => $slot, 'version' => $version,
            'answers' => (array) $service_request->{$cols[1]}, 'result' => $service_request->{$cols[2]},
        ]);
    }

    public function saveChecklist(Request $request, ServiceRequest $service_request, string $slot)
    {
        $this->authorizeDepot($service_request, $slot);
        $request->validate([
            'client_event_uuid' => ['required', 'uuid'],
            'answers'           => ['nullable', 'array'],
            'files'             => ['nullable', 'array'],
            'files.*.*'         => ['file', 'max:10240', 'mimes:jpg,jpeg,png'],
        ]);

        $cols = Checklists::SLOTS[$slot];
        $version = $service_request->{$cols[0]} ? FormTemplateVersion::withoutGlobalScopes()->find($service_request->{$cols[0]}) : null;
        if (! $version) {
            throw new FormException('CHECKLIST_NOT_BOUND', 'This request has no ' . $slot . ' checklist.');
        }

        $checklists = app(Checklists::class);
        $input = $checklists->prepareInput($service_request, null, $version, (array) $request->input('answers', []), (array) $request->file('files', []), $slot);
        $state = $checklists->save($service_request, null, $request->user(), $input, $request->input('client_event_uuid'), Carbon::now(), null, $slot);

        return redirect()->route('service-requests.show', $service_request)
            ->with('success', $state['complete'] ? ucfirst($slot) . ' checklist saved.' : "Saved. {$state['missing']} required answers are still missing.");
    }
}
