@extends('layouts.admin')
@section('title', 'Business hours and holidays')
@section('hide_layout_errors', true)

@section('content')
<style>
  .page-head h1{font-family:'Figtree';font-size:34px;font-weight:800;line-height:1.08;letter-spacing:-.02em;margin:0;color:#0F0F17}
  .page-head p{color:#5F5D6E;font-size:14px;margin-top:6px;margin-bottom:22px}

  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:'DM Sans';font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:all .12s}
  .btn-primary{background:#2F5BEA;color:#fff}
  .btn-primary:hover{background:#2749C8}
  .btn-ghost{background:#fff;color:#0F0F17;border-color:#E5E4EC}
  .btn-mini{padding:7px 14px;font-size:12.5px;font-weight:700;border-radius:10px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;cursor:pointer;font-family:'DM Sans'}
  .btn-mini:hover{border-color:#0F0F17}
  .btn-mini-danger{padding:7px 14px;font-size:12.5px;font-weight:700;border-radius:10px;border:1px solid #F0C7C7;background:#fff;color:#C7372F;cursor:pointer;font-family:'DM Sans'}

  .card{background:#fff;border:1px solid #E5E4EC;border-radius:16px;padding:22px;margin-bottom:16px}
  .card h3{font-family:'Figtree';font-size:17px;font-weight:700;margin:0 0 16px;color:#0F0F17}

  /* ---- Weekly hours grid ---- */
  .wh-head{display:grid;grid-template-columns:140px 100px 1fr 160px;gap:16px;padding:0 4px 10px;font-size:12.5px;font-weight:600;color:#5F5D6E;border-bottom:1px solid #EEEDF3}
  .wh-row{display:grid;grid-template-columns:140px 100px 1fr 160px;gap:16px;align-items:center;padding:14px 4px;border-bottom:1px solid #F2F1F6}
  .wh-row:last-of-type{border-bottom:0}
  .wh-day{font-weight:700;font-size:14px;color:#0F0F17}
  .wh-row.is-closed .wh-day{color:#5F5D6E}

  /* Toggle switch (blue) */
  .sw{position:relative;display:inline-block;width:40px;height:22px;cursor:pointer}
  .sw input{opacity:0;width:0;height:0}
  .sw .slider{position:absolute;inset:0;background:#D1CFDA;border-radius:999px;transition:.15s}
  .sw .slider::after{content:'';position:absolute;top:3px;left:3px;width:16px;height:16px;background:#fff;border-radius:50%;transition:.15s;box-shadow:0 1px 2px rgba(0,0,0,.2)}
  .sw input:checked + .slider{background:#2F5BEA}
  .sw input:checked + .slider::after{transform:translateX(18px)}

  .wh-times{display:flex;align-items:center;gap:10px}
  .wh-times .to{color:#5F5D6E;font-size:13px}
  .time-input{font-family:'DM Sans';font-size:14px;padding:9px 12px;border-radius:10px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;outline:none;font-variant-numeric:tabular-nums;min-width:120px}
  .time-input:focus{border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.15)}
  .closed-text{color:#5F5D6E;font-size:14px;font-style:italic}

  .allday-chk{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13.5px;font-weight:600;color:#0F0F17;user-select:none}
  .allday-chk input{appearance:none;width:17px;height:17px;border:1.5px solid #D1CFDA;border-radius:5px;cursor:pointer;position:relative;flex-shrink:0;background:#fff}
  .allday-chk input:checked{background:#2F5BEA;border-color:#2F5BEA}
  .allday-chk input:checked::after{content:'';position:absolute;left:5px;top:1px;width:4px;height:9px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}

  /* ---- Holidays table ---- */
  .hol-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px}
  .hol-head-right{display:flex;gap:10px;align-items:center}
  .year-sel{appearance:none;font-family:'DM Sans';font-size:13px;font-weight:600;padding:8px 32px 8px 14px;border-radius:10px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235F5D6E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}

  .hol-row{display:grid;grid-template-columns:120px 1fr auto;gap:16px;align-items:center;padding:14px 4px;border-bottom:1px solid #F2F1F6}
  .hol-row:last-child{border-bottom:0}
  .hol-date{font-weight:600;color:#0F0F17;font-size:14px;font-variant-numeric:tabular-nums}
  .hol-name{color:#0F0F17;font-size:14px}
  .hol-acts{display:flex;gap:8px}

  .empty-hol{padding:40px 20px;text-align:center;color:#5F5D6E;font-size:13.5px}

  .swal-form{display:flex;flex-direction:column;gap:12px;text-align:left}
  .swal-form label{font-size:12.5px;font-weight:600;color:#0F0F17;margin-bottom:4px;display:block}
  .swal-form input{font-family:'DM Sans';font-size:14px;padding:10px 13px;border-radius:9px;border:1px solid #E5E4EC;background:#fff;color:#0F0F17;width:100%;outline:none;box-sizing:border-box}
  .swal-form input:focus{border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.15)}
  .hours-cell{display:flex;align-items:center;min-height:38px}
</style>

<div class="page-head">
  <h1>Business hours and holidays</h1>
  <p>Hours and holidays count only for SLA policies that use business hours. Time zone: {{ config('app.timezone') }}.</p>
</div>

{{-- ===================== WEEKLY HOURS ===================== --}}
<form method="POST" action="{{ route('admin.tenant-business-hours.update') }}" id="bh-form">
  @csrf @method('PUT')

  <div class="card">
    <h3>Weekly hours</h3>

    <div class="wh-head">
      <div>Day</div>
      <div>Open</div>
      <div>Hours</div>
      <div></div>
    </div>

    @php $order = [1, 2, 3, 4, 5, 6, 0]; @endphp

    @foreach ($order as $wd)
      @php
        $row = $hours[$wd] ?? null;
        if (!$row) continue;
        $isOpen = old("hours.$wd.is_open", $row->is_open) ? true : false;
        $opens  = old("hours.$wd.opens_at",  optional($row->opens_at)->format('H:i') ?? '09:00');
        $closes = old("hours.$wd.closes_at", optional($row->closes_at)->format('H:i') ?? '18:00');
        $isAllDay = $opens === '00:00' && ($closes === '23:59' || $closes === '00:00');
      @endphp

     <div class="wh-row {{ $isOpen ? '' : 'is-closed' }}" data-wd="{{ $wd }}">
  <input type="hidden" name="hours[{{ $wd }}][weekday]" value="{{ $wd }}">
  <input type="hidden" name="hours[{{ $wd }}][is_open]" value="{{ $isOpen ? '1' : '0' }}" class="hidden-isopen">

  <div class="wh-day">{{ $weekdays[$wd] }}</div>

  <label class="sw">
    <input type="checkbox" class="toggle-open" {{ $isOpen ? 'checked' : '' }}>
    <span class="slider"></span>
  </label>

  {{-- Hours column — one of 3 states: times / "Open all day" / "Closed" --}}
  <div class="hours-cell">
    <div class="wh-times state-times" style="display:none">
      <input type="time" name="hours[{{ $wd }}][opens_at]"  value="{{ $opens }}"  class="time-input input-opens">
      <span class="to">to</span>
      <input type="time" name="hours[{{ $wd }}][closes_at]" value="{{ $closes }}" class="time-input input-closes">
    </div>
    <div class="closed-text state-allday" style="display:none">Open all day</div>
    <div class="closed-text state-closed" style="display:none">Closed</div>
  </div>

  {{-- "Open all day" checkbox (hidden when toggle is off) --}}
  <label class="allday-chk" style="display:{{ $isOpen ? 'flex' : 'none' }}">
    <input type="checkbox" class="allday-input" {{ $isAllDay ? 'checked' : '' }}>
    Open all day
  </label>
</div>
    @endforeach

    <div style="margin-top:20px">
      <button type="submit" class="btn btn-primary">Save hours</button>
    </div>
  </div>
</form>

{{-- ===================== HOLIDAYS ===================== --}}
<div class="card" id="holidays">
  <div class="hol-head">
    <h3 style="margin:0">Holidays</h3>
    <div class="hol-head-right">
      <select class="year-sel" onchange="window.location = '?year=' + this.value">
        @foreach ($years as $y)
          <option value="{{ $y }}" @selected((int) $year === (int) $y)>{{ $y }}</option>
        @endforeach
      </select>
      <button type="button" class="btn btn-primary" onclick="openHolidayModal()">+ Add holiday</button>
    </div>
  </div>

  @forelse ($holidays as $h)
    <div class="hol-row">
      <div class="hol-date">{{ $h->holiday_date->format('d M Y') }}</div>
      <div class="hol-name">{{ $h->name }}</div>
      <div class="hol-acts">
        <button type="button" class="btn-mini"
                onclick='openHolidayModal(@json(["id"=>$h->id,"name"=>$h->name,"holiday_date"=>$h->holiday_date->format("Y-m-d")]))'>Edit</button>
        <form method="POST" action="{{ route('admin.tenant-holidays.destroy', $h) }}" style="margin:0" class="needs-confirm"
              data-confirm-title="Remove this holiday?" data-confirm-ok="Yes, remove" data-confirm-ok-color="red">
          @csrf @method('DELETE')
          <button type="submit" class="btn-mini-danger">Remove</button>
        </form>
      </div>
    </div>
  @empty
    <div class="empty-hol">No holidays recorded for {{ $year }}.</div>
  @endforelse
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
<script>
// ===== Weekly hours UI =====
(function () {
  document.querySelectorAll('.wh-row').forEach(row => {
    const openInput    = row.querySelector('.toggle-open');
    const hiddenIsOpen = row.querySelector('.hidden-isopen');
    const stateTimes   = row.querySelector('.state-times');
    const stateAllday  = row.querySelector('.state-allday');
    const stateClosed  = row.querySelector('.state-closed');
    const alldayWrap   = row.querySelector('.allday-chk');
    const allChk       = row.querySelector('.allday-input');
    const oIn          = row.querySelector('.input-opens');
    const cIn          = row.querySelector('.input-closes');

    function render() {
      const isOpen   = openInput.checked;
      const isAllDay = allChk.checked;

      row.classList.toggle('is-closed', !isOpen);
      hiddenIsOpen.value = isOpen ? '1' : '0';

      stateTimes.style.display  = 'none';
      stateAllday.style.display = 'none';
      stateClosed.style.display = 'none';

      if (!isOpen)       stateClosed.style.display = 'block';
      else if (isAllDay) stateAllday.style.display = 'block';
      else               stateTimes.style.display  = 'flex';

      alldayWrap.style.display = isOpen ? 'flex' : 'none';
    }

    openInput.addEventListener('change', render);
    allChk.addEventListener('change', () => {
      if (allChk.checked) { oIn.value = '00:00'; cIn.value = '23:59'; }
      else                { oIn.value = oIn.value || '09:00'; cIn.value = cIn.value || '18:00'; }
      render();
    });

    render();
  });
})();

// ===== Holiday modal (add/edit) =====
const HOL_URLS = {
  store:  @json(route('admin.tenant-holidays.store')),
  update: id => `{{ url('admin/tenant-holidays') }}/${id}`,
};
const CSRF = @json(csrf_token());

function openHolidayModal(h = null) {
  const edit = !!h?.id;
  const esc  = s => (s ?? '').toString().replace(/&/g,'&amp;').replace(/"/g,'&quot;');

  Swal.fire({
    title: edit ? 'Edit holiday' : 'Add holiday',
    html: `
      <div class="swal-form">
        <div><label>Name *</label><input id="h-name" type="text" value="${esc(h?.name)}" placeholder="e.g. National Day"></div>
        <div><label>Date *</label><input id="h-date" type="date" value="${esc(h?.holiday_date)}"></div>
      </div>`,
    showCancelButton: true, confirmButtonText: 'Save', cancelButtonText: 'Cancel',
    reverseButtons: true, focusConfirm: false,
    didOpen: () => document.getElementById('h-name').focus(),
    preConfirm: () => {
      const name = document.getElementById('h-name').value.trim();
      const date = document.getElementById('h-date').value;
      if (!name) { Swal.showValidationMessage('Name is required'); return false; }
      if (!date) { Swal.showValidationMessage('Date is required'); return false; }
      return { name, holiday_date: date };
    }
  }).then(async r => {
    if (!r.isConfirmed) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = edit ? HOL_URLS.update(h.id) : HOL_URLS.store;
    form.innerHTML = `
      <input type="hidden" name="_token" value="${CSRF}">
      ${edit ? '<input type="hidden" name="_method" value="PUT">' : ''}
      <input type="hidden" name="name" value="${esc(r.value.name)}">
      <input type="hidden" name="holiday_date" value="${esc(r.value.holiday_date)}">
    `;
    document.body.appendChild(form);
    form.submit();
  });
}

// ===== Success toast =====
@if (session('success'))
  Swal.fire({
    toast: true, position: 'top-end', icon: 'success',
    title: @json(session('success')),
    showConfirmButton: false, timer: 2500
  });
@endif

// ===== Re-open the holiday modal when the save failed validation =====
@if ($errors->any() && (old('name') || old('holiday_date')))
  document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
      icon: 'error',
      title: 'Could not save holiday',
      text: @json($errors->first()),
      confirmButtonText: 'Fix it'
    }).then(() => {
      openHolidayModal({
        name: @json(old('name')),
        holiday_date: @json(old('holiday_date')),
      });
      document.getElementById('holidays')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
@endif
</script>

@endsection