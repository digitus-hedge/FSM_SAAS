@extends('layouts.technician')
@section('title', 'Notes')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')
<form method="POST" action="{{ route('technician.jobs.notes.store', $serviceRequest) }}" class="card">
    @csrf
    <div class="field">
        <label>Note</label>
        <textarea name="body" class="txt" rows="3" required maxlength="2000"></textarea>
    </div>
    <div class="field">
        <label>Visible to</label>
        <select name="visibility" class="sel">
            <option value="internal">Internal only</option>
            <option value="client_visible">Client can see this</option>
        </select>
    </div>
    <button type="submit" class="btn block">Add note</button>
</form>
@if ($notes->isEmpty())
    <div class="card muted" style="text-align:center;padding:24px 14px">No notes yet.</div>
@else
    @foreach ($notes as $note)
        <div class="card">
            <div style="font-size:13.5px">{{ $note->body }}</div>
            <div style="margin-top:6px;font-size:11.5px;color:var(--muted)">
                {{ $note->user?->name }} · {{ $note->created_at->format('j M, g:ia') }}
                @if ($note->visibility === 'client_visible')<span class="chip" style="--c:var(--green);margin-left:6px">Client visible</span>@endif
            </div>
        </div>
    @endforeach
@endif
@endsection