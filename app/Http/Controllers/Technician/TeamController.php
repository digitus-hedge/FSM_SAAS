<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\FieldEventLog;
use App\Models\GeofenceOverrideRequest;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    private function teamMemberIds(): array
    {
        return User::where('team_lead_id', auth()->id())->pluck('id')->all();
    }

    private function authorizeTeamSr(ServiceRequest $sr): void
    {
        abort_unless($sr->team_lead_user_id === auth()->id(), 403);
    }

    /**
     * Shared data for every team-lead screen (mobile dashboard and every desktop/web
     * page). Always builds all three queues so the sidebar badge counts on the web
     * layout stay accurate no matter which page is being rendered.
     */
    private function webTeamData(): array
    {
        $teamIds = $this->teamMemberIds();

        $activeJobs = ServiceRequest::with(['client', 'site'])
            ->whereIn('assigned_tech_user_id', $teamIds)
            ->whereIn('status', ['assigned', 'in_progress', 'rework'])
            ->orderBy('scheduled_eta_at')
            ->get();

        // Home KPI tiles — counted from the same active-jobs set, before it gets
        // grouped per technician below.
        $inProgressCount = $activeJobs->where('status', 'in_progress')->count();
        $reworkCount     = $activeJobs->where('status', 'rework')->count();

        $jobsByTechId = $activeJobs->groupBy('assigned_tech_user_id');

        $teamMembers = User::whereIn('id', $teamIds)->get()->map(function ($member) use ($jobsByTechId) {
            $jobs = $jobsByTechId->get($member->id, collect());
            $current = $jobs->firstWhere('status', 'in_progress') ?? $jobs->first();

            if (! $current) {
                $member->crew_status = 'off_duty';
                $member->crew_line = 'No active job';
            } elseif ($current->scheduled_eta_at && $current->scheduled_eta_at->isPast()) {
                $member->crew_status = 'at_risk';
                $member->crew_line = ($current->site?->name ?? $current->client?->name ?? $current->title) . ' — overdue';
            } else {
                $member->crew_status = 'on_track';
                $label = $current->status === 'in_progress' ? 'On site' : 'Assigned';
                $member->crew_line = $label . ' — ' . ($current->site?->name ?? $current->client?->name ?? $current->title);
            }

            $member->current_job = $current;
            $member->active_jobs = $jobs;

            return $member;
        });

        $jobsTodayCount = $activeJobs->count();
        $atRiskCount = $teamMembers->where('crew_status', 'at_risk')->count();

        $needsAction = ServiceRequest::with(['client', 'site'])
            ->where('team_lead_user_id', auth()->id())
            ->whereIn('status', ['pending', 'quoted', 'approved'])
            ->orderBy('scheduled_eta_at')
            ->get();

        $needsApprovalCount = $needsAction->where('status', 'pending')->count();
        $toAssignCount      = $needsAction->where('status', 'approved')->count();

        $toReview = ServiceRequest::with(['client', 'site'])
            ->where('team_lead_user_id', auth()->id())
            ->where('status', 'pending_review')
            ->orderByDesc('last_punch_out_at')
            ->get();

        $overrides = GeofenceOverrideRequest::with(['serviceRequest', 'technician'])
            ->whereHas('serviceRequest', fn ($q) => $q->where('team_lead_user_id', auth()->id()))
            ->where('status', 'pending')
            ->orderByDesc('id')->get();

        $conflicts = FieldEventLog::with(['serviceRequest', 'user'])
            ->where('result', 'conflict_rejected')
            ->whereNull('reviewed_at')
            ->whereHas('serviceRequest', fn ($q) => $q->where('team_lead_user_id', auth()->id()))
            ->orderByDesc('id')->get();

        return compact(
            'teamMembers', 'jobsTodayCount', 'atRiskCount',
            'needsAction', 'needsApprovalCount', 'toAssignCount',
            'inProgressCount', 'reworkCount', 'toReview',
            'overrides', 'conflicts'
        );
    }

    public function index(Request $request)
    {
        $data = $this->webTeamData();

        $view = $request->routeIs('technician.team.desktop') ? 'technician.team.desktop' : 'technician.team.index';

        return view($view, $data);
    }

    public function webRequests()
    {
        return view('technician.team.requests', $this->webTeamData());
    }

    public function webOverrides()
    {
        return view('technician.team.overrides', $this->webTeamData());
    }

    public function webConflicts()
    {
        return view('technician.team.conflicts', $this->webTeamData());
    }

    /**
     * D25 item 4 — a team lead can raise a new request themselves, using the same
     * staff form as the office, scoped to the clients/sites/categories they can see.
     */
    public function createRequest()
    {
        $tenantId = auth()->user()->tenant_id;
        $view = request()->routeIs('technician.team.requests.create')
            ? 'technician.team.requests-create-mobile'
            : 'technician.team.requests-create';

        return view($view, [
            'clients'    => Client::where('tenant_id', $tenantId)->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'categories' => ServiceCategory::where('tenant_id', $tenantId)->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeRequest(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'client_id'           => ['required', 'exists:clients,id'],
            'project_site_id'     => ['required', 'exists:project_sites,id'],
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'priority'            => ['required', 'in:' . implode(',', array_keys(ServiceRequest::PRIORITIES))],
            'title'               => ['required', 'string', 'max:190'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'client_po_reference' => ['nullable', 'string', 'max:100'],
            'attachments'          => ['nullable', 'array'],
            'attachments.*'        => ['file', 'max:10240'],
        ]);

        $cat = ServiceCategory::where('tenant_id', $tenantId)->findOrFail($data['service_category_id']);

        $proofSnapshot = [
            'require_before_photo' => (bool) $cat->require_before_photo,
            'require_after_photo'  => (bool) $cat->require_after_photo,
            'require_signature'    => (bool) $cat->require_signature,
            'require_checklist'    => (bool) $cat->require_checklist,
            'snapshot_at'          => now()->toIso8601String(),
            'category_code'        => $cat->code ?? null,
        ];

        $sr = ServiceRequest::create([
            'tenant_id'              => $tenantId,
            'sr_number'              => ServiceRequest::nextSrNumber($tenantId),
            'client_id'              => $data['client_id'],
            'project_site_id'        => $data['project_site_id'],
            'service_category_id'    => $data['service_category_id'],
            'priority'               => $data['priority'],
            'title'                  => $data['title'],
            'description'            => $data['description'] ?? null,
            'client_po_reference'    => $data['client_po_reference'] ?? null,
            'service_mode'           => 'on_site',
            'source'                 => 'front_desk',
            'coverage_type'          => 'out_of_coverage',
            'status'                 => 'pending',
            'billing_status'         => 'not_applicable',
            'invoice_mandatory'      => (bool) ($cat->invoice_mandatory ?? false),
            'enforce_geofence'       => false,
            'allow_signature_waiver' => false,
            'created_by_user_id'     => auth()->id(),
            'proof_rules_snapshot'   => $proofSnapshot,
            // Raised by the team lead themselves — keep it on their own queue.
            'team_lead_user_id'      => auth()->id(),
        ]);

        $policy  = $sr->resolveSlaPolicy();
        $started = now();
        $sr->update([
            'sla_clock_started_at'  => $started,
            'sla_response_due_at'   => $policy ? $started->copy()->addMinutes((int) $policy->response_minutes) : null,
            'sla_resolution_due_at' => $policy ? $started->copy()->addMinutes((int) $policy->resolution_minutes) : null,
        ]);

        // D25 — attachments the team lead adds when raising the request themselves.
        foreach ($request->file('attachments', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store("sr/{$sr->id}/attachments", 'public');

            Attachment::create([
                'tenant_id'           => $tenantId,
                'attachable_type'     => ServiceRequest::class,
                'attachable_id'       => $sr->id,
                'kind'                 => 'sr_attachment',
                'storage_path'         => $path,
                'original_name'        => $file->getClientOriginalName(),
                'mime_type'             => $file->getMimeType(),
                'size_bytes'            => $file->getSize(),
                'captured_at'           => now(),
                'uploaded_by_user_id'   => auth()->id(),
                'client_event_uuid'     => (string) Str::uuid(),
            ]);
        }

        $fromMobile = $request->routeIs('technician.team.requests.store');
    return redirect()->route($fromMobile ? 'technician.team.index' : 'technician.team.desktop.requests')
        ->with('success', "Request {$sr->sr_number} created.");
    }

    /**
     * AJAX endpoint — sites for a client, scoped the same way as the admin one, so the
     * new-request form can populate the Site select after a client is chosen.
     */
    public function sitesForClient(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $clientId = (int) $request->input('client_id');

        $sites = ProjectSite::where('tenant_id', $tenantId)
            ->where(function ($q) use ($clientId) {
                $q->where('client_id', $clientId)->orWhereNull('client_id');
            })
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'site_type', 'client_id']);

        return response()->json($sites);
    }

    public function approve(Request $request, ServiceRequest $serviceRequest)
{
    $this->authorizeTeamSr($serviceRequest);
    abort_unless(in_array($serviceRequest->status, ['pending', 'quoted']), 422);

    $data = $request->validate([
        'coverage_type' => ['required', 'in:in_warranty,out_of_coverage'],
    ]);

    if ($data['coverage_type'] === 'out_of_coverage') {
        // Blueprint 7.1 "Send for quote": the request stays Pending with
        // quote_requested_at set, and Finance sees it in the Waiting for quote
        // queue. Team leads can't see or build quotes themselves (Field View
        // spec), so this hands off to the office. No status change, so no
        // history row.
        $serviceRequest->update([
            'coverage_type'      => 'out_of_coverage',
            'quote_requested_at' => now(),
        ]);

        return back()->with('success', "{$serviceRequest->sr_number} is out of coverage — sent to the office to quote.");
    }

    $fromStatus = $serviceRequest->status;

    $serviceRequest->update([
        'status'              => 'approved',
        'approved_by_user_id' => auth()->id(),
        'coverage_type'       => 'in_warranty',
        'billing_status'      => 'not_applicable',
    ]);

    // Blueprint 7.1: every transition writes sr_status_history.
    \App\Models\SrStatusHistory::log(
        $serviceRequest, $fromStatus, 'approved', 'user', auth()->id(),
        null, ['coverage' => 'in_warranty']
    );

    return back()->with('success', "{$serviceRequest->sr_number} approved — in warranty.");
}

    public function reject(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTeamSr($serviceRequest);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $serviceRequest->update(['status' => 'cancelled', 'cancellation_reason' => $data['reason']]);
        return back()->with('success', 'Rejected.');
    }

    public function assign(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTeamSr($serviceRequest);
        $data = $request->validate(['assigned_tech_user_id' => ['required', 'in:' . implode(',', $this->teamMemberIds())]]);
        $serviceRequest->update(['assigned_tech_user_id' => $data['assigned_tech_user_id'], 'status' => 'assigned']);
        return back()->with('success', 'Assigned.');
    }

    public function approveOverride(GeofenceOverrideRequest $override)
    {
        abort_unless($override->serviceRequest?->team_lead_user_id === auth()->id(), 403);
        $override->update(['status' => 'approved', 'decided_by_user_id' => auth()->id(), 'decided_at' => now(), 'valid_until' => now()->addHours(2)]);
        return back()->with('success', 'Override approved.');
    }

    public function rejectOverride(Request $request, GeofenceOverrideRequest $override)
    {
        abort_unless($override->serviceRequest?->team_lead_user_id === auth()->id(), 403);
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:255']]);
        $override->update(['status' => 'rejected', 'decided_by_user_id' => auth()->id(), 'decided_at' => now(), 'decision_note' => $data['decision_note'] ?? null]);
        return back()->with('success', 'Override rejected.');
    }

    public function reviewConflict(FieldEventLog $log)
    {
        abort_unless($log->serviceRequest?->team_lead_user_id === auth()->id(), 403);
        $log->update(['reviewed_by_user_id' => auth()->id(), 'reviewed_at' => now()]);
        return back()->with('success', 'Marked reviewed.');
    }
}