<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\GeofenceOverrideRequest;
use App\Models\FieldEventLog;
use App\Models\User;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    private function assertTeamLead(): void
    {
        abort_unless(auth()->user()->role?->code === 'team_lead', 403);
    }

    /** User ids of technicians who report to the signed-in team lead. */
    private function teamTechIds(): array
    {
        // ASSUMPTION: users.team_lead_id points at this team lead's user id.
        // Change this one line if your schema links teams differently.
        return User::where('team_lead_id', auth()->id())->pluck('id')->all();
    }

    public function index(Request $request)
    {
        $this->assertTeamLead();
        $tab = $request->input('tab', 'jobs');
        $techIds = $this->teamTechIds();

        $jobs = $action = $overrides = $conflicts = collect();
        $counts = ['action' => 0, 'overrides' => 0, 'conflicts' => 0];

        if ($tab === 'jobs') {
            $jobs = ServiceRequest::whereIn('assigned_tech_user_id', $techIds)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->with(['client:id,name', 'site:id,name', 'assignedTech:id,name'])
                ->orderBy('scheduled_eta_at')
                ->get()
                ->groupBy('assigned_tech_user_id');
        }

        if ($tab === 'action') {
            $action = ServiceRequest::where(function ($q) use ($techIds) {
                    $q->where('status', 'pending')               // needs approval (not yet assigned to the team)
                      ->orWhere(function ($q2) use ($techIds) {
                          $q2->where('status', 'approved')        // approved, needs a technician assigned
                             ->orWhereIn('assigned_tech_user_id', $techIds);
                      });
                })
                ->whereIn('status', ['pending', 'approved'])
                ->with(['client:id,name', 'site:id,name'])
                ->orderByRaw("FIELD(priority,'emergency','critical','high','medium','low')")
                ->get();
        }

        if ($tab === 'overrides' || $request->boolean('counts_only')) {
            $overrides = GeofenceOverrideRequest::whereIn('technician_user_id', $techIds)
                ->where('status', 'pending')
                ->with(['serviceRequest:id,sr_number,title'])
                ->latest('requested_at_device')
                ->get();
        }

        if ($tab === 'conflicts' || $request->boolean('counts_only')) {
            $conflicts = FieldEventLog::whereIn('user_id', $techIds)
                ->where('result', 'conflict_rejected')
                ->whereNull('reviewed_at')
                ->with(['serviceRequest:id,sr_number,title', 'user:id,name'])
                ->latest('synced_to_server_at')
                ->get();
        }

        $counts = [
            'action'    => ServiceRequest::whereIn('status', ['pending', 'approved'])
                            ->where(fn($q) => $q->where('status', 'pending')->orWhereIn('assigned_tech_user_id', $techIds))
                            ->count(),
            'overrides' => GeofenceOverrideRequest::whereIn('technician_user_id', $techIds)->where('status', 'pending')->count(),
            'conflicts' => FieldEventLog::whereIn('user_id', $techIds)->where('result', 'conflict_rejected')->whereNull('reviewed_at')->count(),
        ];

        return view('technician.team.index', compact('tab', 'jobs', 'action', 'overrides', 'conflicts', 'counts'));
    }

    public function approveOverride(Request $request, GeofenceOverrideRequest $override)
    {
        $this->assertTeamLead();
        abort_unless(in_array($override->technician_user_id, $this->teamTechIds(), true), 403);

        $override->update([
            'status'               => 'approved',
            'decided_by_user_id'   => auth()->id(),
            'decided_at'           => now(),
            'valid_until'          => now()->addHours(2),
        ]);

        return redirect()->route('technician.team.index', ['tab' => 'overrides'])
            ->with('success', 'Override approved. The technician can punch in now.');
    }

    public function rejectOverride(Request $request, GeofenceOverrideRequest $override)
    {
        $this->assertTeamLead();
        abort_unless(in_array($override->technician_user_id, $this->teamTechIds(), true), 403);

        $data = $request->validate(['note' => 'required|string|min:3|max:255']);

        $override->update([
            'status'             => 'rejected',
            'decided_by_user_id' => auth()->id(),
            'decided_at'         => now(),
            'decision_note'      => $data['note'],
        ]);

        return redirect()->route('technician.team.index', ['tab' => 'overrides'])
            ->with('success', 'Override rejected.');
    }

    public function markConflictReviewed(FieldEventLog $log)
    {
        $this->assertTeamLead();
        abort_unless(in_array($log->user_id, $this->teamTechIds(), true), 403);

        $log->update(['reviewed_at' => now(), 'reviewed_by_user_id' => auth()->id()]);

        return redirect()->route('technician.team.index', ['tab' => 'conflicts'])
            ->with('success', 'Marked reviewed.');
    }

    public function storeNew(Request $request)
    {
        $this->assertTeamLead();
        // Reuses the office create form rather than guessing its validation here.
        return redirect()->route('service-requests.create');
    }
}