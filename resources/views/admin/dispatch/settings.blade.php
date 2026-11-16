@extends('layouts.admin')
@section('title', 'Dispatch settings')

@section('content')
<style>
  .dsp-wrap{max-width:640px}
  .dsp-wrap h1{margin:0 0 4px;font-size:22px}
  .dsp-wrap > p{margin:0 0 16px;color:var(--muted);font-size:13px}
  .dsp-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px}
  .dsp-row{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:10px 0;border-bottom:1px solid var(--line)}
  .dsp-row:last-of-type{border-bottom:0}
  .dsp-row label{font-size:13px;font-weight:600}
  .dsp-row small{display:block;color:var(--muted);font-weight:400;font-size:12px;margin-top:2px}
  .dsp-in{width:90px;font-size:13px;padding:9px 12px;border-radius:10px;border:1px solid var(--line);text-align:right}
  .dsp-total{font-size:12px;color:var(--muted);margin:10px 0 0}
  .dsp-err{color:var(--red);font-size:12px;font-weight:600;margin-top:6px}
  .dsp-ok{background:var(--green-soft);color:var(--green);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:14px}
  .dsp-btn{margin-top:16px;background:var(--amber);color:#fff;border:0;border-radius:10px;padding:10px 20px;font-weight:600;font-size:13px;cursor:pointer}
  .dsp-sub{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:18px 0 4px}
</style>

<div class="dsp-wrap">
  <h1>Dispatch settings</h1>
  <p>How technicians are ranked when you assign a request. These are suggestions only; you can always choose anyone.</p>

  @if (session('dispatch_saved'))<div class="dsp-ok">{{ session('dispatch_saved') }}</div>@endif

  <form method="POST" action="{{ route('dispatch.settings.update') }}" class="dsp-card">
    @csrf @method('PUT')

    <div class="dsp-sub" style="margin-top:0">Ranking weights</div>
    @php
      $fields = [
        'w_skill'        => ['Skill match', 'Has the skill for the request category.'],
        'w_availability' => ['Availability', 'No other job near the chosen arrival time.'],
        'w_workload'     => ['Workload', 'Fewer scheduled minutes in the next 7 days.'],
        'w_proximity'    => ['Proximity', 'Closer to the site, from the last punch or home base.'],
        'w_first_fix'    => ['First-time fix', 'Share of finished jobs with no rework.'],
      ];
    @endphp
    @foreach ($fields as $name => [$label, $help])
      <div class="dsp-row">
        <label for="{{ $name }}">{{ $label }}<small>{{ $help }}</small></label>
        <input class="dsp-in dsp-w" type="number" min="0" max="100" id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $s->$name) }}" required>
      </div>
      @error($name)<div class="dsp-err">{{ $message }}</div>@enderror
    @endforeach
    <div class="dsp-total">Weights add up to <strong id="dsp-sum">0</strong>. They do not have to add up to 100; each is counted in proportion.</div>

    <div class="dsp-sub">Workload</div>
    <div class="dsp-row">
      <label for="job_minutes">Minutes counted for each scheduled job<small>Used for workload and for clash checks. Default 120.</small></label>
      <input class="dsp-in" type="number" min="15" max="1440" id="job_minutes" name="job_minutes" value="{{ old('job_minutes', $s->job_minutes) }}" required>
    </div>
    @error('job_minutes')<div class="dsp-err">{{ $message }}</div>@enderror

    <button class="dsp-btn" type="submit">Save settings</button>
  </form>
</div>

<script>
(function () {
  const sum = document.getElementById('dsp-sum');
  const inputs = document.querySelectorAll('.dsp-w');
  function update() {
    let t = 0; inputs.forEach(i => t += parseInt(i.value || '0', 10) || 0);
    sum.textContent = t;
  }
  inputs.forEach(i => i.addEventListener('input', update));
  update();
})();
</script>
@endsection
