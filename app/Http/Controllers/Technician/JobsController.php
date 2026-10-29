<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\Attachment;
use Illuminate\Http\Request;

class JobsController extends Controller
{
    /* ===================== LISTS ===================== */

    public function index(Request $request)
    {
        $u = $request->user();
        $tab = $request->input('tab', 'today');

        $base = fn() => ServiceRequest::query()->where('assigned_tech_user_id', $u->id);

        $jobs = $base()
            ->with(['client:id,name', 'site:id,name,address', 'category:id,name'])
            ->when($tab === 'today', fn($q) =>
            $q->whereDate('scheduled_eta_at', today())
                ->whereNotIn('status', ['completed', 'cancelled']))
            ->when($tab === 'upcoming', fn($q) =>
            $q->whereDate('scheduled_eta_at', '>', today())
                ->whereNotIn('status', ['completed', 'cancelled']))
            ->when($tab === 'done', fn($q) =>
            $q->where('status', 'completed')->latest('completed_at'))
            ->orderByRaw("FIELD(priority,'emergency','critical','high','medium','low')")
            ->orderBy('scheduled_eta_at')
            ->get();

        $counts = [
            'today'    => $base()->whereDate('scheduled_eta_at', today())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'upcoming' => $base()->whereDate('scheduled_eta_at', '>', today())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'done'     => $base()->where('status', 'completed')->count(),
        ];

        return view('technician.jobs.index', compact('jobs', 'tab', 'counts'));
    }

    public function all(Request $request)
    {
        $u      = $request->user();
        $tab    = $request->input('tab', 'active');
        $search = $request->input('q');

        $base = fn() => ServiceRequest::query()
            ->where('assigned_tech_user_id', $u->id)
            ->with(['client:id,name', 'site:id,name']);

        $jobs = $base()
            ->when($search, fn($q) => $q->where(function ($w) use ($search) {
                $w->where('sr_number', 'like', "%$search%")
                    ->orWhere('title', 'like', "%$search%")
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', "%$search%"))
                    ->orWhereHas('site',   fn($s) => $s->where('name', 'like', "%$search%"));
            }))
            ->when($tab === 'active', fn($q) => $q->whereIn('status', ['assigned', 'in_progress']))
            ->when($tab === 'review', fn($q) => $q->where('status', 'pending_review'))
            ->when($tab === 'done',   fn($q) => $q->whereIn('status', ['completed', 'cancelled'])
                ->where('updated_at', '>=', now()->subDays(14)))
            ->orderBy('scheduled_eta_at')
            ->get();

        $counts = [
            'active' => $base()->whereIn('status', ['assigned', 'in_progress'])->count(),
            'review' => $base()->where('status', 'pending_review')->count(),
            'done'   => $base()->whereIn('status', ['completed', 'cancelled'])
                ->where('updated_at', '>=', now()->subDays(14))->count(),
        ];

        return view('technician.jobs.all', compact('jobs', 'tab', 'counts', 'search'));
    }

    /* ===================== DETAIL + STATE CHANGES ===================== */

    // public function show(ServiceRequest $job)
    // {
    //     abort_unless($job->assigned_tech_user_id === auth()->id(), 403);
    //     $job->load(['client.contacts', 'site', 'category', 'holdReason']); // + currentHoldReason


    //     $srType = \App\Models\ServiceRequest::class;
    //     $counts = [
    //         'before'   => \App\Models\Attachment::where('attachable_type', $srType)->where('attachable_id', $job->id)->where('kind', 'before_photo')->count(),
    //         'after'    => \App\Models\Attachment::where('attachable_type', $srType)->where('attachable_id', $job->id)->where('kind', 'after_photo')->count(),
    //         'notes'    => \App\Models\SrNote::where('service_request_id', $job->id)->count(),
    //         'expenses' => \App\Models\ServiceRequestExpense::where('service_request_id', $job->id)->count(),
    //     ];

    //     return view('technician.jobs.show', compact('job', 'counts'));
    // }


    public function show(ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);
        $job->load(['client.contacts', 'site', 'category', 'holdReason']);

        $srType = \App\Models\ServiceRequest::class;

        $counts = [
            'before'   => \App\Models\Attachment::where('attachable_type', $srType)->where('attachable_id', $job->id)->where('kind', 'before_photo')->count(),
            'after'    => \App\Models\Attachment::where('attachable_type', $srType)->where('attachable_id', $job->id)->where('kind', 'after_photo')->count(),
            'notes'    => \App\Models\SrNote::where('service_request_id', $job->id)->count(),
            'expenses' => \App\Models\ServiceRequestExpense::where('service_request_id', $job->id)->count(),
        ];

        // Live proof status — what's actually uploaded in the DB right now
        $proofDone = [
            'before_photo' => $counts['before'] > 0,
            'after_photo'  => $counts['after']  > 0,
            'signature'    => (bool) $job->signed_at,
        ];

        return view('technician.jobs.show', compact('job', 'counts', 'proofDone'));
    }

    public function deletePhoto(ServiceRequest $job, \App\Models\Attachment $photo)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);
        abort_unless($photo->attachable_type === \App\Models\ServiceRequest::class && $photo->attachable_id === $job->id, 404);

        if ($photo->storage_path && \Storage::disk('public')->exists($photo->storage_path)) {
            \Storage::disk('public')->delete($photo->storage_path);
        }
        $photo->delete();

        $kind = $photo->kind === 'before_photo' ? 'before' : 'after';
        return redirect()->route('technician.jobs.photos', ['job' => $job, 'kind' => $kind])
            ->with('success', 'Photo removed.');
    }

    public function replacePhoto(Request $request, ServiceRequest $job, \App\Models\Attachment $photo)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);
        abort_unless($photo->attachable_type === \App\Models\ServiceRequest::class
            && $photo->attachable_id === $job->id, 404);

        $data = $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp,heic|max:12288',
        ]);
        $file = $data['photo'];

        // Delete the OLD file on disk
        if ($photo->storage_path && \Storage::disk('public')->exists($photo->storage_path)) {
            \Storage::disk('public')->delete($photo->storage_path);
        }

        // Save the NEW file (keeps same attachment row, so order is preserved)
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("sr/{$job->id}/photos", $fileName, 'public');

        $photo->update([
            'storage_path'  => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size_bytes'    => $file->getSize(),
            'sha256'        => hash_file('sha256', $file->getRealPath()),
            'captured_at'   => now(),
        ]);

        $kind = $photo->kind === 'before_photo' ? 'before' : 'after';
        return redirect()->route('technician.jobs.photos', ['job' => $job, 'kind' => $kind])
            ->with('success', 'Photo updated.');
    }


    public function accept(Request $request, ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $job->update(['tech_accepted_at' => now()]);

        \App\Models\SrEvent::create([
            'tenant_id'          => auth()->user()->tenant_id,
            'service_request_id' => $job->id,
            'user_id'            => auth()->id(),
            'type'               => 'accepted',
            'occurred_at'        => now(),
        ]);

        return redirect()->route('technician.jobs.show', $job)
            ->with('success', 'Job accepted.');
    }

    public function changeTime(Request $request, ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $data = $request->validate([
            'new_eta' => 'required|date',
            'reason'  => 'nullable|string|max:500',
        ]);

        $job->update(['scheduled_eta_at' => $data['new_eta']]);

        return redirect()->route('technician.jobs.show', $job)
            ->with('success', 'Arrival time updated.');
    }

    public function punchIn(Request $request, ServiceRequest $job)
{
    abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

    $data = $this->normalizeFieldEventInput($request);
    $geo  = $this->evaluateGeofence($job, $data);

    if ($this->alreadyApplied($data['client_event_uuid'])) {
        return redirect()->route('technician.jobs.punched-in', $job);
    }

    $usableOverride = $this->findUsableOverride($job);

    if ($job->enforce_geofence && $geo['within'] === false && ! $usableOverride) {
        $this->logFieldEvent($job, 'punch_in', $data, 'conflict_rejected', 'outside_geofence');
        return redirect()->route('technician.jobs.show', $job)
            ->with('geofence_blocked', [
                'distance' => $geo['distance'],
                'radius'   => $geo['radius'],
            ]);
    }

    $punch = $this->writePunch($job, 'punch_in', $data, $geo);

    if ($usableOverride) {
        $usableOverride->update(['used_punch_id' => $punch->id]);
        $punch->update([
            'geofence_override_reason'     => $usableOverride->reason,
            'geofence_override_by_user_id' => $usableOverride->decided_by_user_id,
        ]);
    }

    $job->update([
        'status'            => 'in_progress',
        'first_punch_in_at' => $job->first_punch_in_at ?? now(),
        'tech_accepted_at'  => $job->tech_accepted_at  ?? now(),
    ]);

    $this->logFieldEvent($job, 'punch_in', $data, 'applied');

    return redirect()->route('technician.jobs.punched-in', $job);
}

    public function punchedIn(ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);
        return view('technician.jobs.punched_in', ['job' => $job]);
    }

    public function punchOut(Request $request, ServiceRequest $job)
{
    abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

    $data = $this->normalizeFieldEventInput($request);
    $geo  = $this->evaluateGeofence($job, $data);

    $srType = ServiceRequest::class;

    $hasBefore = Attachment::where('attachable_type', $srType)->where('attachable_id', $job->id)->where('kind', 'before_photo')->exists();
    $hasAfter  = Attachment::where('attachable_type', $srType)->where('attachable_id', $job->id)->where('kind', 'after_photo')->exists();
    $hasSig    = (bool) $job->signed_at;

    if (! $hasBefore || ! $hasAfter || ! $hasSig) {
        $missing = [];
        if (! $hasBefore) $missing[] = 'Before photo';
        if (! $hasAfter)  $missing[] = 'After photo';
        if (! $hasSig)    $missing[] = 'Signature';
        return redirect()->route('technician.jobs.punch-out-review', $job)
            ->with('error', 'Still missing: ' . implode(', ', $missing) . '.');
    }

    if (! $this->alreadyApplied($data['client_event_uuid'])) {
        $this->writePunch($job, 'punch_out', $data, $geo);
        $this->logFieldEvent($job, 'punch_out', $data, 'applied');
    }

    $job->update(['status' => 'pending_review', 'last_punch_out_at' => now()]);

    \App\Models\SrEvent::create([
        'tenant_id' => auth()->user()->tenant_id,
        'service_request_id' => $job->id,
        'user_id' => auth()->id(),
        'type' => 'punch_out',
        'occurred_at' => now(),
    ]);

    return redirect()->route('technician.jobs.punched-out', $job);
}


    public function punchOutReview(ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $srType = ServiceRequest::class;

        $has = [
            'before'    => Attachment::where('attachable_type', $srType)
                ->where('attachable_id', $job->id)
                ->where('kind', 'before_photo')
                ->exists(),
            'after'     => Attachment::where('attachable_type', $srType)
                ->where('attachable_id', $job->id)
                ->where('kind', 'after_photo')
                ->exists(),
            'signature' => (bool) $job->signed_at,
        ];

        $ready = $has['before'] && $has['after'] && $has['signature'];

        return view('technician.jobs.punch_out', compact('job', 'has', 'ready'));
    }


    public function hold(Request $request, ServiceRequest $job)
{
    abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

    $validated = $request->validate([
        'hold_reason_id' => 'required|integer|exists:hold_reasons,id',
        'note'           => 'nullable|string|max:2000',
    ]);

    $reason = \App\Models\HoldReason::find($validated['hold_reason_id']);

    if ($reason && $reason->requires_note && empty($validated['note'])) {
        return back()->withErrors(['note' => 'This reason needs a note.'])->withInput();
    }

    $data = $this->normalizeFieldEventInput($request);

    if (! $this->alreadyApplied($data['client_event_uuid'])) {
        $geo = $this->evaluateGeofence($job, $data);
        $this->writePunch($job, 'hold_start', $data, $geo, $validated['hold_reason_id']);
        $this->logFieldEvent($job, 'hold_start', $data, 'applied');
    }

    $job->update([
        'is_on_hold'              => true,
        'current_hold_reason_id'  => $validated['hold_reason_id'],
        'hold_started_at'         => now(),
    ]);

    \App\Models\SrEvent::create([
        'tenant_id' => auth()->user()->tenant_id,
        'service_request_id' => $job->id,
        'user_id' => auth()->id(),
        'type' => 'hold',
        'label' => $reason?->label,
        'occurred_at' => now(),
    ]);

    if (! empty($validated['note'])) {
        \App\Models\SrNote::create([
            'tenant_id'          => auth()->user()->tenant_id,
            'service_request_id' => $job->id,
            'user_id'            => auth()->id(),
            'visibility'         => 'internal',
            'source'             => 'user',
            'body'               => "Hold reason: {$reason?->label}\n\n{$validated['note']}",
        ]);
    }

    return redirect()->route('technician.jobs.show', $job)
        ->with('success', 'Job placed on hold.');
}

    public function resume(Request $request, ServiceRequest $job)
{
    abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

    $data = $this->normalizeFieldEventInput($request);

    if (! $this->alreadyApplied($data['client_event_uuid'])) {
        $geo = $this->evaluateGeofence($job, $data);
        $this->writePunch($job, 'hold_resume', $data, $geo);
        $this->logFieldEvent($job, 'hold_resume', $data, 'applied');
    }

    \App\Models\SrEvent::create([
        'tenant_id' => auth()->user()->tenant_id,
        'service_request_id' => $job->id,
        'user_id' => auth()->id(),
        'type' => 'resume',
        'occurred_at' => now(),
    ]);

    $job->update([
        'is_on_hold' => false,
        'current_hold_reason_id' => null,
        'hold_started_at' => null,
    ]);

    return redirect()->route('technician.jobs.show', $job)->with('success', 'Resumed.');
}


    public function punchedOut(ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $srType = ServiceRequest::class;

        // Total time on hold = sum of each hold→resume interval
        $events = \App\Models\SrEvent::where('service_request_id', $job->id)
            ->orderBy('occurred_at')->get();

        $totalHoldSec = 0;
        $holdStart = null;
        foreach ($events as $ev) {
            if ($ev->type === 'hold')   $holdStart = $ev->occurred_at;
            if ($ev->type === 'resume' && $holdStart) {
                $totalHoldSec += $holdStart->diffInSeconds($ev->occurred_at);
                $holdStart = null;
            }
        }

        // Work time = (punch out - first punch in) - total hold
        $totalSec = 0;
        if ($job->first_punch_in_at && $job->last_punch_out_at) {
            $totalSec = max(
                0,
                \Carbon\Carbon::parse($job->first_punch_in_at)
                    ->diffInSeconds(\Carbon\Carbon::parse($job->last_punch_out_at))
                    - $totalHoldSec
            );
        }

        $summary = [
            'work_minutes' => (int) round($totalSec / 60),
            'hold_minutes' => (int) round($totalHoldSec / 60),
            'photos'       => Attachment::where('attachable_type', $srType)
                ->where('attachable_id', $job->id)
                ->whereIn('kind', ['before_photo', 'after_photo'])
                ->count(),
            'expenses'     => \App\Models\ServiceRequestExpense::where('service_request_id', $job->id)
                ->count(),
        ];

        return view('technician.jobs.punched_out', compact('job', 'summary'));
    }

    /* ===================== FIELD EVENT / GEOFENCE HELPERS ===================== */

private function normalizeFieldEventInput(Request $request): array
{
    return [
        'client_event_uuid'  => $request->input('client_event_uuid') ?: (string) \Illuminate\Support\Str::uuid(),
        'recorded_at_device' => $request->input('recorded_at_device') ?: now()->toIso8601String(),
        'lat'                => $request->input('lat'),
        'lng'                => $request->input('lng'),
        'accuracy'           => $request->input('accuracy'),
        'gps_status'         => $request->input('gps_status') ?: 'unavailable',
        'device_info'        => $request->input('device_info') ?: $request->userAgent(),
    ];
}

private function alreadyApplied(string $clientEventUuid): bool
{
    return ServiceRequestPunch::where('tenant_id', auth()->user()->tenant_id)
        ->where('client_event_uuid', $clientEventUuid)
        ->exists();
}

private function evaluateGeofence(ServiceRequest $job, array $data): array
{
    $site   = $job->site;
    $radius = $site->geofence_radius_meters ?? 250;

    if (! in_array($data['gps_status'], ['ok', 'low_accuracy'], true)) {
        return ['within' => false, 'distance' => null, 'radius' => $radius];
    }

    if (! $site || $site->lat === null || $site->lng === null || $data['lat'] === null || $data['lng'] === null) {
        return ['within' => null, 'distance' => null, 'radius' => $radius];
    }

    $distance = $this->haversineMeters((float) $site->lat, (float) $site->lng, (float) $data['lat'], (float) $data['lng']);

    return ['within' => $distance <= $radius, 'distance' => (int) round($distance), 'radius' => $radius];
}

private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

private function writePunch(ServiceRequest $job, string $type, array $data, array $geo, ?int $holdReasonId = null): ServiceRequestPunch
{
    return ServiceRequestPunch::create([
        'tenant_id'            => auth()->user()->tenant_id,
        'service_request_id'   => $job->id,
        'technician_user_id'   => auth()->id(),
        'client_event_uuid'    => $data['client_event_uuid'],
        'punch_type'           => $type,
        'hold_reason_id'       => $holdReasonId,
        'recorded_at_device'   => $data['recorded_at_device'],
        'synced_to_server_at'  => now(),
        'is_offline_sync'      => false,
        'clock_drift_seconds'  => now()->diffInSeconds(\Carbon\Carbon::parse($data['recorded_at_device']), false),
        'gps_lat'              => $data['lat'],
        'gps_lng'              => $data['lng'],
        'gps_accuracy_meters'  => $data['accuracy'] !== null ? (int) round($data['accuracy']) : null,
        'gps_status'           => $data['gps_status'],
        'site_distance_meters' => $geo['distance'],
        'is_within_geofence'   => $geo['within'],
        'sync_status'          => 'synced',
        'device_info'          => substr((string) $data['device_info'], 0, 255),
    ]);
}

private function logFieldEvent(ServiceRequest $job, string $eventType, array $data, string $result, ?string $conflictCode = null): void
{
    FieldEventLog::firstOrCreate(
        ['tenant_id' => auth()->user()->tenant_id, 'client_event_uuid' => $data['client_event_uuid']],
        [
            'user_id'             => auth()->id(),
            'event_type'          => $eventType,
            'service_request_id'  => $job->id,
            'recorded_at_device'  => $data['recorded_at_device'],
            'synced_to_server_at' => now(),
            'clock_drift_seconds' => now()->diffInSeconds(\Carbon\Carbon::parse($data['recorded_at_device']), false),
            'result'              => $result,
            'conflict_code'       => $conflictCode,
            'payload_hash'        => hash('sha256', json_encode($data)),
            'payload_json'        => $data,
        ]
    );
}

private function findUsableOverride(ServiceRequest $job): ?GeofenceOverrideRequest
{
    return GeofenceOverrideRequest::where('tenant_id', auth()->user()->tenant_id)
        ->where('service_request_id', $job->id)
        ->where('technician_user_id', auth()->id())
        ->where('status', 'approved')
        ->whereNull('used_punch_id')
        ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
        ->latest('decided_at')
        ->first();
}

public function requestGeofenceOverride(Request $request, ServiceRequest $job)
{
    abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

    $validated = $request->validate([
        'reason' => 'required|string|max:255',
    ]);

    $data = $this->normalizeFieldEventInput($request);
    $geo  = $this->evaluateGeofence($job, $data);

    GeofenceOverrideRequest::create([
        'tenant_id'            => auth()->user()->tenant_id,
        'service_request_id'   => $job->id,
        'technician_user_id'   => auth()->id(),
        'client_event_uuid'    => $data['client_event_uuid'],
        'reason'               => $validated['reason'],
        'requested_at_device'  => $data['recorded_at_device'],
        'gps_lat'              => $data['lat'],
        'gps_lng'              => $data['lng'],
        'gps_accuracy_meters'  => $data['accuracy'] !== null ? (int) round($data['accuracy']) : null,
        'site_distance_meters' => $geo['distance'],
        'status'               => 'pending',
        'valid_until'          => now()->addHours(2),
    ]);

    return redirect()->route('technician.jobs.show', $job)
        ->with('success', 'Override request sent. Waiting for your manager to approve it.');
}

    /* ===================== SUB-PAGES ===================== */

    public function photos(Request $request, ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $kind = $request->input('kind', 'before');
        $dbKind = $kind === 'before' ? 'before_photo' : 'after_photo';

        $photos = Attachment::query()
            ->where('attachable_type', ServiceRequest::class)
            ->where('attachable_id', $job->id)
            ->where('kind', $dbKind)
            ->orderBy('id')
            ->get();

        $counts = [
            'before' => Attachment::where('attachable_type', ServiceRequest::class)
                ->where('attachable_id', $job->id)->where('kind', 'before_photo')->count(),
            'after'  => Attachment::where('attachable_type', ServiceRequest::class)
                ->where('attachable_id', $job->id)->where('kind', 'after_photo')->count(),
        ];

        return view('technician.jobs.photos', compact('job', 'photos', 'counts', 'kind'));
    }

    public function storePhoto(Request $request, ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $data = $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp,heic|max:12288', // 12 MB
            'kind'  => 'required|in:before,after',
        ]);

        $file = $data['photo'];
        $kind = $data['kind'] === 'before' ? 'before_photo' : 'after_photo';

        // Store in storage/app/public/sr/{job}/photos/
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs(
            "sr/{$job->id}/photos",
            $fileName,
            'public'
        );

        // Record in the polymorphic attachments table
        Attachment::create([
            'tenant_id'          => auth()->user()->tenant_id,
            'attachable_type'    => ServiceRequest::class,
            'attachable_id'      => $job->id,
            'kind'               => $kind,
            'storage_path'       => $path,
            'original_name'      => $file->getClientOriginalName(),
            'mime_type'          => $file->getMimeType(),
            'size_bytes'         => $file->getSize(),
            'sha256'             => hash_file('sha256', $file->getRealPath()),
            'captured_at'        => now(),
            'uploaded_by_user_id' => auth()->id(),
            'client_event_uuid'  => (string) \Illuminate\Support\Str::uuid(),
        ]);

        return redirect()->route('technician.jobs.photos', ['job' => $job, 'kind' => $data['kind']])
            ->with('success', 'Photo added.');
    }

    public function notes(ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $notes = \App\Models\SrNote::where('service_request_id', $job->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();

        return view('technician.jobs.notes', compact('job', 'notes'));
    }

    public function storeNote(Request $request, ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        \App\Models\SrNote::create([
            'tenant_id'          => auth()->user()->tenant_id,
            'service_request_id' => $job->id,
            'user_id'            => auth()->id(),
            'visibility'         => 'internal',   // ✓ matches enum
            'source'             => 'user',       // ← was 'field', change to 'user'
            'body'               => $data['body'],
        ]);

        return redirect()->route('technician.jobs.notes', $job)
            ->with('success', 'Note saved.');
    }

    public function expenses(ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $expenses = \App\Models\ServiceRequestExpense::where('service_request_id', $job->id)
            ->with(['category:id,name', 'receipt'])
            ->orderByDesc('id')
            ->get();

        return view('technician.jobs.expenses', compact('job', 'expenses'));
    }

    public function createExpense(ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);
        $cats = \App\Models\ExpenseCategory::where('is_active', true)->orderBy('name')->get();
        return view('technician.jobs.expense_create', ['job' => $job, 'cats' => $cats]);
    }

    public function storeExpense(Request $request, ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        // $data = $request->validate([
        //     'category_id' => 'required|integer|exists:expense_categories,id',
        //     'desc'        => 'nullable|string|max:255',
        //     'qty'         => 'required|numeric|min:0.01',
        //     'rate'        => 'required|numeric|min:0',
        //     'receipt'     => 'nullable|image|mimes:jpeg,jpg,png,webp,heic|max:12288',
        // ]);

        $data = $request->validate([
            'category_id' => 'required|integer|exists:expense_categories,id',
            'desc'        => 'nullable|string|min:2|max:255',
            'qty'         => 'required|numeric|min:0.01|decimal:0,2',
            'rate'        => 'required|numeric|min:0|decimal:0,2',
            'receipt'     => 'nullable|image|mimes:jpeg,jpg,png,webp,heic|max:12288',
        ], [
            'category_id.required' => 'Please pick a category.',
            'desc.min'    => 'Enter a description with at least 2 characters.',
            'desc.max'    => 'Description is too long (max 255 characters).',
            'qty.min'     => 'Enter a quantity above 0.',
            'qty.decimal' => 'Quantity may have up to 2 decimals.',
            'rate.min'    => 'Enter a rate of 0 or more.',
            'rate.decimal' => 'Rate may have up to 2 decimals.',
            'receipt.image' => 'Receipt must be an image.',
            'receipt.max'   => 'Receipt is too large (max 12 MB).',
        ]);

        $total = round((float) $data['qty'] * (float) $data['rate'], 2);
        $receiptAttachmentId = null;

        // Upload receipt photo if given
        if ($request->hasFile('receipt')) {
            $file = $request->file('receipt');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("sr/{$job->id}/receipts", $fileName, 'public');

            $att = \App\Models\Attachment::create([
                'tenant_id'          => auth()->user()->tenant_id,
                'attachable_type'    => \App\Models\ServiceRequest::class,
                'attachable_id'      => $job->id,
                'kind'               => 'receipt',
                'storage_path'       => $path,
                'original_name'      => $file->getClientOriginalName(),
                'mime_type'          => $file->getMimeType(),
                'size_bytes'         => $file->getSize(),
                'sha256'             => hash_file('sha256', $file->getRealPath()),
                'captured_at'        => now(),
                'uploaded_by_user_id' => auth()->id(),
                'client_event_uuid'  => (string) \Illuminate\Support\Str::uuid(),
            ]);
            $receiptAttachmentId = $att->id;
        }

        // \App\Models\ServiceRequestExpense::create([
        //     'tenant_id'            => auth()->user()->tenant_id,
        //     'service_request_id'   => $job->id,
        //     'logged_by_user_id'    => auth()->id(),
        //     'client_event_uuid'    => (string) \Illuminate\Support\Str::uuid(),
        //     'expense_category_id'  => $data['category_id'],
        //     'description'          => $data['desc'] ?? '',
        //     'quantity'             => $data['qty'],
        //     'unit_rate'            => $data['rate'],
        //     'total_amount'         => $total,
        //     'is_billable_to_client' => true,
        //     'receipt_attachment_id' => $receiptAttachmentId,
        //     'approval_status'      => 'pending',
        // ]);


        // Read the category's billable default — fall back to false if not set
        $category = \App\Models\ExpenseCategory::find($data['category_id']);
        $isBillable = (bool) ($category->is_billable_default ?? false);

        \App\Models\ServiceRequestExpense::create([
            'tenant_id'            => auth()->user()->tenant_id,
            'service_request_id'   => $job->id,
            'logged_by_user_id'    => auth()->id(),
            'client_event_uuid'    => (string) \Illuminate\Support\Str::uuid(),
            'expense_category_id'  => $data['category_id'],
            'description'          => $data['desc'] ?? '',
            'quantity'             => $data['qty'],
            'unit_rate'            => $data['rate'],
            'total_amount'         => $total,
            'is_billable_to_client' => $isBillable,                    // ← reads from category
            'receipt_attachment_id' => $receiptAttachmentId,
            'approval_status'      => 'pending',
        ]);


        return redirect()->route('technician.jobs.expenses', $job)
            ->with('success', 'Expense saved. Total ' . number_format($total, 2) . ' AED.');
    }

    public function signature(ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $existing = null;
        if ($job->customer_signature_attachment_id) {
            $existing = \App\Models\Attachment::find($job->customer_signature_attachment_id);
        }

        return view('technician.jobs.signature', [
            'job'      => $job,
            'existing' => $existing,
        ]);
    }

    public function storeSignature(Request $request, ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $data = $request->validate([
            'signer_name'    => 'required|string|max:150',
            'signer_phone'   => 'nullable|string|max:40',
            'signature_data' => 'required|string|starts_with:data:image/',
        ], [
            'signer_name.required'    => 'Signer name is required.',
            'signer_name.max'         => 'Signer name is too long (max 150 characters).',
            'signer_phone.max'        => 'Phone is too long.',
            'signature_data.required'       => 'Please sign in the box before saving.',
            'signature_data.starts_with'    => 'Signature is invalid. Try drawing again.',
        ]);

        // ===== Decode the base64 PNG from the canvas =====
        [$meta, $raw] = explode(',', $data['signature_data'], 2);
        $binary = base64_decode($raw);
        abort_if($binary === false, 422, 'Could not decode signature.');

        // ===== Save PNG to storage/app/public/sr/{id}/signatures/ =====
        $filename = 'sig_' . time() . '_' . uniqid() . '.png';
        $path     = "sr/{$job->id}/signatures/{$filename}";
        \Storage::disk('public')->put($path, $binary);

        // ===== Record in attachments table =====
        $att = \App\Models\Attachment::create([
            'tenant_id'           => auth()->user()->tenant_id,
            'attachable_type'     => \App\Models\ServiceRequest::class,
            'attachable_id'       => $job->id,
            'kind'                => 'signature',
            'storage_path'        => $path,
            'original_name'       => $filename,
            'mime_type'           => 'image/png',
            'size_bytes'          => strlen($binary),
            'sha256'              => hash('sha256', $binary),
            'captured_at'         => now(),
            'uploaded_by_user_id' => auth()->id(),
            'client_event_uuid'   => (string) \Illuminate\Support\Str::uuid(),
        ]);

        // ===== Link back to the SR =====
        $job->update([
            'customer_signer_name'            => $data['signer_name'],
            'customer_signer_phone'           => $data['signer_phone'],
            'customer_signature_attachment_id' => $att->id,
            'signed_at'                       => now(),
        ]);

        return redirect()->route('technician.jobs.show', $job)
            ->with('success', 'Signature saved.');
    }


    public function liveCounts(ServiceRequest $job)
    {
        abort_unless($job->assigned_tech_user_id === auth()->id(), 403);

        $srType = \App\Models\ServiceRequest::class;

        return response()->json([
            'before'    => \App\Models\Attachment::where('attachable_type', $srType)->where('attachable_id', $job->id)->where('kind', 'before_photo')->count(),
            'after'     => \App\Models\Attachment::where('attachable_type', $srType)->where('attachable_id', $job->id)->where('kind', 'after_photo')->count(),
            'notes'     => \App\Models\SrNote::where('service_request_id', $job->id)->count(),
            'expenses'  => \App\Models\ServiceRequestExpense::where('service_request_id', $job->id)->count(),
            'signature' => (bool) $job->signed_at,
        ]);
    }

    /* ===================== STATIC-ish PAGES ===================== */

    public function profile(Request $request)
    {
        return view('technician.jobs.profile', ['u' => $request->user()]);
    }

    public function notifications(Request $request)
    {
        // Placeholder notifications until a real inbox table exists
        $notifications = collect([
            ['type' => 'emergency', 'title' => 'Emergency job assigned',   'body' => 'SR-2026-000147, Kitchen power failure. Arrival 09:30.', 'time' => now()->subHour()],
            ['type' => 'info',      'title' => 'New job assigned',         'body' => 'SR-2026-000143, Air conditioner not cooling. Arrival 11:00.', 'time' => now()->subHour()],
            ['type' => 'info',      'title' => 'Job sent back for rework', 'body' => 'SR-2026-000139. The After photo is unclear.', 'time' => now()->subHours(2)],
        ]);

        return view('technician.jobs.notifications', compact('notifications'));
    }

    public function sync(Request $request)
    {
        $pending  = 0;
        $conflict = 0;
        $lastSent = now();

        return view('technician.jobs.sync', compact('pending', 'conflict', 'lastSent'));
    }
}
