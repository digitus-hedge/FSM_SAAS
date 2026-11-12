<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequestRequest;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use App\Services\Contracts\ContractCoverage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServiceRequestController extends Controller
{
    private array $perPageOptions = [10, 25, 50, 100];


    // public function index(Request $request)
    // {
    //     $perPage = (int) $request->input('per_page', 10);

    //     if (!in_array($perPage, $this->perPageOptions)) 
    //     {
    //     $perPage = 10;
    //     }

    //     $stageStatuses = [
    //         'needs'     => 'pending',
    //         'quote'     => 'waiting_for_quote',
    //         'quoted'    => 'quoted',
    //         'assign'    => 'approved',
    //         'assigned'  => 'assigned',
    //         'progress'  => 'in_progress',
    //         'review'    => 'pending_review',
    //         'invoice'   => 'awaiting_invoice',
    //         'rework'    => 'rework',
    //         'done'      => 'completed',
    //         'cancelled' => 'cancelled',
    //     ];

    //     $activeStage = $request->input('stage', 'all');

    //     if($activeStage !== 'all' && !array_key_exists($activeStage, $stageStatuses)) 
    //     {
    //     $activeStage = 'all';
    //     }

    //     $countQuery = ServiceRequest::query();

    //     $stageCounts = [];

    //     foreach ($stageStatuses as $key => $status) {
    //         $stageCounts[$key] = (clone $countQuery)
    //             ->where('status', $status)
    //             ->count();
    //     }

    //     $stageCounts['all'] = (clone $countQuery)->count();

    //     $query = ServiceRequest::query()
    //         ->with([
    //             'client:id,name',
    //             'site:id,name',
    //             'category:id,name',
    //         ]);

    //     if($activeStage !== 'all')
    //     {
    //     $query->where('status', $stageStatuses[$activeStage]);
    //     }

    //     $query->status($request->input('status'))
    //         ->priority($request->input('priority'))
    //         ->forClient($request->input('client_id'))
    //         ->search(trim((string) $request->input('q', '')));

    //     $requests = $query
    //         ->orderByDesc('id')
    //         ->paginate($perPage)
    //         ->withQueryString();

    //     return view('admin.service-requests.index', [
    //         'requests'       => $requests,
    //         'perPage'        => $perPage,
    //         'perPageOptions' => $this->perPageOptions,
    //         'statuses'       => ServiceRequest::STATUSES,
    //         'priorities'     => ServiceRequest::PRIORITIES,
    //         'clients'        => Client::active()->orderBy('name')->get(['id', 'name']),
    //         'stageCounts'    => $stageCounts,
    //         'activeStage'    => $activeStage,
    //     ]);
    // }


    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        if (!in_array($perPage, $this->perPageOptions)) {
            $perPage = 10;
        }

        // Stage tabs mapped to database statuses
        $stageStatuses = [
            'needs'     => 'pending',
            'quoted'    => 'quoted',
            'assign'    => 'approved',
            'assigned'  => 'assigned',
            'progress'  => 'in_progress',
            'review'    => 'pending_review',
            'invoice'   => 'awaiting_invoice',
            'rework'    => 'rework',
            'done'      => 'completed',
            'cancelled' => 'cancelled',
        ];

        $activeStage = $request->input('stage', 'all');

        // Allow 'overdue' and 'all' alongside the regular statuses
        $validStages = array_merge(array_keys($stageStatuses), ['quote', 'overdue', 'all']);
        if (! in_array($activeStage, $validStages, true)) {
            $activeStage = 'all';
        }

        // Base query for stage counts
        $countQuery = ServiceRequest::query();
        $stageCounts = [];

        foreach ($stageStatuses as $key => $status) {
            $stageCounts[$key] = (clone $countQuery)
                ->where('status', $status)
                ->when($key === 'needs', fn($q) => $q->whereNull('quote_requested_at'))
                ->count();
        }

        // "Waiting for quote" is Pending with quote_requested_at set, not its own
        // status (blueprint §7.6, "Send for quote").
        $stageCounts['quote'] = (clone $countQuery)
            ->where('status', 'pending')
            ->whereNotNull('quote_requested_at')
            ->count();

        $stageCounts['all'] = (clone $countQuery)->count();

        // Main requests query
        $query = ServiceRequest::query()
            ->with([
                'client:id,name',
                'site:id,name',
                'category:id,name',
            ]);

        // Filter by selected tab
        if ($activeStage === 'overdue') {
            $query->whereNotIn('status', ['completed', 'cancelled','pending'])
                ->whereNotNull('sla_resolution_due_at')
                ->where('sla_resolution_due_at', '<', now());
        } elseif ($activeStage === 'quote') {
            $query->where('status', 'pending')->whereNotNull('quote_requested_at');
        } elseif ($activeStage === 'needs') {
            $query->where('status', 'pending')->whereNull('quote_requested_at');
        } elseif ($activeStage !== 'all') {
            $query->where('status', $stageStatuses[$activeStage]);
        }

        // Also support legacy ?overdue=1 param from Operations Queue "See all" link
        if ($request->boolean('overdue') && $activeStage !== 'overdue') {
            $query->whereNotIn('status', ['completed', 'cancelled','pending'])
                ->whereNotNull('sla_resolution_due_at')
                ->where('sla_resolution_due_at', '<', now());
            $activeStage = 'overdue';
        }

        // Contracts: Pending requests that wait for a contract choice (?choice=1)
        if ($request->boolean('choice')) {
            $query->where('status', 'pending')->where('contract_choice_pending', true);
        }

        // Keep existing filters
        $query->status($request->input('status'))
            ->priority($request->input('priority'))
            ->forClient($request->input('client_id'))
            ->search(trim((string) $request->input('q', '')));

        // Overdue defaults to deadline order; everything else by newest id
        if ($activeStage === 'overdue') {
            $query->orderBy('sla_resolution_due_at');
        } else {
            $query->orderByDesc('id');
        }

        $requests = $query
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.service-requests.index', [
            'requests'       => $requests,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
            'statuses'       => ServiceRequest::STATUSES,
            'priorities'     => ServiceRequest::PRIORITIES,
            'clients'        => Client::active()->orderBy('name')->get(['id', 'name']),
            'stageCounts'    => $stageCounts,
            'activeStage'    => $activeStage,
            'choiceCount'    => (clone $countQuery)->where('status', 'pending')->where('contract_choice_pending', true)->count(),
        ]);
    }



    public function create(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $sr       = new ServiceRequest(['priority' => 'medium']);
        $sites    = collect();
        $fromQr   = false;

        // Opened from an asset QR landing page: fill client, site, category and asset.
        if ($request->filled('asset')) {
            $asset = Asset::where('tenant_id', $tenantId)->selectable()->find((int) $request->input('asset'));
            if ($asset) {
                $sr->client_id           = $asset->client_id;
                $sr->project_site_id     = $asset->project_site_id;
                $sr->service_category_id = $asset->service_category_id;
                $sr->primary_asset_id    = $asset->id;
                $fromQr = true;

                $sites = ProjectSite::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($asset) {
                        $q->where('client_id', $asset->client_id)->orWhereNull('client_id');
                    })
                    ->where('is_active', 1)
                    ->orderBy('name')
                    ->get(['id', 'name', 'client_id']);
            }
        }

        return view('admin.service-requests.form', [
            'sr'         => $sr,
            'mode'       => 'create',
            'clients'    => Client::active()->orderBy('name')->get(['id', 'name']),
            'categories' => ServiceCategory::where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'sites'      => $sites,
            'priorities' => ServiceRequest::PRIORITIES,
            'fromQr'     => $fromQr,
        ]);
    }

    public function store(ServiceRequestRequest $request)
    {
        $data     = $request->validated();
        $tenantId = auth()->user()->tenant_id;

        $cat = ServiceCategory::where('tenant_id', $tenantId)->findOrFail($data['service_category_id']);
        $teamLeadId = User::where('tenant_id', $tenantId)
            ->whereNull('deactivated_at')
            ->whereHas('coveredCategories', fn($q) => $q->where('service_categories.id', $data['service_category_id']))
            ->orderBy('id')
            ->value('id');
        $proofSnapshot = [
            'require_before_photo' => (bool) $cat->require_before_photo,
            'require_after_photo'  => (bool) $cat->require_after_photo,
            'require_signature'    => (bool) $cat->require_signature,
            'require_checklist'    => (bool) $cat->require_checklist,
            'snapshot_at'          => now()->toIso8601String(),
            'category_code'        => $cat->code ?? null,
        ];

        // A request raised from an asset's QR page is tagged as such.
        $source = ($request->boolean('from_qr') && ! empty($data['primary_asset_id'])) ? 'qr_scan' : 'front_desk';

        $sr = DB::transaction(function () use ($data, $tenantId, $cat, $proofSnapshot, $teamLeadId, $source) {
            $sr = ServiceRequest::create([
                'tenant_id'              => $tenantId,
                'sr_number'              => ServiceRequest::nextSrNumber($tenantId),
                'client_id'              => $data['client_id'],
                'project_site_id'        => $data['project_site_id'],
                'service_category_id'    => $data['service_category_id'],
                'primary_asset_id'       => $data['primary_asset_id'] ?? null,
                'priority'               => $data['priority'],
                'title'                  => $data['title'],
                'description'            => $data['description'] ?? null,
                'client_po_reference'    => $data['client_po_reference'] ?? null,
                'service_mode'           => 'on_site',
                'source'                 => $source,
                'coverage_type'          => 'out_of_coverage',
                'status'                 => 'pending',
                'billing_status'         => 'not_applicable',
                'invoice_mandatory'      => (bool) ($cat->invoice_mandatory ?? false),
                'enforce_geofence'       => false,
                'allow_signature_waiver' => false,
                'created_by_user_id'     => auth()->id(),
                'proof_rules_snapshot'   => $proofSnapshot,
                'team_lead_user_id'      => $teamLeadId,
            ]);

            // Start SLA clock at creation for every priority
            $policy  = $sr->resolveSlaPolicy();
            $started = now();
            $sr->update([
                'sla_clock_started_at'  => $started,
                'sla_response_due_at'   => $policy ? $started->copy()->addMinutes((int) $policy->response_minutes) : null,
                'sla_resolution_due_at' => $policy ? $started->copy()->addMinutes((int) $policy->resolution_minutes) : null,
            ]);
            \App\Models\SrStatusHistory::log($sr, null, 'pending', 'user', auth()->id());
            app(ContractCoverage::class)->onCreated($sr);   // D3: flag when several contracts match
            return $sr;
        });

        $this->storeAttachments($request, $sr);

        return redirect()->route('service-requests.index')
            ->with('success', "Request {$sr->sr_number} created.");
    }

    public function show(ServiceRequest $service_request)
{
        $service_request->load([
        'client', 'site', 'category', 'primaryAsset', 'createdBy:id,name,role_id', 'createdBy.role:id,name',
        'assignedTech:id,name', 'teamLead:id,name', 'holdReason:id,label', 'attachmentsRel',
    ]);

    $tenantId = auth()->user()->tenant_id;

    $techRoleIds = DB::table('roles')
        ->where('tenant_id', $tenantId)
        ->whereIn('code', ['field_technician', 'team_lead'])
        ->pluck('id');

    $technicians = User::query()
        ->with('role:id,code')
        ->where('tenant_id', $tenantId)
        ->when($techRoleIds->isNotEmpty(), fn($q) => $q->whereIn('role_id', $techRoleIds))
        ->whereNull('deactivated_at')
        ->orderBy('name')
        ->get(['id', 'name', 'team_lead_id', 'role_id']);

    $acceptedQuote = \App\Models\Quote::where('service_request_id', $service_request->id)
        ->where('status', 'accepted')
        ->orderByDesc('id')
        ->first();

    $cov       = app(ContractCoverage::class);
    $contractsOn = $cov->enabled();
    $coverage  = ($contractsOn && $service_request->status === 'pending') ? $cov->resolve($service_request) : null;
    $user      = auth()->user();

    // Keep the "several contracts" flag in step with what matches today (6.3).
    if ($coverage && $service_request->contract_choice_reason !== 'cap_reached') {
        $wantsSeveral = $coverage['needs_choice'] && $coverage['choice_reason'] === 'several';
        if ($wantsSeveral && ! $service_request->contract_choice_pending) {
            $cov->flagChoice($service_request, $coverage);
        } elseif (! $wantsSeveral && $service_request->contract_choice_pending) {
            $service_request->forceFill(['contract_choice_pending' => false, 'contract_choice_reason' => null])->save();
        }
    }

    $service_request->loadMissing('contract');

    return view('admin.service-requests.show', [
        'sr'              => $service_request,
        'policy'          => $service_request->resolveSlaPolicy(),
        'technicians'     => $technicians,
        'acceptedQuote'   => $acceptedQuote,
        'contractsOn'     => $contractsOn,
        'coverage'        => $coverage,
        'canSeeContract'  => (bool) $user?->canDo('contract.view'),
        'canChoose'       => (bool) $user?->canDo('contract.choose'),
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

    // Blueprint 7.1 "Send for quote": the request stays Pending with
    // quote_requested_at set. No status change, so no history row.
    if ($data['coverage'] === 'needs_quote') {
        $service_request->update([
            'coverage_type'            => 'out_of_coverage',
            'quote_requested_at'       => now(),
            'coverage_override_reason' => $data['note'] ?? null,
        ]);
        return back()->with('success', "{$service_request->sr_number} sent for quoting.");
    }

    $cov = app(ContractCoverage::class);

    // Without the contracts feature (or on a downgrade) the Release A manual choice stays.
    if (! $cov->enabled()) {
        $this->completeApproval($service_request, $data['coverage'], null, $data['note'] ?? null, false);
        return back()->with('success', "{$service_request->sr_number} approved — " . str_replace('_', ' ', $data['coverage']) . '.');
    }

    // 6.2: work the coverage out again at approval (D3).
    $result = $cov->resolve($service_request);

    if ($result['needs_choice']) {
        $cov->flagChoice($service_request, $result);
        return back()->with('error', $result['choice_reason'] === 'several'
            ? $result['reason']
            : $result['reason'] . ' A manager must decide how to continue.');
    }

    $suggested = $result['type'];
    $override  = $data['coverage'] !== $suggested;
    $note      = trim((string) ($data['note'] ?? ''));

    if ($data['coverage'] === 'in_contract' && ! $result['contract']) {
        return back()->with('error', 'No contract covers this request.');
    }
    if ($override && mb_strlen($note) < 3) {
        return back()->with('error', 'Give a reason for changing the coverage.');
    }

    $outcome = DB::transaction(function () use ($service_request, $data, $result, $note, $override, $cov) {
        $contract = null;

        if ($data['coverage'] === 'in_contract') {
            // 6.6: lock the contract first so two approvals cannot both take the last call.
            $contract = Contract::lockForUpdate()->find($result['contract']->id);
            if (! $contract || $contract->capReached()) {
                return $contract ? ['cap', $contract] : ['gone', null];
            }
            $cov->countCall($service_request, $contract, false);
        }

        $this->completeApproval($service_request, $data['coverage'], $contract, $note ?: null, $override);
        return ['ok', $contract];
    });

    if ($outcome[0] === 'cap') {
        $cov->flagChoice($service_request, ['choice_reason' => 'cap_reached', 'contract' => $outcome[1]]);
        return back()->with('error', "Contract {$outcome[1]->contract_number} has used {$outcome[1]->calls_used_current_period} of {$outcome[1]->max_calls} calls. A manager must decide how to continue.");
    }
    if ($outcome[0] === 'gone') {
        return back()->with('error', 'The contract is no longer available. Open the request again.');
    }

    return back()->with('success', "{$service_request->sr_number} approved — " . str_replace('_', ' ', $data['coverage']) . '.');
}

    /**
     * Writes the approval: status, coverage, SLA times and history.
     * The contract (if any) stays on the request for reporting.
     */
    private function completeApproval(ServiceRequest $sr, string $coverage, ?Contract $contract, ?string $note, bool $override): void
    {
        $fromStatus = $sr->status;
        $started    = now();

        $sr->fill([
            'status'                       => 'approved',
            'approved_by_user_id'          => auth()->id(),
            'coverage_type'                => $coverage,
            'billing_status'               => 'not_applicable',
            'coverage_override_reason'     => $note,
            'coverage_override_by_user_id' => $override ? auth()->id() : null,
            'contract_id'                  => $contract?->id,
            'contract_choice_pending'      => false,
            'contract_choice_reason'       => null,
            'sla_clock_started_at'         => $started,
        ]);

        // D10: a contract's response and resolution times replace the policy's.
        $sr->unsetRelation('contract');
        [$resp, $reso] = $sr->slaMinutes();
        $sr->fill([
            'sla_response_due_at'   => $resp !== null ? $started->copy()->addMinutes($resp) : null,
            'sla_resolution_due_at' => $reso !== null ? $started->copy()->addMinutes($reso) : null,
        ])->save();

        \App\Models\SrStatusHistory::log($sr, $fromStatus, 'approved', 'user', auth()->id(), $note, ['coverage' => $coverage]);

        if ($override) {
            app(AuditLogger::class)->log('request.coverage_overridden', [
                'item_type' => ServiceRequest::class, 'item_id' => $sr->id,
                'new' => ['sr_number' => $sr->sr_number, 'coverage' => $coverage, 'reason' => $note],
            ]);
        }
    }

    /** CNT-04: the manager picks one of several matching contracts. Counts nothing. */
    public function chooseContract(Request $request, ServiceRequest $service_request)
    {
        abort_unless(auth()->user()?->canDo('contract.choose'), 403);
        abort_unless($service_request->status === 'pending', 422, 'Only a pending request can have its contract chosen.');

        $data = $request->validate(['contract_id' => ['required', 'integer']], ['contract_id.required' => 'Choose a contract.']);

        app(ContractCoverage::class)->choose($service_request, (int) $data['contract_id']);

        return back()->with('success', 'Contract chosen. You can approve the request now.');
    }

    /** CNT-06 / D9: the cap is reached. The choice also approves the request (decision B1). */
    public function contractDecision(Request $request, ServiceRequest $service_request)
    {
        abort_unless(auth()->user()?->canDo('contract.choose'), 403);
        abort_unless(
            $service_request->status === 'pending' && $service_request->contract_choice_pending && $service_request->contract_choice_reason === 'cap_reached',
            422, 'This request is not waiting for a cap decision.'
        );

        $data = $request->validate([
            'decision' => ['required', 'in:count,goodwill,out'],
            'reason'   => ['nullable', 'string', 'max:500'],
        ]);
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($data['decision'] === 'goodwill' && mb_strlen($reason) < 3) {
            return back()->with('error', 'Goodwill needs a reason.');
        }

        $cov = app(ContractCoverage::class);

        if ($data['decision'] === 'out') {
            // Contract cleared; the Release A quote path starts.
            $service_request->update([
                'contract_id' => null, 'contract_choice_pending' => false, 'contract_choice_reason' => null,
                'coverage_type' => 'out_of_coverage', 'quote_requested_at' => now(),
                'coverage_override_reason' => $reason ?: 'Contract cap reached',
                'coverage_override_by_user_id' => auth()->id(),
            ]);
            app(AuditLogger::class)->log('request.coverage_overridden', [
                'item_type' => ServiceRequest::class, 'item_id' => $service_request->id,
                'new' => ['sr_number' => $service_request->sr_number, 'coverage' => 'out_of_coverage', 'reason' => 'Contract cap reached'],
            ]);
            return back()->with('success', "{$service_request->sr_number} sent for quoting.");
        }

        DB::transaction(function () use ($service_request, $data, $reason, $cov) {
            $contract = Contract::lockForUpdate()->findOrFail($service_request->contract_id);

            if ($data['decision'] === 'count') {
                $cov->countCall($service_request, $contract, true);       // counted, marked over cap
                $service_request->forceFill(['contract_over_cap' => true])->save();
                $this->completeApproval($service_request, 'in_contract', $contract, $reason ?: null, true);
            } else {
                // Goodwill: no billing, no call counted, the contract stays on the request for reporting.
                $this->completeApproval($service_request, 'goodwill', $contract, $reason, true);
            }
        });

        return back()->with('success', "{$service_request->sr_number} approved — " . ($data['decision'] === 'count' ? 'in contract (over the cap).' : 'goodwill.'));
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
    $fromStatus = $service_request->status;

    // If the job is assigned straight to a team lead (no team_lead_id of their own),
    // keep it visible on THEIR team-lead queue rather than leaving it ownerless.
    $teamLeadUserId = $tech->team_lead_id ?? ($tech->role?->code === 'team_lead' ? $tech->id : null);

    // Blueprint: "Enforce geofence can be ticked only for a site with saved
    // coordinates." A site without lat/lng can never end up enforced, whether it
    // came from the assigner's tick or the site's always-enforce flag.
    $site             = $service_request->site;
    $hasCoordinates   = $site && $site->lat !== null && $site->lng !== null;
    $alwaysEnforce    = $hasCoordinates && (bool) ($site->always_enforce_geofence ?? false);
    $requestedEnforce = $hasCoordinates && (bool) ($data['enforce_geofence'] ?? false);

    $service_request->update([
        'status'                 => 'assigned',
        'assigned_tech_user_id'  => $tech->id,
        'team_lead_user_id'      => $teamLeadUserId,              // auto-set from technician's team
        'scheduled_eta_at'       => $data['arrival_time'],
        'enforce_geofence'       => $alwaysEnforce || $requestedEnforce,
        'allow_signature_waiver' => (bool) ($data['allow_signature_waiver'] ?? false),
    ]);
    \App\Models\SrStatusHistory::log($service_request, $fromStatus, 'assigned', 'user', auth()->id(), null, ['technician_id' => $tech->id, 'technician_name' => $tech->name]);
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

        $fromStatus = $service_request->status;

        $service_request->update([
            'status'              => 'cancelled',
            'cancellation_reason' => 'Rejected: ' . $data['reason'],
        ]);
        app(ContractCoverage::class)->giveBack($service_request);
         \App\Models\SrStatusHistory::log($service_request, $fromStatus, 'cancelled', 'user', auth()->id(), 'Rejected: ' . $data['reason']);
        return redirect()->route('service-requests.show', $service_request)
            ->with('success', "{$service_request->sr_number} rejected.");
    }

    public function sendForQuote(ServiceRequest $service_request)
    {
        // Blueprint 7.1: stays Pending with quote_requested_at set.
        // Finance moves it to Quoted when the quote is actually sent.
        $service_request->update([
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
            [$resp, $reso] = $service_request->fresh()->slaMinutes();
            $started = now();

            $service_request->update([
                'sla_clock_started_at'  => $started,
                'sla_response_due_at'   => $resp !== null ? $started->copy()->addMinutes($resp) : null,
                'sla_resolution_due_at' => $reso !== null ? $started->copy()->addMinutes($reso) : null,
            ]);
        }
        // Changing to any non-emergency priority — recalculate due times with the new policy
        elseif ($data['priority'] !== 'emergency' && $service_request->sla_clock_started_at) {
            [$resp, $reso] = $service_request->fresh()->slaMinutes();
            if ($resp !== null && $reso !== null) {
                $started = $service_request->sla_clock_started_at;
                $service_request->update([
                    'sla_response_due_at'   => $started->copy()->addMinutes($resp),
                    'sla_resolution_due_at' => $started->copy()->addMinutes($reso),
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

        $fromStatus = $service_request->status;


        $service_request->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $data['reason'],
        ]);
        app(ContractCoverage::class)->giveBack($service_request);   // before the first punch in the call goes back
         \App\Models\SrStatusHistory::log($service_request, $fromStatus, 'cancelled', 'user', auth()->id(), $data['reason']);
        return redirect()->route('service-requests.show', $service_request)
            ->with('success', "{$service_request->sr_number} cancelled.");
    }

    public function markReview(ServiceRequest $service_request)
    {
        abort_unless(
            $service_request->status === 'in_progress',
            422,
            "{$service_request->sr_number} can only be submitted for review once work is in progress (status: " . $service_request->statusLabel() . ')'
        );

        $service_request->update(['status' => 'pending_review']);
        \App\Models\SrStatusHistory::log($service_request, 'in_progress', 'pending_review', 'user', auth()->id()); 
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
        /**
     * AJAX — selectable assets at a site (decommissioned ones are hidden,
     * unless it is the asset already saved on this request).
     */
    public function assetsForSite(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $siteId   = (int) $request->input('site_id');
        $include  = (int) $request->input('include');

        $assets = Asset::where('tenant_id', $tenantId)
            ->where('project_site_id', $siteId)
            ->where(fn ($q) => $q->selectable()
                ->when($include, fn ($qq) => $qq->orWhere('id', $include)))
            ->orderBy('asset_code')
            ->get(['id', 'asset_code', 'name', 'service_category_id']);

        return response()->json($assets->map(fn ($a) => [
            'id'                  => $a->id,
            'label'               => $a->asset_code . ' — ' . $a->name,
            'service_category_id' => $a->service_category_id,
        ]));
    }

    /**
     * AJAX — warranty status and the last 3 requests for one asset.
     */
    public function assetSummary(Asset $asset)
    {
        $recent = ServiceRequest::where('primary_asset_id', $asset->id)
            ->orderByDesc('id')
            ->limit(3)
            ->get(['id', 'sr_number', 'title', 'status', 'created_at']);

        return response()->json([
            'id'              => $asset->id,
            'asset_code'      => $asset->asset_code,
            'name'            => $asset->name,
            'warranty_status' => $asset->warranty_status,
            'warranty_expiry' => $asset->warranty_expiry_date?->format('d M Y'),
            'recent'          => $recent->map(fn ($r) => [
                'sr_number'    => $r->sr_number,
                'title'        => $r->title,
                'status_label' => $r->statusLabel(),
                'date'         => $r->created_at?->format('d M Y'),
                'url'          => route('service-requests.show', $r),
            ]),
        ]);
    }

    public function reopen(Request $request, ServiceRequest $service_request)
{
    abort_unless(auth()->user()->canDo('sr.reopen'), 403);
    abort_unless($service_request->status === 'completed', 422, 'Only a Completed request can be reopened.');

    $data = $request->validate([
        'reason' => ['required', 'string', 'min:3', 'max:500'],
    ], [
        'reason.required' => 'Please give a reason for reopening this request.',
    ]);

    $windowDays = Tenant::current()?->setting?->reopen_window_days ?? 14;

    if ($windowDays === 0) {
        abort(422, 'This company does not allow reopening completed requests. Create a follow-up job instead.');
    }

    $deadline = $service_request->completed_at?->copy()->addHours($windowDays * 24);
    if (! $deadline || now()->greaterThan($deadline)) {
        abort(422, 'The reopen window for this request has closed. Create a follow-up job instead.');
    }

    $service_request->update([
        'status'              => 'rework',
        'reopened_count'      => $service_request->reopened_count + 1,
        'reopen_reason'       => $data['reason'],
        'reopened_at'         => now(),
        'reopened_by_user_id' => auth()->id(),
    ]);
     \App\Models\SrStatusHistory::log($service_request, 'completed', 'rework', 'user', auth()->id(), $data['reason']);
    return back()->with('success', "{$service_request->sr_number} reopened — sent back to the technician for rework.");
}
}