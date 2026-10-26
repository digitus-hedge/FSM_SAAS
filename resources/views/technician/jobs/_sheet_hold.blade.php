<style>
  /* High-specificity selectors so .sheet label { display:block } doesn't override */
  .sheet label.hold-opt{display:flex;gap:12px;padding:14px 2px;border-bottom:1px solid #F2F1F6;cursor:pointer;align-items:flex-start;margin-bottom:0;font-weight:400;color:var(--ink)}
  .sheet label.hold-opt:last-of-type{border-bottom:0}

.sheet label.hold-opt input[type=radio]{
  appearance:none !important;
  -webkit-appearance:none !important;
  -moz-appearance:none !important;
  box-sizing:border-box;
  display:inline-block;
  width:20px;
  min-width:20px;
  height:20px;
  min-height:20px;
  padding:0;
  border:1.5px solid #D1CFDA;
  border-radius:50%;
  background:#fff;
  cursor:pointer;
  position:relative;
  flex-shrink:0;
  flex-grow:0;
  margin:2px 0 0 0;
  outline:none;
  vertical-align:top;
}
.sheet label.hold-opt input[type=radio]:checked{
  border-color:var(--accent);
}
.sheet label.hold-opt input[type=radio]:checked::after{
  content:'';
  position:absolute;
  left:50%;
  top:50%;
  transform:translate(-50%,-50%);
  width:10px;
  height:10px;
  border-radius:50%;
  background:var(--accent);
}

  .sheet label.hold-opt .t{font-weight:800;font-size:14.5px;color:var(--ink);line-height:1.3}
  .sheet label.hold-opt .s{font-size:12.5px;color:var(--muted);margin-top:2px}

  .note-block{display:none;margin-top:14px}
  .note-block.show{display:block}
  .note-block label{display:block;font-weight:800;font-size:14px;margin-bottom:6px}
  .note-block textarea{width:100%;font-family:'DM Sans';font-size:14.5px;padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;outline:none;box-sizing:border-box;min-height:90px}
  .note-block textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(47,91,234,.15)}
</style>

<div class="sheet" id="sheet-hold" style="max-height:85%">
  <h3>Hold this job</h3>
  <form method="POST" action="{{ route('technician.jobs.hold', $job) }}" id="hold-form">@csrf
    @php
      $reasons = \App\Models\HoldReason::where('is_active', true)->orderBy('label')->get();
    @endphp

    @forelse ($reasons as $r)
      <label class="hold-opt">
        <input type="radio" name="hold_reason_id" value="{{ $r->id }}"
               data-requires-note="{{ $r->requires_note ? '1' : '0' }}"
               onchange="toggleHoldNote()" required>
        <div>
          <div class="t">{{ $r->label }}</div>
          <div class="s">{{ $r->pauses_sla ? 'Pauses the SLA' : 'Does not pause the SLA' }}</div>
        </div>
      </label>
    @empty
      <div style="padding:14px;color:var(--muted);font-size:13.5px">No hold reasons configured.</div>
    @endforelse

    <div class="note-block" id="hold-note-block">
      <label for="hold-note">Note <span style="color:var(--red)">*</span></label>
      <textarea id="hold-note" name="note" placeholder="Add any detail that helps the office follow up."></textarea>
    </div>

    <div class="sheet-actions" style="margin-top:18px">
      <button type="button" class="btn btn-ghost" onclick="closeSheets()">Cancel</button>
      <button type="submit" class="btn btn-primary">Hold job</button>
    </div>
  </form>
</div>

<script>
function toggleHoldNote() {
  const picked = document.querySelector('input[name="hold_reason_id"]:checked');
  const block  = document.getElementById('hold-note-block');
  const field  = document.getElementById('hold-note');
  const needs  = picked && picked.dataset.requiresNote === '1';
  block.classList.toggle('show', needs);
  field.required = needs;
  if (! needs) field.value = '';
}
</script>