@extends('layouts.admin')
@section('title', $quote->quote_number)

@section('content')
<style>
  .crumb{color:var(--muted);font-size:13px;margin-bottom:4px}
  .crumb a{color:var(--ink);text-decoration:underline}
  h1.q{font-family:'Figtree';font-size:28px;font-weight:800;margin-bottom:6px}
  .sr-sub{color:var(--muted);font-size:13.5px;margin-bottom:22px}
  .pill-oc{display:inline-block;background:#F0EFF5;color:#5A5869;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:700;margin-left:6px}
  .card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px}
  .fld{margin-bottom:18px}
  .fld label{display:block;font-family:'Figtree';font-size:14px;font-weight:700;margin-bottom:6px}
  .fld .req{color:#D22E2E}
  .fld input, .fld select, .fld textarea{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:10px;font-family:inherit;font-size:14px;background:#fff;outline:none}
  .fld input:focus, .fld select:focus, .fld textarea:focus{border-color:#2F5BEA;box-shadow:0 0 0 3px rgba(47,91,234,.15)}

  .lines-head{display:grid;grid-template-columns:1fr 90px 90px 120px 140px 90px 32px;gap:10px;padding:0 2px 8px;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--line)}
  .line-row{display:grid;grid-template-columns:1fr 90px 90px 120px 140px 90px 32px;gap:10px;align-items:flex-start;padding:12px 2px;border-bottom:1px solid #F2F1F6}
  .line-row .cell{display:flex;flex-direction:column;gap:3px}
  .line-row input, .line-row select{padding:10px 12px;border:1px solid var(--line);border-radius:10px;font-family:inherit;font-size:14px;background:#fff;width:100%}
  .line-row input.has-err, .line-row select.has-err{border-color:#C2381B;background:#FFF7F7}
  .line-row input.has-err:focus{box-shadow:0 0 0 3px rgba(194,56,27,.15)}
  .line-err{color:#C2381B;font-size:11.5px;font-weight:600;line-height:1.3;margin-top:1px}
  .line-row .line-total{text-align:right;font-weight:600;padding-top:12px}
  .line-row .btn-rm{align-self:flex-start;margin-top:4px}

  .btn-rm{background:#fff;border:1px solid var(--line);border-radius:50%;width:28px;height:28px;cursor:pointer;color:var(--muted)}
  .btn-rm:hover{background:#FDECE7;color:#C2381B;border-color:#F2C2C2}
  .btn-add{margin-top:16px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px 16px;font-weight:700;font-family:inherit;font-size:13.5px;cursor:pointer;display:inline-flex;gap:6px;align-items:center}
  .totals{display:flex;justify-content:flex-end;margin-top:20px}
  .totals table{min-width:300px;font-size:14px}
  .totals td{padding:6px 0}
  .totals .big{font-family:'Figtree';font-weight:800;font-size:16px;border-top:1px solid var(--line);padding-top:10px}
  .footer-btns{display:flex;gap:10px;margin-top:22px}
  .btn{padding:12px 22px;border-radius:12px;font-weight:700;text-decoration:none;font-family:inherit;font-size:14px;cursor:pointer;border:1px solid var(--line);background:#fff;color:var(--ink)}
  .btn-primary{background:#2F5BEA;color:#fff;border-color:#2F5BEA}
  .err{color:#C2381B;font-size:12px;margin-top:4px}

  .err-summary{background:#FDECE7;border:1px solid #F2C2C2;color:#C2381B;padding:14px 16px;border-radius:12px;margin-bottom:16px;font-size:13.5px}
  .err-summary strong{display:block;margin-bottom:4px}
  .err-summary ul{margin:4px 0 0 18px;padding:0}
</style>

<div class="crumb"><a href="{{ route('admin.requests-review.index') }}">Quotes</a> / {{ $quote->quote_number }}</div>
<h1 class="q">Quote {{ $quote->quote_number }}</h1>
<div class="sr-sub">
  {{ $sr->sr_number }} {{ $sr->title }}<br>
  {{ $sr->client?->name }}, {{ $sr->site?->name }} <span class="pill-oc">Out of coverage</span>
</div>

<form method="POST" action="{{ route('admin.quotes.update', $quote) }}" id="qform">
  @csrf @method('PUT')

  {{-- ===== ERROR SUMMARY ===== --}}


  <div class="card">
    <div class="fld" style="max-width:260px">
      <label>Valid until <span class="req">*</span></label>
      <input type="date" name="valid_until"
             value="{{ old('valid_until', optional($quote->valid_until)->format('Y-m-d')) }}"
             required
             class="@error('valid_until') has-err @enderror">
      @error('valid_until')<div class="err">{{ $message }}</div>@enderror
    </div>

    <div class="lines-head">
      <div>Description </div>
      <div>Quantity</div>
      <div>Unit</div>
      <div>Unit rate</div>
      <div>Tax rate</div>
      <div style="text-align:right">Line total</div>
      <div></div>
    </div>

    <div id="lines">
      @php
        $existingLines = $quote->items->isNotEmpty()
          ? $quote->items
          : collect([(object)[
              'description'=>'','quantity'=>1,'unit'=>'',
              'unit_rate'=>0,'tax_rate_id'=>null,'line_total'=>0
          ]]);
      @endphp

      @foreach ($existingLines as $i => $ln)
        <div class="line-row">
          {{-- Description --}}
          <div class="cell">
            <input type="text"
                   name="lines[{{ $i }}][description]"
                   value="{{ old('lines.'.$i.'.description', $ln->description) }}"
                   placeholder="Description"
                    minlength="2" maxlength="255"
                   class="@error('lines.'.$i.'.description') has-err @enderror">
            @error('lines.'.$i.'.description')
              <div class="line-err">{{ $message }}</div>
            @enderror
          </div>

          {{-- Quantity --}}
          <div class="cell">
            <input type="number" step="0.01" min="0.01"
                   name="lines[{{ $i }}][quantity]"
                   value="{{ old('lines.'.$i.'.quantity', $ln->quantity) }}"
                   placeholder="1"
                   
                   class="qty @error('lines.'.$i.'.quantity') has-err @enderror">
            @error('lines.'.$i.'.quantity')
              <div class="line-err">{{ $message }}</div>
            @enderror
          </div>

          {{-- Unit (optional) --}}
          <div class="cell">
            <input type="text"
                   name="lines[{{ $i }}][unit]"
                   value="{{ old('lines.'.$i.'.unit', $ln->unit) }}"
                   placeholder="pc">
          </div>

          {{-- Unit rate --}}
          <div class="cell">
            <input type="number" step="0.01" min="0"
                   name="lines[{{ $i }}][unit_rate]"
                   value="{{ old('lines.'.$i.'.unit_rate', $ln->unit_rate) }}"
                   placeholder="0.00"
                   class="rate @error('lines.'.$i.'.unit_rate') has-err @enderror">
            @error('lines.'.$i.'.unit_rate')
              <div class="line-err">{{ $message }}</div>
            @enderror
          </div>

          {{-- Tax rate --}}
          <div class="cell">
            <select name="lines[{{ $i }}][tax_rate_id]" class="tax">
              <option value="">No tax</option>
              @foreach ($taxRates as $tr)
                <option value="{{ $tr->id }}"
                        data-rate="{{ $tr->rate_percent }}"
                        @selected(old('lines.'.$i.'.tax_rate_id', $ln->tax_rate_id) == $tr->id)>
                  {{ $tr->name }} {{ rtrim(rtrim(number_format($tr->rate_percent,2),'0'),'.') }}%
                </option>
              @endforeach
            </select>
          </div>

          <div class="line-total">0.00</div>
          <button type="button" class="btn-rm" onclick="rmLine(this)">×</button>
        </div>
      @endforeach
    </div>

    <button type="button" class="btn-add" onclick="addLine()">+ Add line</button>

    <div class="fld" style="margin-top:22px;max-width:200px">
      <label>Discount</label>
      <select name="discount_type" id="dtype">
        <option value="none"    @selected($quote->discount_type==='none')>None</option>
        <option value="percent" @selected($quote->discount_type==='percent')>Percent %</option>
        <option value="amount"  @selected($quote->discount_type==='amount')>Amount (AED)</option>
      </select>
      <input type="number" step="0.01" min="0" name="discount_value" value="{{ $quote->discount_value }}" id="dval" style="margin-top:8px" placeholder="0">
    </div>

    <div class="totals">
      <table>
        <tr><td style="color:var(--muted)">Subtotal</td><td style="text-align:right"><span id="t-sub">AED 0.00</span></td></tr>
        <tr><td style="color:var(--muted)">Tax</td><td style="text-align:right"><span id="t-tax">AED 0.00</span></td></tr>
        <tr><td style="color:var(--muted)">Discount</td><td style="text-align:right"><span id="t-disc">AED 0.00</span></td></tr>
        <tr><td class="big">Total</td><td class="big" style="text-align:right"><span id="t-total">AED 0.00</span></td></tr>
      </table>
    </div>

    <div class="fld" style="margin-top:22px">
      <label>Terms</label>
      <textarea name="terms" rows="3">{{ old('terms', $quote->terms) }}</textarea>
    </div>

    <div class="footer-btns">
      <button type="submit" class="btn">Save Draft</button>
      <a href="{{ route('admin.quotes.pdf', $quote) }}" target="_blank" class="btn">Preview PDF</a>
      <button type="submit" class="btn btn-primary" name="and_send" value="1">Send Quote</button>
    </div>
  </div>
</form>

<script>
let lineIdx = {{ $existingLines->count() }};

function addLine() {
  const first = document.querySelector('.line-row');
  const tpl = first.cloneNode(true);

  // Reindex every name attribute
  tpl.querySelectorAll('[name]').forEach(el => {
    el.name = el.name.replace(/lines\[\d+\]/, `lines[${lineIdx}]`);

    if (el.tagName === 'SELECT') {
      el.selectedIndex = 0;
    } else if (el.type === 'number') {
      el.value = el.classList.contains('qty') ? 1 : 0;
    } else {
      el.value = '';
    }
    el.classList.remove('has-err');
  });

  // Strip any inline error messages that were cloned
  tpl.querySelectorAll('.line-err').forEach(e => e.remove());
  tpl.querySelector('.line-total').textContent = '0.00';

  document.getElementById('lines').appendChild(tpl);
  lineIdx++;
  recalc();
}

function rmLine(btn) {
  const rows = document.querySelectorAll('.line-row');
  if (rows.length <= 1) return;
  btn.closest('.line-row').remove();
  recalc();
}

function recalc() {
  let sub = 0, tax = 0;
  document.querySelectorAll('.line-row').forEach(r => {
    const q = parseFloat(r.querySelector('.qty').value) || 0;
    const u = parseFloat(r.querySelector('.rate').value) || 0;
    const t = r.querySelector('.tax');
    const rate = t.selectedIndex > 0 ? parseFloat(t.options[t.selectedIndex].dataset.rate) || 0 : 0;
    const line = q * u;
    sub += line;
    tax += line * rate / 100;
    r.querySelector('.line-total').textContent = line.toFixed(2);
  });
  const dtype = document.getElementById('dtype').value;
  const dval = parseFloat(document.getElementById('dval').value) || 0;
  let disc = 0;
  if (dtype === 'percent') disc = sub * dval / 100;
  if (dtype === 'amount')  disc = dval;
  const total = Math.max(0, sub + tax - disc);
  document.getElementById('t-sub').textContent = 'AED ' + sub.toFixed(2);
  document.getElementById('t-tax').textContent = 'AED ' + tax.toFixed(2);
  document.getElementById('t-disc').textContent = 'AED ' + disc.toFixed(2);
  document.getElementById('t-total').textContent = 'AED ' + total.toFixed(2);
}

document.addEventListener('input', e => {
  if (e.target.closest('.line-row') || e.target.id === 'dtype' || e.target.id === 'dval') recalc();
});
document.addEventListener('change', e => {
  if (e.target.classList.contains('tax') || e.target.id === 'dtype') recalc();
});

// Clear red border on a field once the user starts typing in it
document.addEventListener('input', e => {
  if (e.target.classList.contains('has-err')) e.target.classList.remove('has-err');
});

recalc();
</script>
@endsection