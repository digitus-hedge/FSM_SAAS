<style>
  .sheet-bd{position:absolute;inset:0;background:rgba(0,0,0,.5);z-index:60;opacity:0;pointer-events:none;transition:opacity .2s;border-radius:28px}
.sheet-bd.open{opacity:1;pointer-events:auto}
@media (max-width:600px){.sheet-bd{border-radius:0}}

.sheet{position:absolute;left:0;right:0;bottom:0;z-index:70;background:#fff;border-top-left-radius:24px;border-top-right-radius:24px;transform:translateY(100%);transition:transform .22s;max-height:85%;overflow-y:auto;padding:20px 20px calc(20px + env(safe-area-inset-bottom,0px));box-shadow:0 -8px 24px rgba(0,0,0,.15)}
.sheet.open{transform:translateY(0)}
.sheet::before{content:'';display:block;width:36px;height:4px;background:#D1CFDA;border-radius:999px;margin:0 auto 14px}
  .sheet h3{font-family:'Figtree';font-size:19px;font-weight:800;margin-bottom:14px}
  .sheet label{display:block;font-size:12.5px;font-weight:700;color:var(--ink);margin-bottom:6px}
  .sheet input,.sheet textarea{width:100%;font-family:'DM Sans';font-size:14.5px;padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;margin-bottom:14px;box-sizing:border-box}
  .sheet input:focus,.sheet textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(47,91,234,.15)}
  .sheet-actions{display:flex;gap:10px}
</style>

<div class="sheet-bd" id="sheet-bd" onclick="closeSheets()"></div>

<div class="sheet" id="sheet-time">
  <h3>Change arrival time</h3>
  <form method="POST" action="{{ route('technician.jobs.change-time', $job) }}">@csrf
    <label>New arrival time</label>
    <input type="datetime-local" name="new_eta"
           value="{{ $job->scheduled_eta_at ? \Carbon\Carbon::parse($job->scheduled_eta_at)->format('Y-m-d\TH:i') : '' }}" required>
    <label>Reason</label>
    <textarea name="reason" rows="3"></textarea>
    <div class="sheet-actions">
      <button type="button" class="btn btn-ghost" onclick="closeSheets()">Cancel</button>
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</div>