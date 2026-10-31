@extends('layouts.technician')
@section('title', 'Notes')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')
<form method="POST" action="{{ route('technician.jobs.notes.store', $serviceRequest) }}" class="card">
    @csrf
    <label class="field">Note<textarea name="body" rows="3" required maxlength="2000"></textarea></label>
    <label class="field">Visible to
        <select name="visibility">
            <option value="internal">Internal only</option>
            <option value="client_visible">Client can see this</option>
        </select>
    </label>
    <button type="submit" class="btn block">Add note</button>
</form>
@if ($notes->isEmpty())
    <div class="empty">No notes yet.</div>
@else
    @foreach ($notes as $note)
        <div class="card">
            <div style="font-size:13.5px">{{ $note->body }}</div>
            <div style="margin-top:6px;font-size:11.5px;color:var(--muted)">
                {{ $note->user?->name }} · {{ $note->created_at->format('j M, g:ia') }}
                @if ($note->visibility === 'client_visible')<span class="chip green" style="margin-left:6px">Client visible</span>@endif
            </div>
        </div>
    @endforeach
@endif
@endsection