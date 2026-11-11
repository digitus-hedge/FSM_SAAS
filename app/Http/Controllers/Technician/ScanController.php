<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\SrStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * AST-08 — a technician raises a request from an asset QR scan (online-first).
 * Allowed only while the technician is punched in on a job (assigned to them, status in_progress;
 * a job on hold still counts), and only for an asset at that job's site.
 */
class ScanController extends Controller
{
    /** The job the technician is punched in on right now. */
    private function currentJob(): ?ServiceRequest
    {
        return ServiceRequest::where('tenant_id', auth()->user()->tenant_id)
            ->where('assigned_tech_user_id', auth()->id())
            ->where('status', 'in_progress')
            ->orderByDesc('id')
            ->first();
    }

    /** Returns [asset, job, errorMessage]. */
    private function check(?Asset $asset): array
    {
        $job = $this->currentJob();

        if (! $job) {
            return [null, null, 'Punch in on a job first, then scan the asset.'];
        }
        if (! $asset) {
            return [null, $job, 'This QR code is not an asset of your company.'];
        }
        if ($asset->status === 'decommissioned') {
            return [null, $job, 'This asset has been decommissioned, so no new request can be raised for it.'];
        }
        if ((int) $asset->project_site_id !== (int) $job->project_site_id) {
            return [null, $job, 'This asset is at a different site from the job you are punched in on.'];
        }

        return [$asset, $job, null];
    }

    public function index()
    {
        return view('technician.scan.index', ['job' => $this->currentJob()]);
    }

    /** Called by the scanner page with the hash read from the QR code. */
    public function resolve(Request $request)
    {
        $hash = trim((string) $request->input('hash'));
        $asset = $hash === '' ? null : Asset::where('tenant_id', auth()->user()->tenant_id)->where('qr_hash', $hash)->first();

        [$asset, , $error] = $this->check($asset);
        if ($error) {
            return redirect()->route('technician.scan.index')->with('warning', $error);
        }

        return redirect()->route('technician.scan.create', $asset);
    }

    public function create(Asset $asset)
    {
        [$asset, $job, $error] = $this->check($asset->tenant_id === auth()->user()->tenant_id ? $asset : null);
        if ($error) {
            return redirect()->route('technician.scan.index')->with('warning', $error);
        }

        // One-time token so a double tap cannot create two requests.
        $token = (string) Str::uuid();
        session(['scan_form_token' => $token]);

        $asset->loadMissing(['client', 'site']);

        return view('technician.scan.create', [
            'asset'      => $asset,
            'job'        => $job,
            'token'      => $token,
            'categories' => ServiceCategory::where('tenant_id', auth()->user()->tenant_id)->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $tenantId = auth()->user()->tenant_id;

        [$asset, $job, $error] = $this->check($asset->tenant_id === $tenantId ? $asset : null);
        if ($error) {
            return redirect()->route('technician.scan.index')->with('warning', $error);
        }

        $data = $request->validate([
            'token'               => ['required', 'string'],
            'service_category_id' => [
                'required', 'integer',
                Rule::exists('service_categories', 'id')->where('tenant_id', $tenantId)->where('is_active', 1)->whereNull('deleted_at'),
            ],
            'priority'            => ['required', 'in:' . implode(',', array_keys(ServiceRequest::PRIORITIES))],
            'title'               => ['required', 'string', 'max:190'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'attachments'         => ['nullable', 'array'],
            'attachments.*'       => ['file', 'max:10240'],
        ]);

        // Double-tap guard: the token is used once.
        if (! hash_equals((string) session('scan_form_token'), $data['token'])) {
            return redirect()->route('technician.jobs.index')->with('warning', 'That request was already sent.');
        }
        session()->forget('scan_form_token');

        $cat = ServiceCategory::where('tenant_id', $tenantId)->findOrFail($data['service_category_id']);

        $proofSnapshot = [
            'require_before_photo' => (bool) $cat->require_before_photo,
            'require_after_photo'  => (bool) $cat->require_after_photo,
            'require_signature'    => (bool) $cat->require_signature,
            'require_checklist'    => (bool) $cat->require_checklist,
            'snapshot_at'          => now()->toIso8601String(),
            'category_code'        => $cat->code ?? null,
        ];

        // The technician's own team lead gets it; a team lead keeps it on their own queue.
        $me = auth()->user();
        $teamLeadId = $me->role?->code === 'team_lead' ? $me->id : $me->team_lead_id;

        $sr = ServiceRequest::create([
            'tenant_id'              => $tenantId,
            'sr_number'              => ServiceRequest::nextSrNumber($tenantId),
            'client_id'              => $asset->client_id,
            'project_site_id'        => $asset->project_site_id,
            'service_category_id'    => $data['service_category_id'],
            'primary_asset_id'       => $asset->id,
            'priority'               => $data['priority'],
            'title'                  => $data['title'],
            'description'            => $data['description'] ?? null,
            'service_mode'           => 'on_site',
            'source'                 => 'qr_scan',
            'coverage_type'          => 'out_of_coverage',
            'status'                 => 'pending',
            'billing_status'         => 'not_applicable',
            'invoice_mandatory'      => (bool) ($cat->invoice_mandatory ?? false),
            'enforce_geofence'       => false,
            'allow_signature_waiver' => false,
            'created_by_user_id'     => $me->id,
            'proof_rules_snapshot'   => $proofSnapshot,
            'team_lead_user_id'      => $teamLeadId,
        ]);

        $policy  = $sr->resolveSlaPolicy();
        $started = now();
        $sr->update([
            'sla_clock_started_at'  => $started,
            'sla_response_due_at'   => $policy ? $started->copy()->addMinutes((int) $policy->response_minutes) : null,
            'sla_resolution_due_at' => $policy ? $started->copy()->addMinutes((int) $policy->resolution_minutes) : null,
        ]);

        SrStatusHistory::log($sr, null, 'pending', 'user', $me->id);

        foreach ($request->file('attachments', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store("sr/{$sr->id}/attachments", 'public');
            Attachment::create([
                'tenant_id'           => $tenantId,
                'attachable_type'     => ServiceRequest::class,
                'attachable_id'       => $sr->id,
                'kind'                => 'sr_attachment',
                'storage_path'        => $path,
                'original_name'       => $file->getClientOriginalName(),
                'mime_type'           => $file->getMimeType(),
                'size_bytes'          => $file->getSize(),
                'captured_at'         => now(),
                'uploaded_by_user_id' => $me->id,
                'client_event_uuid'   => (string) Str::uuid(),
            ]);
        }

        return redirect()->route('technician.jobs.show', $job)
            ->with('success', "Request {$sr->sr_number} raised for {$asset->asset_code}.");
    }
}