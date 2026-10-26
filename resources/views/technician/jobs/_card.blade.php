@php
  $pri = strtolower($job->priority ?? 'medium');
  $priClass = match($pri) {
    'emergency','critical' => 'chip-emerg',
    'high'   => 'chip-high',
    'medium' => 'chip-med',
    default  => 'chip-low'
  };

  $time = $job->scheduled_eta_at ? \Carbon\Carbon::parse($job->scheduled_eta_at) : null;
  $timeLabel = $time
    ? ($time->isToday()
        ? 'Today '.$time->format('H:i')
        : ($time->isYesterday() ? 'Yesterday '.$time->format('H:i')
            : $time->format('j M H:i')))
    : null;

  $accepted = ! empty($job->tech_accepted_at);
  $isRework = ($job->rework_count ?? 0) > 0;
  $onHold   = (bool) ($job->is_on_hold ?? false);

  $statusLabel = match($job->status) {
    'assigned'       => 'Assigned',
    'in_progress'    => 'In progress',
    'pending_review' => 'Pending review',
    'completed'      => 'Completed',
    'cancelled'      => 'Cancelled',
    'approved'       => 'Approved',
    'quoted'         => 'Quoted',
    'pending'        => 'Pending',
    default          => ucfirst(str_replace('_', ' ', $job->status ?? '')),
  };

  $statusClass = match($job->status) {
    'assigned'       => 'chip-assigned',
    'in_progress'    => 'chip-ok',
    'pending_review' => 'chip-med',
    'completed'      => 'chip-low',
    'cancelled'      => 'chip-low',
    default          => 'chip-low',
  };
@endphp

<a href="{{ route('technician.jobs.show', $job) }}"
   style="display:block;background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px;margin-bottom:10px;text-decoration:none;color:var(--ink)">

  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:8px">
    <span style="font-size:12.5px;color:var(--muted);font-weight:600">{{ $job->sr_number ?? ('SR-'.$job->id) }}</span>
    <span class="chip {{ $priClass }}">{{ ucfirst($pri) }}</span>
  </div>

  <div style="font-family:'Figtree';font-size:18px;font-weight:700;color:var(--ink);line-height:1.3;margin-bottom:6px">
    {{ $job->title ?? 'Service request' }}
  </div>

  <div style="font-size:14px;color:var(--muted);margin-bottom:12px">
    {{ $job->client?->name ?? 'Client' }}@if ($job->site?->name), {{ $job->site->name }}@endif
  </div>

  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    @if ($timeLabel)
      <span style="display:inline-flex;align-items:center;gap:6px;font-size:13.5px;color:var(--ink);font-weight:700">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        {{ $timeLabel }}
      </span>
    @endif

    {{-- Main status pill --}}
    @if ($job->status)
      <span class="chip {{ $statusClass }}">{{ $statusLabel }}</span>
    @endif

    {{-- Secondary flags --}}
    @if ($job->status === 'assigned' && ! $accepted)
      <span class="chip chip-notaccepted">Not accepted</span>
    @endif

    @if ($isRework)
      <span class="chip chip-rework">Rework</span>
    @endif

    @if ($onHold)
      <span class="chip" style="background:#FFF4E6;color:#B35F00">On hold</span>
    @endif

    @if ($job->status === 'in_progress' && ($job->always_enforce_geofence ?? false))
      <span class="chip chip-med">Geofence on</span>
    @endif
  </div>
</a>