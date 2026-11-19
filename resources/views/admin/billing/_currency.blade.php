{{--
    Currency picker and rate line for the quote and invoice builders (spec 5.2, 6.3, 6.4).
    Needs: $doc (Quote or Invoice), $base, $currencyOptions, $pickerAvailable, $rateMap, $docLocked, optional $currencyFixed.
    It sets window.billingCurrency, which the builder's totals script reads, and shows the base total under the totals
    (the element with id="base-total-line", if the builder has one).
    Fields posted: currency_code, and exchange_rate + rate_manual only when Finance used "Change rate".
--}}
@php
    use App\Services\Currency\Money;

    $docCur     = strtoupper($doc->currency_code ?: $base);
    $docRate    = Money::rate($doc->exchange_rate ?? 1);
    $locked     = (bool) ($docLocked ?? false);
    $fixedCur   = (bool) ($currencyFixed ?? false);
    $decimals   = collect($currencyOptions)->mapWithKeys(fn ($c) => [$c => Money::decimals($c)])->all();
    $decimals[$base] = Money::decimals($base);
    // The date of the stored rate, shown only when the document still carries that very rate.
    $mapRow     = $rateMap[$docCur] ?? null;
    $docRateDate = ($mapRow && Money::rate($mapRow['rate']) === $docRate) ? $mapRow['date'] : null;
    $showPicker = $pickerAvailable || $docCur !== $base;
@endphp

@if ($showPicker)
<div class="fld" id="cur-box" style="max-width:420px">
  <label>Currency</label>
  <select name="currency_code" id="cur-pick" @disabled($locked || $fixedCur)>
    @foreach ($currencyOptions as $code)
      <option value="{{ $code }}" @selected($docCur === $code)>{{ $code }}{{ $code === $base ? ' (base)' : '' }}</option>
    @endforeach
  </select>

  <div id="rate-line" style="font-size:13px;color:var(--muted);margin-top:6px"></div>

  <div id="rate-edit" style="display:none;margin-top:6px">
    <input type="number" step="0.00000001" min="0" name="exchange_rate" id="rate-input" disabled style="max-width:200px">
    <input type="hidden" name="rate_manual" value="1" id="rate-manual" disabled>
  </div>

  @if (! $locked)
    <button type="button" id="rate-change" class="btn ghost sm" style="display:none;margin-top:6px;width:auto">Change rate</button>
  @else
    <div style="font-size:12px;color:var(--muted);margin-top:6px">{{ \App\Services\Currency\DocumentCurrency::MSG_LOCKED }}</div>
  @endif

  @error('currency_code') <div class="err" style="color:var(--red);font-size:12px">{{ $message }}</div> @enderror
  @error('exchange_rate') <div class="err" style="color:var(--red);font-size:12px">{{ $message }}</div> @enderror
</div>
@endif

<script>
(function () {
  const base = @json($base);
  const decimals = @json($decimals);
  const rateMap = @json($rateMap);
  const locked = @json($locked);
  const st = {
    code: @json($docCur),
    rate: @json($docRate),
    source: @json($doc->rate_source ?: 'provider'),
    date: @json($docRateDate),
    manual: false,
  };

  function fmtRate(r) {
    const n = parseFloat(r);
    if (!isFinite(n)) return '';
    let s = n.toFixed(8).replace(/0+$/, '');
    const dot = s.indexOf('.');
    return dot < 0 ? s + '.0000' : (s.length - dot - 1 < 4 ? s + '0'.repeat(4 - (s.length - dot - 1)) : s);
  }

  const pick = document.getElementById('cur-pick');
  const line = document.getElementById('rate-line');
  const edit = document.getElementById('rate-edit');
  const input = document.getElementById('rate-input');
  const manual = document.getElementById('rate-manual');
  const change = document.getElementById('rate-change');

  const api = window.billingCurrency = {
    get code() { return st.code; },
    get dec() { return decimals[st.code] ?? 2; },
    get rate() { return st.code === base ? 1 : parseFloat(st.rate) || 0; },
    updateBase(total) {
      const el = document.getElementById('base-total-line');
      if (!el) return;
      if (st.code === base) { el.textContent = ''; return; }
      const bd = decimals[base] ?? 2;
      el.textContent = '≈ ' + (total * api.rate).toFixed(bd) + ' ' + base;
    },
  };

  function render() {
    if (!line) return;
    if (st.code === base) {
      line.textContent = ''; edit.style.display = 'none'; input.disabled = manual.disabled = true;
      if (change) change.style.display = 'none';
    } else if (!st.rate || parseFloat(st.rate) <= 0) {
      line.textContent = 'Enter the rate for ' + st.code + '.';
      edit.style.display = 'block'; input.disabled = manual.disabled = false; input.required = true;
      if (change) change.style.display = 'none';
    } else {
      line.textContent = '1 ' + st.code + ' = ' + fmtRate(st.rate) + ' ' + base + ' · ' +
        (st.manual || st.source === 'manual' ? 'entered by hand' : 'from provider' + (st.date ? ', ' + st.date : ''));
      if (!st.manual) { edit.style.display = 'none'; input.disabled = manual.disabled = true; input.required = false; }
      if (change && !locked) change.style.display = st.manual ? 'none' : 'inline-flex';
    }
    document.querySelectorAll('#dtype option[value=amount]').forEach(o => o.textContent = 'Amount (' + st.code + ')');
    if (typeof recalc === 'function') recalc(); else if (typeof recomputeAll === 'function') recomputeAll();
  }

  if (pick && !pick.disabled) {
    pick.addEventListener('change', () => {
      st.code = pick.value; st.manual = false;
      const row = rateMap[st.code];
      st.rate = row ? row.rate : ''; st.source = row ? row.source : 'provider'; st.date = row ? row.date : null;
      render();
    });
  }
  if (change) {
    change.addEventListener('click', () => {
      st.manual = true; input.value = st.rate; input.disabled = manual.disabled = false; edit.style.display = 'block';
      change.style.display = 'none';
      input.focus(); render();
    });
  }
  if (input) {
    input.addEventListener('input', () => { st.rate = input.value; render(); });
  }

  window.addEventListener('DOMContentLoaded', render);
  render();
})();
</script>
