@extends('layouts.technician')
@section('title', 'Notes')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')

<form method="POST" action="{{ route('technician.jobs.notes.store', $serviceRequest) }}" class="card" style="padding:18px;margin-bottom:16px">
    @csrf
    <div class="field">
        <label>Note</label>
        <textarea name="body" class="txt" rows="3" required maxlength="2000" placeholder="Add a note about this job..."></textarea>
    </div>
    <div class="field">
        <label>Visible to</label>
        <select name="visibility" class="sel">
            <option value="internal">Internal only</option>
            <option value="client_visible">Client can see this</option>
        </select>
    </div>
    <button type="submit" class="btn dark block">Add note</button>
</form>

@if ($notes->isEmpty())
    <div class="card center" style="padding:36px 20px">
        <div style="width:50px;height:50px;border-radius:999px;background:var(--tint);color:var(--accent);display:grid;place-items:center;margin:0 auto 12px">
            @include('technician._icon', ['name'=>'doc','size'=>22])
        </div>
        <h3 style="margin-bottom:4px;font-size:15px">No notes yet</h3>
        <p class="sub" style="margin:0">Notes you add about this job will show up here.</p>
    </div>
@else
    <div class="sec">{{ $notes->count() }} note{{ $notes->count() === 1 ? '' : 's' }}</div>
    @foreach ($notes as $note)
        @php
            $initials = collect(preg_split('/\s+/', trim($note->user?->name ?? '?')))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
        @endphp
        <div class="card">
            <div style="display:flex;gap:10px;align-items:flex-start">
                <div style="width:30px;height:30px;border-radius:999px;background:var(--tint);color:var(--accent);display:grid;place-items:center;font-weight:700;font-size:12px;flex:0 0 auto">{{ strtoupper($initials ?: '?') }}</div>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                        <span style="font-weight:700;font-size:13px">{{ $note->user?->name ?? 'Unknown' }}</span>
                        <span class="muted" style="font-size:11.5px;white-space:nowrap">{{ $note->created_at->format('j M, g:ia') }}</span>
                    </div>
                    <div style="font-size:13.5px;margin-top:4px;line-height:1.5">{{ $note->body }}</div>
                    @if ($note->visibility === 'client_visible')
                        <span class="chip sm" style="--c:var(--green);margin-top:8px">Client visible</span>
                    @else
                        <span class="chip sm" style="--c:var(--slate);margin-top:8px">Internal only</span>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection