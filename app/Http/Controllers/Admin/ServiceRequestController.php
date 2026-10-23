<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequestRequest;
use App\Models\Client;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServiceRequestController extends Controller
{
    private array $perPageOptions = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, $this->perPageOptions)) $perPage = 10;

        $requests = ServiceRequest::query()
            ->with(['client:id,name', 'site:id,name', 'category:id,name'])
            ->status($request->input('status'))
            ->priority($request->input('priority'))
            ->forClient($request->input('client_id'))
            ->search(trim((string) $request->input('q', '')))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.service-requests.index', [
            'requests'       => $requests,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
            'statuses'       => ServiceRequest::STATUSES,
            'priorities'     => ServiceRequest::PRIORITIES,
            'clients'        => Client::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('admin.service-requests.form', [
            'sr'         => new ServiceRequest(['priority' => 'medium']),
            'mode'       => 'create',
            'clients'    => Client::active()->orderBy('name')->get(['id', 'name']),
            'categories' => ServiceCategory::where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'sites'      => collect(),
            'priorities' => ServiceRequest::PRIORITIES,
        ]);
    }

    public function store(ServiceRequestRequest $request)
    {
        $data     = $request->validated();
        $tenantId = auth()->user()->tenant_id;

        $cat = ServiceCategory::where('tenant_id', $tenantId)->findOrFail($data['service_category_id']);

        $proofSnapshot = [
            'require_before_photo' => (bool) $cat->require_before_photo,
            'require_after_photo'  => (bool) $cat->require_after_photo,
            'require_signature'    => (bool) $cat->require_signature,
            'require_checklist'    => (bool) $cat->require_checklist,
            'snapshot_at'          => now()->toIso8601String(),
            'category_code'        => $cat->code ?? null,
        ];

        $sr = DB::transaction(function () use ($data, $tenantId, $cat, $proofSnapshot) {
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
            ]);

            // Start SLA clock at creation for every priority
            $policy  = $sr->resolveSlaPolicy();
            $started = now();
            $sr->update([
                'sla_clock_started_at'  => $started,
                'sla_response_due_at'   => $policy ? $started->copy()->addMinutes((int) $policy->response_minutes) : null,
                'sla_resolution_due_at' => $policy ? $started->copy()->addMinutes((int) $policy->resolution_minutes) : null,
            ]);

            return $sr;
        });

        $this->storeAttachments($request, $sr);

        return redirect()->route('service-requests.index')
            ->with('success', "Request {$sr->sr_number} created.");
    }

    public function show(ServiceRequest $service_request)
    {
        // $service_request->load([
        //     'client',
        //     'site',
        //     'category',
        //     'createdBy:id,name',
        //     'assignedTech:id,name',
        //     'teamLead:id,name',
        //     'holdReason:id,label',
        // ]);

        $service_request->load([
            'client',
            'site',
            'category',
            'createdBy:id,name',
            'assignedTech:id,name',
            'teamLead:id,name',
            'holdReason:id,label',
            'attachmentsRel',
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Find THIS tenant's field_technician role id
        $techRoleId = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->where('code', 'field_technician')
            ->value('id');

        $technicians = User::query()
            ->where('tenant_id', $tenantId)
            ->when($techRoleId, fn($q) => $q->where('role_id', $techRoleId))
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get(['id', 'name', 'team_lead_id']);

        return view('admin.service-requests.show', [
            'sr'          => $service_request,
            'policy'      => $service_request->resolveSlaPolicy(),
            'technicians' => $technicians,
        ]);
    }

    public function edit(ServiceRequest $service_request)
    {
        return view('admin.service-requests.form', [
            'sr'         => $service_request,
            'mode'       => 'edit',
            'clients'    => Client::active()->orderBy('name')->get(['id', 'name']),
            'categories' => ServiceCategory::where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'sites'      => ProjectSite::where('tenant_id', auth()->user()->tenant_id)
                ->where(function ($q) use ($service_request) {
                    $q->where('client_id', $service_request->client_id)->orWhereNull('client_id');
                })
                ->orderBy('name')
                ->get(['id', 'name', 'client_id']),
            'priorities' => ServiceRequest::PRIORITIES,
        ]);
    }

    // public function update(ServiceRequestRequest $request, ServiceRequest $service_request)
    // {
    //     $data = $request->validated();
    //     unset($data['attachments']);

    //     $service_request->update($data);

    //     $this->storeAttachments($request, $service_request);

    //     return redirect()->route('service-requests.index')
    //         ->with('success', "Request {$service_request->sr_number} updated.");
    // }


    public function update(ServiceRequestRequest $request, ServiceRequest $service_request)
    {
        $data = $request->validated();
        unset($data['attachments']);

        // Remove only the attachments selected using X.
        $removed = $request->input('removed_attachments', []);

        foreach ($removed as $filename) {
            if (
                !is_string($filename) ||
                str_contains($filename, '/') ||
                str_contains($filename, '\\') ||
                str_contains($filename, '..')
            ) {
                continue;
            }

            $attachment = \App\Models\Attachment::where(
                'attachable_type',
                \App\Models\ServiceRequest::class
            )
                ->where('attachable_id', $service_request->id)
                ->where('storage_path', 'like', '%/' . $filename)
                ->first();

            if ($attachment) {
                Storage::disk('public')->delete($attachment->storage_path);

                $attachment->storage_path = null;
                $attachment->original_name = null;
                $attachment->mime_type = null;
                $attachment->size_bytes = null;
                $attachment->sha256 = null;
                $attachment->save();
            }
        }

        $service_request->update($data);

        $this->storeAttachments($request, $service_request);

        return redirect()
            ->route('service-requests.index')
            ->with('success', "Request {$service_request->sr_number} updated.");
    }

    public function destroy(ServiceRequest $service_request)
    {
        $service_request->delete();

        return redirect()->route('service-requests.index')
            ->with('success', "Request {$service_request->sr_number} deleted.");
    }

    /**
     * Delete a single attachment file from an SR.
     * Route: DELETE /service-requests/{service_request}/attachments/{filename}
     */
    public function deleteAttachment(ServiceRequest $service_request, string $filename)
    {
        if (str_contains($filename, '/') || str_contains($filename, '..') || str_contains($filename, '\\')) {
            abort(400, 'Bad filename.');
        }

        $att = \App\Models\Attachment::where('attachable_type', \App\Models\ServiceRequest::class)
            ->where('attachable_id', $service_request->id)
            ->where('storage_path', 'like', "%/{$filename}")
            ->first();

        if ($att) {
            $att->deleteWithFile();
        } else {
            // Fallback for legacy files on disk with no DB row
            $path = "sr/{$service_request->id}/attachments/{$filename}";
            if (\Storage::disk('public')->exists($path)) {
                \Storage::disk('public')->delete($path);
            }
        }

        return back()->with('success', 'Attachment removed.');
    }

    /**
     * Save uploaded files to storage/app/public/sr/{id}/attachments/
     * Filenames: <timestamp>_<uniqid>__<sanitized-original-name>
     * so we can display the original name without needing a DB table.
     */
    private function storeAttachments(Request $request, ServiceRequest $sr): void
    {
        if (!$request->hasFile('attachments')) return;

        foreach ($request->file('attachments') as $file) {
            $safeName   = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $storedName = time() . '_' . uniqid() . '__' . $safeName;
            $relPath    = "sr/{$sr->id}/attachments/{$storedName}";

            // Save file to disk
            $file->storeAs("sr/{$sr->id}/attachments", $storedName, 'public');

            // Insert into attachments table (polymorphic)
            \App\Models\Attachment::create([
                'tenant_id'           => $sr->tenant_id,
                'attachable_type'     => \App\Models\ServiceRequest::class,
                'attachable_id'       => $sr->id,
                'kind'                => 'sr_attachment',
                'storage_path'        => $relPath,
                'original_name'       => $file->getClientOriginalName(),
                'mime_type'           => $file->getClientMimeType(),
                'size_bytes'          => $file->getSize(),
                'sha256'              => hash_file('sha256', $file->getRealPath()),
                'uploaded_by_user_id' => auth()->id(),
                'client_event_uuid'   => (string) \Illuminate\Support\Str::uuid(),
            ]);
        }
    }

    /* ============================================================
     | State-machine actions
     * ============================================================ */

    public function approve(Request $request, ServiceRequest $service_request)
    {
        $data = $request->validate([
            'coverage' => ['required', 'in:in_contract,in_warranty,goodwill,needs_quote'],
            'note'     => ['nullable', 'string', 'max:500'],
        ]);

        // "Needs a quote" branches to QUOTED, not APPROVED
        if ($data['coverage'] === 'needs_quote') {
            $service_request->update([
                'status'                   => 'quoted',
                'coverage_type'            => 'out_of_coverage',
                'quote_requested_at'       => now(),
                'coverage_override_reason' => $data['note'] ?? null,
            ]);

            return back()->with('success', "{$service_request->sr_number} marked for quoting.");
        }

        // In-contract / In-warranty / Goodwill → APPROVE and start SLA clock
        $policy  = $service_request->resolveSlaPolicy();
        $started = now();

        $service_request->update([
            'status'                   => 'approved',
            'approved_by_user_id'      => auth()->id(),
            'coverage_type'            => $data['coverage'],
            'coverage_override_reason' => $data['note'] ?? null,
            'sla_clock_started_at'     => $started,
            'sla_response_due_at'      => $policy ? $started->copy()->addMinutes((int) $policy->response_minutes) : null,
            'sla_resolution_due_at'    => $policy ? $started->copy()->addMinutes((int) $policy->resolution_minutes) : null,
        ]);

        return back()->with('success', "{$service_request->sr_number} approved — " . str_replace('_', ' ', $data['coverage']) . '.');
    }

    public function assign(Request $request, ServiceRequest $service_request)
    {
        $data = $request->validate([
            'technician_id'          => ['required', 'exists:users,id'],
            'arrival_time'           => ['required', 'date'],
            'enforce_geofence'       => ['nullable', 'boolean'],
            'allow_signature_waiver' => ['nullable', 'boolean'],
            'note'                   => ['nullable', 'string', 'max:500'],
        ]);

        $tech = User::findOrFail($data['technician_id']);

        $service_request->update([
            'status'                 => 'assigned',
            'assigned_tech_user_id'  => $tech->id,
            'team_lead_user_id'      => $tech->team_lead_id,          // auto-set from technician's team
            'scheduled_eta_at'       => $data['arrival_time'],
            'enforce_geofence'       => (bool) ($data['enforce_geofence'] ?? false),
            'allow_signature_waiver' => (bool) ($data['allow_signature_waiver'] ?? false),
        ]);

        // Note persistence — proper storage comes with sr_notes table (Release B).
        if (!empty($data['note'])) {
            $prefix = $service_request->coverage_override_reason ? $service_request->coverage_override_reason . "\n" : '';
            $service_request->update(['coverage_override_reason' => $prefix . 'Assign note: ' . $data['note']]);
        }

        return back()->with('success', "Request {$service_request->sr_number} assigned to {$tech->name}.");
    }

    public function reject(Request $request, ServiceRequest $service_request)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $service_request->update([
            'status'              => 'cancelled',
            'cancellation_reason' => 'Rejected: ' . $data['reason'],
        ]);

        return redirect()->route('service-requests.show', $service_request)
            ->with('success', "{$service_request->sr_number} rejected.");
    }

    public function sendForQuote(ServiceRequest $service_request)
    {
        $service_request->update([
            'status'             => 'quoted',
            'coverage_type'      => 'out_of_coverage',
            'quote_requested_at' => now(),
        ]);

        return back()->with('success', "{$service_request->sr_number} sent for quoting.");
    }

    public function changePriority(Request $request, ServiceRequest $service_request)
    {
        $data = $request->validate([
            'priority' => ['required', 'in:' . implode(',', array_keys(ServiceRequest::PRIORITIES))],
        ]);

        $wasEmergency   = $service_request->priority === 'emergency';
        $isNowEmergency = $data['priority'] === 'emergency';

        $service_request->update(['priority' => $data['priority']]);

        // Raising to Emergency starts the SLA clock if it hasn't started
        if (!$wasEmergency && $isNowEmergency && !$service_request->sla_clock_started_at) {
            $policy  = $service_request->fresh()->resolveSlaPolicy();
            $started = now();

            $service_request->update([
                'sla_clock_started_at'  => $started,
                'sla_response_due_at'   => $policy ? $started->copy()->addMinutes((int) $policy->response_minutes) : null,
                'sla_resolution_due_at' => $policy ? $started->copy()->addMinutes((int) $policy->resolution_minutes) : null,
            ]);
        }
        // Changing to any non-emergency priority — recalculate due times with the new policy
        elseif ($data['priority'] !== 'emergency' && $service_request->sla_clock_started_at) {
            $policy = $service_request->fresh()->resolveSlaPolicy();
            if ($policy) {
                $started = $service_request->sla_clock_started_at;
                $service_request->update([
                    'sla_response_due_at'   => $started->copy()->addMinutes((int) $policy->response_minutes),
                    'sla_resolution_due_at' => $started->copy()->addMinutes((int) $policy->resolution_minutes),
                ]);
            }
        }

        return back()->with('success', "{$service_request->sr_number} priority changed to " . ucfirst($data['priority']) . '.');
    }

    public function cancel(Request $request, ServiceRequest $service_request)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $service_request->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $data['reason'],
        ]);

        return redirect()->route('service-requests.show', $service_request)
            ->with('success', "{$service_request->sr_number} cancelled.");
    }

    public function markReview(ServiceRequest $service_request)
    {
        $service_request->update(['status' => 'pending_review']);
        return back()->with('success', "{$service_request->sr_number} submitted for review.");
    }

    /**
     * AJAX endpoint — returns sites for a client as JSON.
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
}
