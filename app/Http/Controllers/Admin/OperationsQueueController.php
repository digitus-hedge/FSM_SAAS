<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationsQueueController extends Controller
{
    public function index(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        // ─── Top metric tiles ───────────────────────────────────────────
        $geofenceWaiting = 0;
        if (Schema::hasTable('geofence_override_requests')) {
            $geofenceWaiting = DB::table('geofence_override_requests')
                ->where('status', 'pending')
                ->count();
        }

        $fieldConflicts = 0;
        if (Schema::hasTable('field_conflicts_logs')) {
            $fieldConflicts = DB::table('field_conflicts_logs')
                ->where('status', 'pending')
                ->count();
        } elseif (Schema::hasTable('time_conflict_logs')) {
            $fieldConflicts = DB::table('time_conflict_logs')
                ->where('status', 'pending')
                ->count();
        }

        $onHoldLong = ServiceRequest::where('is_on_hold', true)
            ->whereNotNull('hold_started_at')
            ->where('hold_started_at', '<', now()->subDay())
            ->count();

        // ─── Section 1: Pending approval ────────────────────────────────
        $pendingApprovalTotal = ServiceRequest::where('status', 'pending')->count();
        $pendingApproval = ServiceRequest::with(['client:id,name', 'site:id,name'])
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        // ─── Section 2: Pending QC review ───────────────────────────────
        $pendingQcTotal = ServiceRequest::where('status', 'pending_review')->count();
        $pendingQc = ServiceRequest::with(['client:id,name', 'site:id,name'])
            ->where('status', 'pending_review')
            ->orderBy('last_punch_out_at')
            ->get();

        // ─── Section 3: Overdue ─────────────────────────────────────────
        $overdueTotal = ServiceRequest::whereNotIn('status', ['completed', 'cancelled','pending'])
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<', now())
            ->count();
       $overdue = ServiceRequest::with(['client:id,name', 'site:id,name'])
    ->whereNotIn('status', ['completed', 'cancelled','pending'])
    ->whereNotNull('sla_resolution_due_at')
    ->where('sla_resolution_due_at', '<', now())
    ->orderBy('sla_resolution_due_at')

    ->get();

        // ─── Section 4: Awaiting invoice ────────────────────────────────
        $awaitingInvoiceTotal = ServiceRequest::where('billing_status', 'awaiting_invoice')->count();
        $awaitingInvoice = ServiceRequest::with(['client:id,name', 'site:id,name'])
            ->where('billing_status', 'awaiting_invoice')
            ->orderBy('updated_at')
       
            ->get();

        return view('admin.operations-queue.index', compact(
            'tenant',
            'geofenceWaiting', 'fieldConflicts', 'onHoldLong',
            'pendingApproval', 'pendingApprovalTotal',
            'pendingQc', 'pendingQcTotal',
            'overdue', 'overdueTotal',
            'awaitingInvoice', 'awaitingInvoiceTotal'
        ));
    }

    /**
     * Format waited-time for a timestamp.
     */
    public static function waited(?\Carbon\Carbon $since): string
    {
        if (! $since) return '—';
        $mins = max(0, $since->diffInMinutes(now()));
        if ($mins < 60) return "{$mins} min";
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return "{$h} h {$m} min";
    }
}