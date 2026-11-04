@php
    $prioMap = ['emergency'=>['Emergency','red'],'critical'=>['Critical','red'],'high'=>['High','amber'],'medium'=>['Medium','blue'],'low'=>['Low','slate']];
    $stateMap = ['assigned'=>['Scheduled','blue'],'in_progress'=>['In progress','accent'],'rework'=>['Rework','amber'],'pending_review'=>['Pending review','violet'],'completed'=>['Completed','green'],'cancelled'=>['Cancelled','slate']];
    [$prioLabel, $prioColor] = $prioMap[$job->priority] ?? ['', 'slate'];
    [$stateLabel, $stateColor] = $stateMap[$job->status] ?? ['', 'slate'];
    $sameDay = $job->scheduled_eta_at && $job->scheduled_eta_at->isSameDay(now());
    $isLink = $linkable ?? true;
    $tag = $isLink ? 'a' : 'div';
    $isActive = $job->status === 'in_progress';
    $isUrgent = in_array($job->priority, ['emergency', 'critical'], true);

    $cardStyle = 'display:block;text-decoration:none;color:inherit';
    if ($isActive) {
        $cardStyle .= ';border:2px solid var(--accent);box-shadow:0 8px 20px -12px color-mix(in srgb,var(--accent) 55%,transparent)';
    }

    $clientLabel = $job->client?->name;
    $titleLine = $clientLabel ? ($clientLabel . ' — ' . $job->title) : $job->title;
@endphp
<{{ $tag }} @if($isLink) href="{{ route('technician.jobs.show', $job) }}" @endif
    class="card tap"
    style="{{ $cardStyle }}">
    <div class="r1" style="margin-bottom:8px">
        <span class="muted" style="font-size:13px;font-weight:600">{{ $job->sr_number }}</span>
        <span style="display:flex;align-items:center;gap:6px">
            @if ($isUrgent)<span class="chip sm" style="--c:var(--{{ $prioColor }})">{{ $prioLabel }}</span>@endif
            <span class="chip sm" style="--c:var(--{{ $stateColor }})">{{ $stateLabel }}</span>
        </span>
    </div>
    <h3 style="font-size:16.5px;line-height:1.3">{{ $titleLine }}</h3>
    <div class="sub" style="display:flex;align-items:center;gap:5px;margin-top:4px;font-size:14px">
        @include('technician._icon', ['name'=>'clock','size'=>14])
        <span>{{ $sameDay ? 'Today ' : ($job->scheduled_eta_at?->format('j M ') ?? '') }}{{ $job->scheduled_eta_at?->format('g:ia') ?? '—' }}</span>
        @if ($job->site?->name)<span>&middot; {{ $job->site->name }}</span>@endif
    </div>
    @if (($job->status === 'in_progress' && $job->is_on_hold) || (in_array($job->status, ['assigned','rework']) && ! $job->tech_accepted_at) || $job->enforce_geofence)
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:9px">
            @if ($job->status === 'in_progress' && $job->is_on_hold)<span class="chip sm" style="--c:var(--amber)">On hold</span>@endif
            @if (in_array($job->status, ['assigned','rework']) && ! $job->tech_accepted_at)<span class="chip sm" style="--c:var(--slate)">Not accepted</span>@endif
            @if ($job->enforce_geofence)<span class="chip sm" style="--c:var(--blue)">Geofence on</span>@endif
        </div>
    @endif
    @if ($isActive && $isLink)
        <div class="btn dark block" style="margin-top:12px;border-radius:999px;min-height:46px">
            Open job sheet
            @include('technician._icon', ['name'=>'right','size'=>16])
        </div>
    @endif
</{{ $tag }}>