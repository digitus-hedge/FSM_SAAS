@extends('layouts.admin')
@section('title', 'SLA policies')

@section('content')
<style>
  .page-head h1{font-family:'Figtree';font-size:34px;font-weight:800;line-height:1.08;letter-spacing:-.02em;margin:0;color:#0F0F17}
  .page-head p{color:#5F5D6E;font-size:14px;margin-top:6px;margin-bottom:22px;max-width:720px}

  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:'DM Sans';font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:all .12s}
  .btn-primary{background:#2F5BEA;color:#fff}
  .btn-primary:hover{background:#2749C8}

  .cat-sel{appearance:none;font-family:'DM Sans';font-size:14px;font-weight:600;padding:10px 36px 10px 16px;border-radius:10px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;cursor:pointer;min-width:220px;margin-bottom:16px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center}

  .card{background:#fff;border:1px solid #E5E4EC;border-radius:16px;overflow:hidden}

  .grid-head,.grid-row{display:grid;gap:16px;align-items:center}
  .grid-head{padding:16px 24px;font-size:13px;font-weight:600;color:#5F5D6E;border-bottom:1px solid #EEEDF3;background:#FAFAFC}
  .grid-row{padding:20px 24px;border-bottom:1px solid #F2F1F6}
  .grid-row:last-of-type{border-bottom:0}

  .pri-name{font-weight:700;font-size:14px;color:#0F0F17}
  .pri-default{font-size:12px;color:#5F5D6E;margin-top:3px}
  .pri-err{color:#C7372F;font-size:12.5px;font-weight:600;margin-top:6px;line-height:1.3}

  .dhm{display:flex;align-items:center;gap:8px}
  .dhm input{font-family:'DM Sans';font-size:14px;padding:10px 12px;border-radius:10px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;outline:none;width:64px;text-align:center;font-variant-numeric:tabular-nums;transition:opacity .12s}
  .dhm input:focus{border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.15)}
  .dhm input.err{border-color:#C7372F;background:#FFF7F7}
  .dhm input:disabled{background:#FAFAFC;color:#9A98A7}
  .dhm .unit{color:#5F5D6E;font-size:13px;font-weight:600}

  .bh-cell label{display:flex;align-items:flex-start;gap:10px;cursor:pointer;user-select:none}
  .bh-cell input[type=checkbox]{appearance:none;width:18px;height:18px;border:1.5px solid #D1CFDA;border-radius:5px;cursor:pointer;position:relative;flex-shrink:0;background:#fff;margin-top:1px}
  .bh-cell input:checked{background:#2F5BEA;border-color:#2F5BEA}
  .bh-cell input:checked::after{content:'';position:absolute;left:5px;top:1px;width:4px;height:9px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
  .bh-cell .bh-ttl{font-weight:700;font-size:13.5px;color:#0F0F17}
  .bh-cell .bh-sub{font-size:12px;color:#5F5D6E;margin-top:2px;display:block;max-width:180px;line-height:1.4}

  .foot-note{color:#5F5D6E;font-size:13px;padding:20px 24px;border-top:1px solid #EEEDF3;background:#FCFBFF}
  .actions{padding:16px 24px;background:#fff;border-top:1px solid #EEEDF3}
</style>

<div class="page-head">
  <h1>SLA policies</h1>
  <p>Response is met when the technician punches in on site. Resolution is met at the quality check.</p>
</div>

{{-- Category picker (reloads the grid) --}}
<select class="cat-sel" onchange="window.location = '?category_id=' + this.value">
  <option value="default" @selected($scope === 'default')>Company default</option>
  @foreach ($categories as $c)
    <option value="{{ $c->id }}" @selected((string) $scope === (string) $c->id)>{{ $c->name }} ({{ $c->code }})</option>
  @endforeach
</select>

@php
    $isCat = $categoryId !== null;
    // Columns differ between Company default (4 cols) and Category (5 cols with "Scope")
    $gridCols = $isCat ? '240px 150px 1fr 1fr 160px' : '160px 1fr 1fr 180px';

    // Pretty "Default: 1 hour / 4 hours" formatter
    $fmt = function ($mins) {
        if ($mins >= 1440 && $mins % 1440 === 0) return ($mins/1440).' '.\Illuminate\Support\Str::plural('day',  $mins/1440);
        if ($mins >= 60   && $mins % 60   === 0) return ($mins/60)  .' '.\Illuminate\Support\Str::plural('hour', $mins/60);
        return $mins.' min';
    };
@endphp

<form method="POST" action="{{ route('admin.sla-policies.save-all') }}">
  @csrf
  <input type="hidden" name="category_id" value="{{ $categoryId }}">

  <div class="card">
    <div class="grid-head" style="grid-template-columns: {{ $gridCols }}">
      <div>Priority</div>
      @if ($isCat) <div>Scope</div> @endif
      <div>Response</div>
      <div>Resolution</div>
      <div>Hours</div>
    </div>

    @foreach ($priorities as $key => $label)
      @php
        $row  = $editable[$key];
        $useD = (bool) old("rows.$key.use_default", $row['use_default']);
        $resp = $row['response_minutes'];
        $resn = $row['resolution_minutes'];

        $rD = intdiv($resp, 1440); $rH = intdiv($resp % 1440, 60); $rM = $resp % 60;
        $nD = intdiv($resn, 1440); $nH = intdiv($resn % 1440, 60); $nM = $resn % 60;

        $defaultLabel = $fmt($row['base_response']).' / '.$fmt($row['base_resolution']);
        $err = $errors->first("rows.$key");
      @endphp

      <div class="grid-row" style="grid-template-columns: {{ $gridCols }}">
        <div>
          <div class="pri-name">{{ $label }}</div>
          @if ($isCat)
            <div class="pri-default">Default: {{ $defaultLabel }}</div>
          @endif
          @if ($err)<div class="pri-err">{{ $err }}</div>@endif
        </div>

        @if ($isCat)
          <div class="bh-cell">
            <label>
              <input type="hidden" name="rows[{{ $key }}][use_default]" value="0">
              <input type="checkbox" class="use-default" name="rows[{{ $key }}][use_default]" value="1" @checked($useD)>
              <div class="bh-ttl">Use default</div>
            </label>
          </div>
        @endif

        <div class="dhm">
          <input type="number" name="rows[{{ $key }}][response_d]" min="0" max="365" value="{{ old("rows.$key.response_d", $rD) }}" class="val-input {{ $err ? 'err' : '' }}">
          <span class="unit">d</span>
          <input type="number" name="rows[{{ $key }}][response_h]" min="0" max="23"  value="{{ old("rows.$key.response_h", $rH) }}"  class="val-input {{ $err ? 'err' : '' }}">
          <span class="unit">h</span>
          <input type="number" name="rows[{{ $key }}][response_m]" min="0" max="59"  value="{{ old("rows.$key.response_m", $rM) }}"  class="val-input {{ $err ? 'err' : '' }}">
          <span class="unit">m</span>
        </div>

        <div class="dhm">
          <input type="number" name="rows[{{ $key }}][resolution_d]" min="0" max="365" value="{{ old("rows.$key.resolution_d", $nD) }}" class="val-input {{ $err ? 'err' : '' }}">
          <span class="unit">d</span>
          <input type="number" name="rows[{{ $key }}][resolution_h]" min="0" max="23"  value="{{ old("rows.$key.resolution_h", $nH) }}"  class="val-input {{ $err ? 'err' : '' }}">
          <span class="unit">h</span>
          <input type="number" name="rows[{{ $key }}][resolution_m]" min="0" max="59"  value="{{ old("rows.$key.resolution_m", $nM) }}"  class="val-input {{ $err ? 'err' : '' }}">
          <span class="unit">m</span>
        </div>

        <div class="bh-cell">
          <label>
            <input type="hidden" name="rows[{{ $key }}][uses_business_hours]" value="0">
            <input type="checkbox" name="rows[{{ $key }}][uses_business_hours]" value="1"
                   @checked(old("rows.$key.uses_business_hours", $row['uses_business_hours']))
                   onchange="toggleHint(this)">
            <div>
              <div class="bh-ttl">Business hours</div>
              <span class="bh-sub" style="display:{{ old("rows.$key.uses_business_hours", $row['uses_business_hours']) ? 'block' : 'none' }}">
                Only open time counts, so 1 day here means 24 open hours.
              </span>
            </div>
          </label>
        </div>
      </div>
    @endforeach

    <div class="foot-note">
      Contract SLAs, when they arrive, take priority over this table. A policy change affects only clocks that start afterwards.
    </div>

    <div class="actions">
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
<script>
function toggleHint(cb) {
  const sub = cb.closest('label').querySelector('.bh-sub');
  if (sub) sub.style.display = cb.checked ? 'block' : 'none';
}

// "Use default" greys out the row's d/h/m inputs
document.querySelectorAll('.use-default').forEach(cb => {
  const row = cb.closest('.grid-row');
  function sync() {
    row.querySelectorAll('.val-input').forEach(i => {
      i.disabled = cb.checked;
      i.style.opacity = cb.checked ? .5 : 1;
      if (cb.checked) i.value = 0;
    });
  }
  cb.addEventListener('change', sync);
  sync();
});

@if (session('success'))
  Swal.fire({toast:true,position:'top-end',icon:'success',title:@json(session('success')),showConfirmButton:false,timer:2500});
@endif
</script>
@endsection