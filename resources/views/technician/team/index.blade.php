@extends('layouts.technician')
@section('title', 'Team')

@section('content')
<div style="display:flex;background:var(--soft);border-radius:12px;padding:4px;margin-bottom:14px;overflow-x:auto">
  @foreach ([
    'jobs'      => 'Jobs',
    'action'    => 'Needs action ('.$counts['action'].')',
    'overrides' => 'Overrides ('.$counts['overrides'].')',
    'conflicts' => 'Conflicts ('.$counts['conflicts'].')',
  ] as $k => $label)
    <a href="?tab={{ $k }}"
       style="flex:1;padding:11px 8px;text-align:center;border-radius:9px;font-size:13px;font-weight:800;white-space:nowrap;color:{{ $tab===$k ? 'var(--ink)' : 'var(--muted)' }};background:{{ $tab===$k ? '#fff' : 'transparent' }};text-decoration:none">{{ $label }}</a>
  @endforeach
</div>

@if ($tab === 'jobs')
  @forelse ($jobs as $techId => $techJobs)
    <div class="muted" style="font-weight:700;margin:12px 2px 6px">{{ $techJobs->first()->assignedTech?->name ?? 'Technician' }}</div>
    @foreach ($techJobs as $job) @include('technician.jobs._card', ['job' => $job]) @endforeach
  @empty
    <div class="card muted" style="text-align:center">No team jobs right now.</div>
  @endforelse
@endif

@if ($tab === 'action')
  @forelse ($action as $job)
    @php $isPending = $job->status === 'pending'; @endphp
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <span style="font-size:12.5px;color:var(--muted);font-weight:600">{{ $job->sr_number ?? ('SR-'.$job->id) }}</span>
        <span class="chip {{ $isPending ? 'chip-med' : 'chip-assigned' }}">{{ $isPending ? 'Needs approval' : 'Needs assigning' }}</span>
      </div>
      <div style="font-family:'Figtree';font-weight:700;font-size:16px;margin-bottom:4px">{{ $job->title }}</div>
      <div style="font-size:13.5px;color:var(--muted);margin-bottom:10px">{{ $job->client?->name }}, {{ $job->site?->name }}</div>

      @if ($isPending)
        <div style="display:flex;gap:8px">
          <form method="POST" action="{{ route('service-requests.reject', $job) }}" style="flex:1;margin:0" onsubmit="return confirm('Reject this request?')">
            @csrf<button type="submit" class="btn" style="width:100%;background:#fff;color:var(--red);border:1px solid #F5C7C0">Reject</button>
          </form>
          <form method="POST" action="{{ route('service-requests.approve', $job) }}" style="flex:1;margin:0">
            @csrf<button type="submit" class="btn btn-primary">Approve</button>
          </form>
        </div>
      @else
        <button type="button" class="btn btn-primary" onclick="openSheet('sheet-assign-{{ $job->id }}')">Assign</button>
        @include('technician.team._assign_sheet', ['job' => $job])
      @endif
    </div>
  @empty
    <div class="card muted" style="text-align:center">Nothing needs your action right now.</div>
  @endforelse

  <button type="button" class="btn" style="margin-top:10px;background:#fff;border:1px solid var(--line)" onclick="openSheet('sheet-newreq')">+ New request</button>
  @include('technician.team._new_sheet')
@endif

@if ($tab === 'overrides')
  @forelse ($overrides as $o)
    <div class="card">
      <div style="font-size:12.5px;color:var(--muted);font-weight:600;margin-bottom:4px">{{ $o->serviceRequest?->sr_number }}</div>
      <div style="font-family:'Figtree';font-weight:700;margin-bottom:6px">{{ $o->serviceRequest?->title }}</div>
      <div style="font-size:14px;margin-bottom:6px"><strong>Reason:</strong> {{ $o->reason }}</div>
      <div style="font-size:12.5px;color:var(--muted);margin-bottom:10px">
        {{ $o->site_distance_meters !== null ? $o->site_distance_meters.'m from the site' : 'Distance unknown' }}
      </div>
      <div style="display:flex;gap:8px">
        <form method="POST" action="{{ route('technician.team.overrides.reject', $o) }}" style="flex:1;margin:0" onsubmit="return promptNote(this)">
          @csrf<input type="hidden" name="note">
          <button type="button" class="btn" style="width:100%;background:#fff;color:var(--red);border:1px solid #F5C7C0" onclick="var n=prompt('Reason for rejecting:');if(n){this.form.note.value=n;this.form.submit();}">Reject</button>
        </form>
        <form method="POST" action="{{ route('technician.team.overrides.approve', $o) }}" style="flex:1;margin:0">
          @csrf<button type="submit" class="btn btn-primary">Approve</button>
        </form>
      </div>
    </div>
  @empty
    <div class="card muted" style="text-align:center">No override requests are waiting.</div>
  @endforelse
@endif

@if ($tab === 'conflicts')
  @forelse ($conflicts as $c)
    <div class="card">
      <div style="font-size:12.5px;color:var(--muted);font-weight:600;margin-bottom:4px">{{ $c->serviceRequest?->sr_number }}</div>
      <div style="font-weight:700;margin-bottom:4px">{{ $c->user?->name }}, {{ $c->event_type }}</div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:10px">The office's change won over this offline action. Evidence is kept.</div>
      <form method="POST" action="{{ route('technician.team.conflicts.reviewed', $c) }}" style="margin:0">
        @csrf<button type="submit" class="btn" style="background:#fff;border:1px solid var(--line)">Mark reviewed</button>
      </form>
    </div>
  @empty
    <div class="card muted" style="text-align:center">No field conflicts.</div>
  @endforelse
@endif

<div class="sheet-bd" id="team-sheet-bd" onclick="closeSheets()"></div>
<script>
function openSheet(id){document.getElementById('team-sheet-bd').classList.add('open');document.getElementById(id).classList.add('open');}
function closeSheets(){document.getElementById('team-sheet-bd').classList.remove('open');document.querySelectorAll('.sheet.open').forEach(s=>s.classList.remove('open'));}
</script>
@endsection