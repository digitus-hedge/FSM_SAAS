@php
    $prioMap = ['emergency'=>['Emergency','red'],'critical'=>['Critical','red'],'high'=>['High','amber'],'medium'=>['Medium','blue'],'low'=>['Low','slate']];
    $stateMap = ['assigned'=>['Assigned','blue'],'in_progress'=>['In progress','green'],'rework'=>['Rework','amber'],'pending_review'=>['Pending review','violet'],'completed'=>['Completed','slate'],'cancelled'=>['Cancelled','slate']];
    [$prioLabel, $prioColor] = $prioMap[$job->priority] ?? ['', 'slate'];
    [$stateLabel, $stateColor] = $stateMap[$job->status] ?? ['', 'slate'];
    $sameDay = $job->scheduled_eta_at && $job->scheduled_eta_at->isSameDay(now());
    $isLink = $linkable ?? true;
    $tag = $isLink ? 'a' : 'div';
    $isActive = $job->status === 'in_progress';
    $cardStyle = 'display:block;text-decoration:none;color:inherit;border-left:4px solid var(--' . $stateColor . ')';
    if ($isActive) {
        $cardStyle .= ';box-shadow:0 6px 18px -10px color-mix(in srgb,var(--accent) 55%,transparent)';
    }
@endphp
<{{ $tag }} @if($isLink) href="{{ route('technician.jobs.show', $job) }}" @endif
    class="card tap"
    style="{{ $cardStyle }}">
    <div class="r1">
        <span>{{ $job->sr_number }}</span>
        <span class="chip sm" style="--c:var(--{{ $prioColor }})">{{ $prioLabel }}</span>
    </div>
    <h3>{{ $job->title }}</h3>
    <div class="sub">{{ $job->client?->name }}, {{ $job->site?->name }}</div>
    <div class="r3">
        <span class="eta">@include('technician._icon', ['name'=>'clock','size'=>15]) {{ $sameDay ? 'Today ' : ($job->scheduled_eta_at?->format('j M ') ?? '') }}{{ $job->scheduled_eta_at?->format('g:ia') ?? '—' }}</span>
        <span class="chip" style="--c:var(--{{ $stateColor }})">{{ $stateLabel }}</span>
        @if ($job->status === 'in_progress' && $job->is_on_hold)<span class="chip sm" style="--c:var(--amber)">On hold</span>@endif
        @if (in_array($job->status, ['assigned','rework']) && ! $job->tech_accepted_at)<span class="chip sm" style="--c:var(--slate)">Not accepted</span>@endif
        @if ($job->enforce_geofence)<span class="chip sm" style="--c:var(--blue)">Geofence on</span>@endif
        @if ($job->status === 'cancelled')<span class="chip sm" style="--c:var(--red)">Cancelled</span>@endif
    </div>
    @if ($isActive && $isLink)
        <div style="display:flex;align-items:center;gap:6px;margin-top:12px;padding-top:12px;border-top:1px solid var(--line);font-weight:700;font-size:13.5px;color:var(--accent)">
            Open job sheet
            @include('technician._icon', ['name'=>'right','size'=>16])
        </div>
    @endif
</{{ $tag }}>