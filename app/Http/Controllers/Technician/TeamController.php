<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\FieldEventLog;
use App\Models\GeofenceOverrideRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;

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

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'jobs');
        $teamIds = $this->teamMemberIds();

        $jobsByTech = collect();
        $needsAction = collect();
        $overrides = collect();
        $conflicts = collect();

        if ($tab === 'jobs') {
            $jobs = ServiceRequest::with(['client', 'site', 'assignedTech'])
                ->whereIn('assigned_tech_user_id', $teamIds)
                ->whereIn('status', ['assigned', 'in_progress', 'rework'])
                ->orderBy('scheduled_eta_at')
                ->get();
            $jobsByTech = $jobs->groupBy(fn ($j) => $j->assignedTech?->name ?? 'Unassigned');
        }

        if ($tab === 'action') {
            $needsAction = ServiceRequest::with(['client', 'site'])
                ->where('team_lead_user_id', auth()->id())
                ->whereIn('status', ['pending', 'quoted', 'approved'])
                ->orderBy('scheduled_eta_at')
                ->get();
        }

        if ($tab === 'ovr') {
            $overrides = GeofenceOverrideRequest::with(['serviceRequest', 'technician'])
                ->whereHas('serviceRequest', fn ($q) => $q->where('team_lead_user_id', auth()->id()))
                ->where('status', 'pending')
                ->orderByDesc('id')->get();
        }

        if ($tab === 'conf') {
            $conflicts = FieldEventLog::with(['serviceRequest', 'user'])
                ->where('result', 'conflict_rejected')
                ->whereNull('reviewed_at')
                ->whereHas('serviceRequest', fn ($q) => $q->where('team_lead_user_id', auth()->id()))
                ->orderByDesc('id')->get();
        }

        $needsActionCount = ServiceRequest::where('team_lead_user_id', auth()->id())
            ->whereIn('status', ['pending', 'quoted', 'approved'])->count();
        $overrideCount = GeofenceOverrideRequest::whereHas('serviceRequest', fn ($q) => $q->where('team_lead_user_id', auth()->id()))->where('status', 'pending')->count();
        $conflictCount = FieldEventLog::where('result', 'conflict_rejected')->whereNull('reviewed_at')
            ->whereHas('serviceRequest', fn ($q) => $q->where('team_lead_user_id', auth()->id()))->count();

        $teamMembers = User::whereIn('id', $teamIds)->get();

        return view('technician.team.index', compact(
            'tab', 'jobsByTech', 'needsAction', 'overrides', 'conflicts',
            'needsActionCount', 'overrideCount', 'conflictCount', 'teamMembers'
        ));
    }

    public function approve(ServiceRequest $serviceRequest)
    {
        $this->authorizeTeamSr($serviceRequest);
        abort_unless(in_array($serviceRequest->status, ['pending', 'quoted']), 422);
        $serviceRequest->update(['status' => 'approved', 'approved_by_user_id' => auth()->id()]);
        return back()->with('success', 'Approved.');
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