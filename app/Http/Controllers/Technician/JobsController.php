<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\ExpenseCategory;
use App\Models\GeofenceOverrideRequest;
use App\Models\HoldReason;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestExpense;
use App\Models\ServiceRequestPunch;
use App\Models\SrNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JobsController extends Controller
{
    private function authorizeTech(ServiceRequest $sr): void
    {
        abort_unless((int) $sr->assigned_tech_user_id === (int) auth()->id(), 403);
    }

    private function alreadyApplied(int $srId, string $punchType, string $uuid): bool
    {
        return ServiceRequestPunch::where('service_request_id', $srId)
            ->where('punch_type', $punchType)
            ->where('client_event_uuid', $uuid)
            ->exists();
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth * $c;
    }

    private function evaluateGeofence(ServiceRequest $sr, array $data): array
    {
        $result = ['within' => true, 'block' => false, 'distance' => null, 'gps_status' => 'ok', 'message' => null, 'override' => null];

        if (empty($data['gps_lat']) || empty($data['gps_lng'])) {
            $result['gps_status'] = 'denied';
            if ($sr->enforce_geofence) {
                $result['block'] = true;
                $result['message'] = 'Location is required for this job. Enable location and try again.';
            }
            return $result;
        }

        if (! empty($data['gps_accuracy']) && $data['gps_accuracy'] > 150) {
            $result['gps_status'] = 'low_accuracy';
            $result['block'] = true;
            $result['message'] = 'GPS signal is too weak (accuracy over 150m). Move to an open area and try again.';
            return $result;
        }

        if (! $sr->enforce_geofence) {
            return $result;
        }

        $site = $sr->site;
        if (! $site || ! $site->lat || ! $site->lng) {
            return $result;
        }

        $distance = $this->haversineMeters((float) $data['gps_lat'], (float) $data['gps_lng'], (float) $site->lat, (float) $site->lng);
        $result['distance'] = (int) round($distance);
        $radius = $site->geofence_radius_meters ?? 150;

        if ($distance <= $radius) {
            return $result;
        }

        $result['within'] = false;

        $override = GeofenceOverrideRequest::where('service_request_id', $sr->id)
            ->where('technician_user_id', auth()->id())
            ->where('status', 'approved')
            ->whereNull('used_punch_id')
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->latest('id')->first();

        if ($override) {
            $result['override'] = $override;
            return $result;
        }

        $result['block'] = true;
        $result['message'] = "You're {$result['distance']}m from the site (allowed: {$radius}m). Request a geofence override to continue.";
        return $result;
    }

        public function index()
    {
        $me = auth()->id();
        $now = now();
        $jobs = ServiceRequest::with(['client', 'site'])
            ->where('assigned_tech_user_id', $me)
            ->whereIn('status', ['assigned', 'in_progress', 'rework', 'pending_review', 'cancelled'])
            ->orderBy('scheduled_eta_at')
            ->get();

        $nowJobs = $jobs->where('status', 'in_progress')->values();

        $soon = $jobs->filter(function ($j) use ($now) {
            return in_array($j->status, ['assigned', 'rework'])
                && $j->scheduled_eta_at
                && $j->scheduled_eta_at->greaterThanOrEqualTo($now)
                && ! $j->scheduled_eta_at->isSameDay($now)
                && $j->scheduled_eta_at->lessThan($now->copy()->addDays(3));
        })->sortBy('scheduled_eta_at')->values();

        $soonIds = $soon->pluck('id');

        $todayArr = $jobs->filter(function ($j) use ($now, $soonIds) {
            if ($soonIds->contains($j->id) || $j->status === 'in_progress') return false;
            $sameDay = $j->scheduled_eta_at && $j->scheduled_eta_at->isSameDay($now);
            $overdue = in_array($j->status, ['assigned', 'rework']) && $j->scheduled_eta_at && $j->scheduled_eta_at->lessThan($now);
            return (in_array($j->status, ['assigned', 'rework', 'cancelled']) && $sameDay) || $overdue;
        })->all();

        usort($todayArr, function ($a, $b) {
            $pa = $a->priority === 'emergency' ? 0 : 1;
            $pb = $b->priority === 'emergency' ? 0 : 1;
            if ($pa !== $pb) return $pa <=> $pb;
            return ($a->scheduled_eta_at ?? now()) <=> ($b->scheduled_eta_at ?? now());
        });
        $today = collect($todayArr);

        $review = $jobs->filter(function ($j) use ($now) {
            return $j->status === 'pending_review' && $j->last_punch_out_at && $j->last_punch_out_at->greaterThan($now->copy()->subDay());
        })->values();

        return view('technician.jobs.index', compact('nowJobs', 'today', 'soon', 'review'));
    }

    public function all(Request $request)
    {
        $tab = $request->get('tab', 'active');
        $search = $request->get('q');

        $query = ServiceRequest::with(['client', 'site'])
            ->where('assigned_tech_user_id', auth()->id())
            ->search($search);

        $query = match ($tab) {
            'review' => $query->whereIn('status', ['pending_review', 'rework']),
            'done'   => $query->where('status', 'completed'),
            default  => $query->whereIn('status', ['assigned', 'in_progress']),
        };

        $jobs = $query->orderByDesc('scheduled_eta_at')->paginate(20)->withQueryString();

        return view('technician.jobs.all', compact('jobs', 'tab', 'search'));
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $serviceRequest->load(['client', 'site', 'holdReason']);
        $holdReasons = HoldReason::active()->orderBy('label')->get();
        $pendingOverride = GeofenceOverrideRequest::where('service_request_id', $serviceRequest->id)
            ->where('technician_user_id', auth()->id())
            ->latest('id')->first();

        return view('technician.jobs.show', compact('serviceRequest', 'holdReasons', 'pendingOverride'));
    }

    public function accept(ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        if (! $serviceRequest->tech_accepted_at) {
            $serviceRequest->update(['tech_accepted_at' => now()]);
        }
        return back()->with('success', 'Job accepted.');
    }

    public function changeTime(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $data = $request->validate([
            'new_eta_at' => ['required', 'date'],
            'reason'     => ['nullable', 'string', 'max:255'],
        ]);

        DB::table('sr_reschedules')->insert([
            'tenant_id'              => $serviceRequest->tenant_id,
            'service_request_id'     => $serviceRequest->id,
            'previous_eta_at'        => $serviceRequest->scheduled_eta_at,
            'new_eta_at'             => $data['new_eta_at'],
            'reason'                 => $data['reason'] ?? null,
            'source'                 => 'technician_request',
            'rescheduled_by_user_id' => auth()->id(),
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $serviceRequest->update(['scheduled_eta_at' => $data['new_eta_at']]);

        return back()->with('success', 'Time updated.');
    }

    public function punchIn(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);

        $data = $request->validate([
            'client_event_uuid' => ['required', 'uuid'],
            'gps_lat'            => ['nullable', 'numeric'],
            'gps_lng'            => ['nullable', 'numeric'],
            'gps_accuracy'       => ['nullable', 'numeric'],
        ]);

        if ($this->alreadyApplied($serviceRequest->id, 'punch_in', $data['client_event_uuid'])) {
            return back()->with('success', 'Already punched in.');
        }

        if ($serviceRequest->status === 'in_progress' && ! $serviceRequest->is_on_hold) {
            return back()->withErrors(['identifier' => 'Already punched in.']);
        }

        $geo = $this->evaluateGeofence($serviceRequest, $data);
        if ($geo['block']) {
            return back()->withErrors(['identifier' => $geo['message']]);
        }

        $punch = ServiceRequestPunch::create([
            'tenant_id'                     => $serviceRequest->tenant_id,
            'service_request_id'           => $serviceRequest->id,
            'technician_user_id'            => auth()->id(),
            'client_event_uuid'             => $data['client_event_uuid'],
            'punch_type'                    => 'punch_in',
            'recorded_at_device'            => now(),
            'synced_to_server_at'           => now(),
            'is_offline_sync'               => false,
            'clock_drift_seconds'           => 0,
            'gps_lat'                       => $data['gps_lat'] ?? null,
            'gps_lng'                       => $data['gps_lng'] ?? null,
            'gps_accuracy_meters'           => $data['gps_accuracy'] ?? null,
            'gps_status'                    => $geo['gps_status'],
            'site_distance_meters'          => $geo['distance'],
            'is_within_geofence'            => $geo['within'],
            'geofence_override_reason'      => $geo['override']?->reason,
            'geofence_override_by_user_id'  => $geo['override']?->decided_by_user_id,
            'sync_status'                   => 'synced',
            'device_info'                   => substr((string) $request->userAgent(), 0, 255),
        ]);

        if ($geo['override']) {
            $geo['override']->update(['used_punch_id' => $punch->id, 'status' => 'used']);
        }

        $serviceRequest->update([
            'status'            => 'in_progress',
            'first_punch_in_at' => $serviceRequest->first_punch_in_at ?? now(),
            'is_on_hold'         => false,
        ]);

        return back()->with('success', 'Punched in.');
    }

    public function hold(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);

        $data = $request->validate([
            'client_event_uuid' => ['required', 'uuid'],
            'hold_reason_id'     => ['required', 'exists:hold_reasons,id'],
            'note'               => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->alreadyApplied($serviceRequest->id, 'hold_start', $data['client_event_uuid'])) {
            return back()->with('success', 'Already on hold.');
        }

        $reason = HoldReason::findOrFail($data['hold_reason_id']);
        if ($reason->requires_note && blank($data['note'] ?? null)) {
            return back()->withErrors(['identifier' => 'This hold reason requires a note.']);
        }

        if ($serviceRequest->status !== 'in_progress' || $serviceRequest->is_on_hold) {
            return back()->withErrors(['identifier' => 'Job is not in progress.']);
        }

        ServiceRequestPunch::create([
            'tenant_id'            => $serviceRequest->tenant_id,
            'service_request_id'  => $serviceRequest->id,
            'technician_user_id'  => auth()->id(),
            'client_event_uuid'   => $data['client_event_uuid'],
            'punch_type'           => 'hold_start',
            'hold_reason_id'       => $reason->id,
            'recorded_at_device'   => now(),
            'synced_to_server_at'  => now(),
            'is_offline_sync'      => false,
            'sync_status'          => 'synced',
            'conflict_note'        => $data['note'] ?? null,
            'device_info'          => substr((string) $request->userAgent(), 0, 255),
        ]);

        $serviceRequest->update([
            'is_on_hold'              => true,
            'current_hold_reason_id'  => $reason->id,
            'hold_started_at'         => now(),
        ]);

        return back()->with('success', 'Job put on hold.');
    }

    public function resume(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $data = $request->validate(['client_event_uuid' => ['required', 'uuid']]);

        if ($this->alreadyApplied($serviceRequest->id, 'hold_resume', $data['client_event_uuid'])) {
            return back()->with('success', 'Already resumed.');
        }

        if (! $serviceRequest->is_on_hold) {
            return back()->withErrors(['identifier' => 'Job is not on hold.']);
        }

        $holdMinutes = $serviceRequest->hold_started_at ? $serviceRequest->hold_started_at->diffInMinutes(now()) : 0;

        ServiceRequestPunch::create([
            'tenant_id'            => $serviceRequest->tenant_id,
            'service_request_id'  => $serviceRequest->id,
            'technician_user_id'  => auth()->id(),
            'client_event_uuid'   => $data['client_event_uuid'],
            'punch_type'           => 'hold_resume',
            'recorded_at_device'   => now(),
            'synced_to_server_at'  => now(),
            'is_offline_sync'      => false,
            'sync_status'          => 'synced',
        ]);

        $serviceRequest->update([
            'is_on_hold'              => false,
            'current_hold_reason_id'  => null,
            'hold_started_at'         => null,
            'total_hold_minutes'      => $serviceRequest->total_hold_minutes + $holdMinutes,
        ]);

        return back()->with('success', 'Resumed.');
    }

    public function punchOut(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);

        $data = $request->validate([
            'client_event_uuid' => ['required', 'uuid'],
            'gps_lat'            => ['nullable', 'numeric'],
            'gps_lng'            => ['nullable', 'numeric'],
            'gps_accuracy'       => ['nullable', 'numeric'],
        ]);

        if ($this->alreadyApplied($serviceRequest->id, 'punch_out', $data['client_event_uuid'])) {
            return redirect()->route('technician.jobs.index')->with('success', 'Already punched out.');
        }

        if ($serviceRequest->status !== 'in_progress') {
            return back()->withErrors(['identifier' => 'Job is not in progress.']);
        }
        if ($serviceRequest->is_on_hold) {
            return back()->withErrors(['identifier' => 'Resume the job before punching out.']);
        }

        $geo = $this->evaluateGeofence($serviceRequest, $data);

        ServiceRequestPunch::create([
            'tenant_id'              => $serviceRequest->tenant_id,
            'service_request_id'    => $serviceRequest->id,
            'technician_user_id'    => auth()->id(),
            'client_event_uuid'     => $data['client_event_uuid'],
            'punch_type'             => 'punch_out',
            'recorded_at_device'     => now(),
            'synced_to_server_at'    => now(),
            'is_offline_sync'        => false,
            'gps_lat'                => $data['gps_lat'] ?? null,
            'gps_lng'                => $data['gps_lng'] ?? null,
            'gps_accuracy_meters'    => $data['gps_accuracy'] ?? null,
            'gps_status'             => $geo['gps_status'],
            'site_distance_meters'   => $geo['distance'],
            'is_within_geofence'     => $geo['within'],
            'sync_status'            => 'synced',
            'device_info'            => substr((string) $request->userAgent(), 0, 255),
        ]);

        $workMinutes = $serviceRequest->first_punch_in_at
            ? $serviceRequest->first_punch_in_at->diffInMinutes(now()) - $serviceRequest->total_hold_minutes
            : 0;

        $serviceRequest->update([
            'status'             => 'pending_review',
            'last_punch_out_at'  => now(),
            'total_work_minutes' => max(0, $workMinutes),
        ]);

        return redirect()->route('technician.jobs.show', $serviceRequest)->with('success', 'Punched out. Job sent for review.');
    }

    public function requestGeofenceOverride(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);

        $data = $request->validate([
            'client_event_uuid' => ['required', 'uuid'],
            'reason'              => ['required', 'string', 'max:255'],
            'gps_lat'             => ['nullable', 'numeric'],
            'gps_lng'             => ['nullable', 'numeric'],
            'gps_accuracy'        => ['nullable', 'numeric'],
            'photo'               => ['nullable', 'image', 'max:10240'],
        ]);

        $photoAttachmentId = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store("attachments/sr-{$serviceRequest->id}", 'public');
            $photoAttachmentId = Attachment::create([
                'tenant_id'            => $serviceRequest->tenant_id,
                'attachable_type'      => ServiceRequest::class,
                'attachable_id'        => $serviceRequest->id,
                'kind'                  => 'sr_attachment',
                'storage_path'          => $path,
                'original_name'         => $request->file('photo')->getClientOriginalName(),
                'mime_type'             => $request->file('photo')->getMimeType(),
                'size_bytes'            => $request->file('photo')->getSize(),
                'captured_at'           => now(),
                'gps_lat'               => $data['gps_lat'] ?? null,
                'gps_lng'               => $data['gps_lng'] ?? null,
                'uploaded_by_user_id'   => auth()->id(),
                'client_event_uuid'     => $data['client_event_uuid'],
            ])->id;
        }

        $distance = null;
        if (! empty($data['gps_lat']) && ! empty($data['gps_lng']) && $serviceRequest->site?->lat && $serviceRequest->site?->lng) {
            $distance = (int) round($this->haversineMeters(
                (float) $data['gps_lat'], (float) $data['gps_lng'],
                (float) $serviceRequest->site->lat, (float) $serviceRequest->site->lng
            ));
        }

        GeofenceOverrideRequest::create([
            'tenant_id'              => $serviceRequest->tenant_id,
            'service_request_id'    => $serviceRequest->id,
            'technician_user_id'    => auth()->id(),
            'client_event_uuid'     => $data['client_event_uuid'],
            'reason'                 => $data['reason'],
            'photo_attachment_id'    => $photoAttachmentId,
            'requested_at_device'    => now(),
            'gps_lat'                => $data['gps_lat'] ?? null,
            'gps_lng'                => $data['gps_lng'] ?? null,
            'gps_accuracy_meters'    => $data['gps_accuracy'] ?? null,
            'site_distance_meters'   => $distance,
            'status'                 => 'pending',
            'valid_until'            => now()->addHours(2),
        ]);

        return back()->with('success', 'Override requested. Notification to your manager isn\'t wired up yet — ask them to check it on their end.');
    }

    public function photos(ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $photos = Attachment::where('attachable_type', ServiceRequest::class)
            ->where('attachable_id', $serviceRequest->id)
            ->whereIn('kind', ['before_photo', 'after_photo'])
            ->orderByDesc('id')->get();

        return view('technician.jobs.photos', compact('serviceRequest', 'photos'));
    }

    public function storePhoto(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $data = $request->validate([
            'photo'    => ['required', 'image', 'max:10240'],
            'category' => ['required', 'in:before_photo,after_photo'],
        ]);

        $path = $request->file('photo')->store("attachments/sr-{$serviceRequest->id}", 'public');

        Attachment::create([
            'tenant_id'           => $serviceRequest->tenant_id,
            'attachable_type'     => ServiceRequest::class,
            'attachable_id'       => $serviceRequest->id,
            'kind'                 => $data['category'],
            'storage_path'         => $path,
            'original_name'        => $request->file('photo')->getClientOriginalName(),
            'mime_type'             => $request->file('photo')->getMimeType(),
            'size_bytes'            => $request->file('photo')->getSize(),
            'captured_at'           => now(),
            'uploaded_by_user_id'   => auth()->id(),
            'client_event_uuid'     => (string) Str::uuid(),
        ]);

        return back()->with('success', 'Photo added.');
    }

    public function deletePhoto(ServiceRequest $serviceRequest, Attachment $attachment)
    {
        $this->authorizeTech($serviceRequest);
        abort_unless($attachment->attachable_type === ServiceRequest::class && $attachment->attachable_id === $serviceRequest->id, 404);
        $attachment->deleteWithFile();

        return back()->with('success', 'Photo removed.');
    }

    public function notes(ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $notes = SrNote::where('service_request_id', $serviceRequest->id)->with('user')->orderByDesc('id')->get();

        return view('technician.jobs.notes', compact('serviceRequest', 'notes'));
    }

    public function storeNote(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $data = $request->validate([
            'body'       => ['required', 'string', 'max:2000'],
            'visibility' => ['required', 'in:internal,client_visible'],
        ]);

        SrNote::create([
            'tenant_id'           => $serviceRequest->tenant_id,
            'service_request_id' => $serviceRequest->id,
            'user_id'              => auth()->id(),
            'visibility'            => $data['visibility'],
            'source'                => 'user',
            'body'                  => $data['body'],
        ]);

        return back()->with('success', 'Note added.');
    }

    public function expenses(ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $expenses = ServiceRequestExpense::where('service_request_id', $serviceRequest->id)->with('category')->orderByDesc('id')->get();

        return view('technician.jobs.expenses', compact('serviceRequest', 'expenses'));
    }

    public function createExpense(ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $categories = ExpenseCategory::active()->orderBy('name')->get();

        return view('technician.jobs.expense_create', compact('serviceRequest', 'categories'));
    }

    public function storeExpense(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $category = ExpenseCategory::findOrFail($request->input('expense_category_id'));

        $data = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'description'           => ['required', 'string', 'max:255'],
            'quantity'               => ['required', 'numeric', 'min:0.01'],
            'unit_rate'              => ['nullable', 'numeric', 'min:0'],
            'receipt'                => [$category->requires_receipt ? 'required' : 'nullable', 'image', 'max:10240'],
        ]);

        $unitRate = $data['unit_rate'] ?? $category->default_unit_rate ?? 0;
        $total = round($data['quantity'] * $unitRate, 2);

        $receiptId = null;
        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store("attachments/sr-{$serviceRequest->id}", 'public');
            $receiptId = Attachment::create([
                'tenant_id'            => $serviceRequest->tenant_id,
                'attachable_type'      => ServiceRequest::class,
                'attachable_id'        => $serviceRequest->id,
                'kind'                  => 'receipt',
                'storage_path'          => $path,
                'original_name'         => $request->file('receipt')->getClientOriginalName(),
                'mime_type'             => $request->file('receipt')->getMimeType(),
                'size_bytes'            => $request->file('receipt')->getSize(),
                'captured_at'           => now(),
                'uploaded_by_user_id'   => auth()->id(),
                'client_event_uuid'     => (string) Str::uuid(),
            ])->id;
        }

        ServiceRequestExpense::create([
            'tenant_id'              => $serviceRequest->tenant_id,
            'service_request_id'    => $serviceRequest->id,
            'logged_by_user_id'      => auth()->id(),
            'client_event_uuid'      => (string) Str::uuid(),
            'expense_category_id'    => $category->id,
            'description'             => $data['description'],
            'quantity'                => $data['quantity'],
            'unit_rate'               => $unitRate,
            'total_amount'            => $total,
            'is_billable_to_client'   => $category->is_billable_default,
            'receipt_attachment_id'   => $receiptId,
            'approval_status'         => 'pending',
            'created_at'              => now(),
        ]);

        return redirect()->route('technician.jobs.expenses', $serviceRequest)->with('success', 'Expense logged.');
    }

    public function signature(ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        return view('technician.jobs.signature', compact('serviceRequest'));
    }

    public function storeSignature(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeTech($serviceRequest);
        $data = $request->validate([
            'signature_png' => ['required', 'string'],
            'signer_name'    => ['required', 'string', 'max:150'],
            'signer_phone'   => ['nullable', 'string', 'max:40'],
            'gps_lat'        => ['nullable', 'numeric'],
            'gps_lng'        => ['nullable', 'numeric'],
        ]);

        $raw = $data['signature_png'];
        if (str_contains($raw, ',')) {
            $raw = substr($raw, strpos($raw, ',') + 1);
        }
        $binary = base64_decode($raw);
        if ($binary === false) {
            return back()->withErrors(['identifier' => 'Could not read the signature. Please try again.']);
        }

        $path = "attachments/sr-{$serviceRequest->id}/signature-" . time() . '.png';
        Storage::disk('public')->put($path, $binary);

        $attachment = Attachment::create([
            'tenant_id'           => $serviceRequest->tenant_id,
            'attachable_type'     => ServiceRequest::class,
            'attachable_id'       => $serviceRequest->id,
            'kind'                 => 'signature',
            'storage_path'         => $path,
            'original_name'        => 'signature.png',
            'mime_type'             => 'image/png',
            'size_bytes'            => strlen($binary),
            'captured_at'           => now(),
            'gps_lat'               => $data['gps_lat'] ?? null,
            'gps_lng'               => $data['gps_lng'] ?? null,
            'uploaded_by_user_id'   => auth()->id(),
            'client_event_uuid'     => (string) Str::uuid(),
        ]);

        $serviceRequest->update([
            'customer_signer_name'              => $data['signer_name'],
            'customer_signer_phone'             => $data['signer_phone'] ?? null,
            'customer_signature_attachment_id'  => $attachment->id,
            'signature_gps_lat'                  => $data['gps_lat'] ?? null,
            'signature_gps_lng'                  => $data['gps_lng'] ?? null,
            'signed_at'                          => now(),
        ]);

        return redirect()->route('technician.jobs.show', $serviceRequest)->with('success', 'Signature captured.');
    }

    public function liveCounts()
    {
        $me = auth()->id();
        return response()->json([
            'today'  => ServiceRequest::where('assigned_tech_user_id', $me)->whereDate('scheduled_eta_at', now()->toDateString())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'active' => ServiceRequest::where('assigned_tech_user_id', $me)->whereIn('status', ['assigned', 'in_progress'])->count(),
            'review' => ServiceRequest::where('assigned_tech_user_id', $me)->whereIn('status', ['pending_review', 'rework'])->count(),
        ]);
    }

    public function profile()
    {
        return view('technician.jobs.profile', ['user' => auth()->user()]);
    }

    public function notifications()
    {
        return view('technician.jobs.notifications');
    }
}