<style>
  /* Accept-sheet styles (scoped under .sheet-accept so they don't leak) */
  .sheet-accept h3{
    font-family:'Figtree';
    font-size:19px;
    font-weight:800;
    color:var(--ink);
    margin:0 0 6px;
  }
  .sheet-accept .arrival-line{
    color:var(--muted);
    font-size:14.5px;
    line-height:1.5;
    margin:0 0 22px;
  }
  .sheet-accept .arrival-line strong{
    color:var(--ink);
    font-weight:700;
  }
  .sheet-accept .sheet-actions{
    display:flex;
    gap:10px;
    margin-top:4px;
  }
  .sheet-accept .sheet-actions .btn{
    padding:14px 10px;
    font-size:15px;
    font-weight:700;
    border-radius:14px;
  }
  .sheet-accept .sheet-actions .btn-ghost{
    flex:1;
    background:#fff;
    color:var(--ink);
    border:1px solid var(--line);
  }
  .sheet-accept .sheet-actions .btn-primary{
    flex:1.4;
    background:var(--accent);
    color:#fff;
    border:0;
    cursor:pointer;
  }
  .sheet-accept .sheet-actions .btn-primary:hover{
    opacity:.92;
  }
</style>

<div class="sheet sheet-accept" id="sheet-accept" style="max-height:60%">
  <h3>Accept this job?</h3>

  <p class="arrival-line">
    @if ($job->scheduled_eta_at)
      Arrival time <strong>{{ \Carbon\Carbon::parse($job->scheduled_eta_at)->format('H:i') }}</strong>.
    @else
      No arrival time set yet.
    @endif
  </p>

  <div class="sheet-actions">
    {{-- Opens the existing Change arrival time sheet --}}
    <button type="button"
            class="btn btn-ghost"
            onclick="closeSheets(); setTimeout(() => openSheet('sheet-time'), 220);">
      Change time
    </button>

    {{-- Posts to the accept route --}}
    <form method="POST"
          action="{{ route('technician.jobs.accept', $job) }}"
          style="margin:0;flex:1.4">
      @csrf
      <button type="submit" class="btn btn-primary" style="width:100%">
        Accept
      </button>
    </form>
  </div>
</div>