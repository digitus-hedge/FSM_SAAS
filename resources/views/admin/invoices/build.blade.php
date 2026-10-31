@extends('layouts.admin')
@section('title', $invoice->invoice_number . ' — edit')
@section('content')
<style>
.crumb{color:var(--muted);font-size:13px;margin-bottom:10px}
.crumb a{color:var(--ink);text-decoration:none;font-weight:600}
.page-h1{font-family:'Figtree';font-size:24px;font-weight:800;margin-bottom:6px}
.page-sub{color:var(--muted);font-size:14px;margin-bottom:16px}
.build-grid{display:grid;grid-template-columns:1fr 320px;gap:16px}
@media (max-width:900px){.build-grid{grid-template-columns:1fr}}
.panel{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-bottom:16px}
.panel-h{padding:14px 18px;border-bottom:1px solid var(--line);font-family:'Figtree';font-weight:700;font-size:14px}
.panel-b{padding:16px 18px}
.fld{display:flex;flex-direction:column;gap:4px;margin-bottom:14px}
.fld label{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.fld input,.fld select,.fld textarea{height:42px;padding:0 14px;border:1px solid var(--line);border-radius:10px;font-family:inherit;font-size:14px;background:#fff}
.fld textarea{min-height:80px;padding:12px 14px;height:auto}
.fld input:focus,.fld select:focus,.fld textarea:focus{outline:none;border-color:#FFC61A;box-shadow:0 0 0 3px #FFF4CC}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.line-row{display:grid;grid-template-columns:1fr 70px 70px 100px 130px 100px 32px;gap:8px;align-items:center;padding:10px 0;border-top:1px solid var(--line)}
.line-row:first-of-type{border-top:0}
.line-row input,.line-row select{height:36px;padding:0 10px;border:1px solid var(--line);border-radius:8px;font-family:inherit;font-size:13px;font-variant-numeric:tabular-nums}
.line-row .line-total{text-align:right;font-weight:700;font-variant-numeric:tabular-nums}
.line-row .del{background:none;border:0;color:#C2381B;cursor:pointer;font-size:18px}
.line-headers{display:grid;grid-template-columns:1fr 70px 70px 100px 130px 100px 32px;gap:8px;padding:8px 0;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700}
.line-headers .r{text-align:right}
.tot-row{display:flex;justify-content:space-between;padding:8px 18px;font-size:14px;font-variant-numeric:tabular-nums}
.tot-row.sub{border-top:1px solid var(--line);padding-top:12px}
.tot-row.tot{font-family:'Figtree';font-weight:800;font-size:17px;border-top:2px solid var(--ink);padding:14px 18px}
.btn{display:inline-flex;align-items:center;gap:6px;background:#FFC61A;color:#1B1A24;font-weight:700;font-size:13px;padding:11px 20px;border-radius:999px;border:0;cursor:pointer;font-family:inherit;text-decoration:none;width:100%;justify-content:center}
.btn:hover{background:#F0B700}
.btn.dark{background:#1B1A24;color:#FBFAF6}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--ink);font-weight:600}
.btn.sm{padding:7px 14px;font-size:12px;width:auto}
.err{background:#FDECE7;border:1px solid #C2381B;color:#8A2718;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px}


.line-row{align-items:flex-start}
.line-row .cell{display:flex;flex-direction:column;gap:3px;min-width:0}
.line-row .cell input,
.line-row .cell select{width:100%}
.line-row input.has-err,
.line-row select.has-err{border-color:#C2381B;background:#FFF7F7}
.line-row input.has-err:focus{box-shadow:0 0 0 3px rgba(194,56,27,.15)}
.line-err{color:#C2381B;font-size:11.5px;font-weight:600;line-height:1.3;margin-top:1px}

</style>

<div class="crumb"><a href="{{ route('admin.invoices.index') }}">Invoices</a> / {{ $invoice->invoice_number }}</div>
<h1 class="page-h1">{{ $invoice->invoice_number }} · Edit</h1>
<p class="page-sub">{{ $sr->sr_number }} · {{ $sr->title ?? '' }} · {{ $invoice->client?->name }}</p>

@if ($errors->any())
  <div class="err">@foreach ($errors->all() as $e)<div>• {{ $e }}</div>@endforeach</div>
@endif

<form method="POST" action="{{ route('admin.invoices.update', $invoice) }}" id="inv-form">
  @csrf @method('PUT')
  <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

  <div class="build-grid">
    <div class="panel">
      <div class="panel-h">Line items</div>
      <div class="panel-b">
        <div class="line-headers">
          <div>Description</div><div class="r">Qty</div><div>Unit</div><div class="r">Rate</div><div>Tax rate</div><div class="r">Line total</div><div></div>
        </div>
       
        <div id="lines">
          @foreach (($invoice->items->isEmpty() ? collect([(object)['description'=>'','quantity'=>1,'unit'=>'','unit_rate'=>0,'tax_rate_id'=>null]]) : $invoice->items) as $i => $it)
           
          <!-- <div class="line-row">
              <input name="lines[{{ $i }}][description]" value="{{ old('lines.'.$i.'.description', $it->description) }}" required>
              <input name="lines[{{ $i }}][quantity]"    value="{{ old('lines.'.$i.'.quantity', $it->quantity) }}"    required style="text-align:right" oninput="recomputeAll()">
              <input name="lines[{{ $i }}][unit]"        value="{{ old('lines.'.$i.'.unit', $it->unit) }}">
              <input name="lines[{{ $i }}][unit_rate]"   value="{{ old('lines.'.$i.'.unit_rate', $it->unit_rate) }}"   required style="text-align:right" oninput="recomputeAll()">
              <select name="lines[{{ $i }}][tax_rate_id]" onchange="recomputeAll()">
                <option value="">— None —</option>
                @foreach ($taxRates as $tr)
                  <option value="{{ $tr->id }}" data-pct="{{ $tr->rate_percent }}" @selected((int) old('lines.'.$i.'.tax_rate_id', $it->tax_rate_id) === $tr->id)>{{ $tr->name }} ({{ $tr->rate_percent }}%)</option>
                @endforeach
              </select>
              <div class="line-total" data-total>0.000</div>
              <button type="button" class="del" onclick="this.closest('.line-row').remove();recomputeAll()">×</button>
            </div> -->


            <div class="line-row">
  {{-- Description --}}
  <div class="cell">
    <input type="text"
           name="lines[{{ $i }}][description]"
           value="{{ old('lines.'.$i.'.description', $it->description) }}"
           placeholder="Description"
            minlength="2" maxlength="255"
           class="@error('lines.'.$i.'.description') has-err @enderror">
    @error('lines.'.$i.'.description')
      <div class="line-err">{{ $message }}</div>
    @enderror
  </div>

  {{-- Quantity --}}
  <div class="cell">
    <input type="number"
           step="0.01" min="0.01"
           name="lines[{{ $i }}][quantity]"
           value="{{ old('lines.'.$i.'.quantity', $it->quantity) }}"
           placeholder="1"
           
           style="text-align:right"
           oninput="recomputeAll()"
           class="@error('lines.'.$i.'.quantity') has-err @enderror">
    @error('lines.'.$i.'.quantity')
      <div class="line-err">{{ $message }}</div>
    @enderror
  </div>

  {{-- Unit (optional) --}}
  <div class="cell">
    <input type="text"
           name="lines[{{ $i }}][unit]"
           value="{{ old('lines.'.$i.'.unit', $it->unit) }}"
           placeholder="pc">
  </div>

  {{-- Unit rate --}}
  <div class="cell">
    <input type="number"
           step="0.001" min="0"
           name="lines[{{ $i }}][unit_rate]"
           value="{{ old('lines.'.$i.'.unit_rate', $it->unit_rate) }}"
           placeholder="0.000"
           
           style="text-align:right"
           oninput="recomputeAll()"
           class="@error('lines.'.$i.'.unit_rate') has-err @enderror">
    @error('lines.'.$i.'.unit_rate')
      <div class="line-err">{{ $message }}</div>
    @enderror
  </div>

  {{-- Tax rate --}}
  <div class="cell">
    <select name="lines[{{ $i }}][tax_rate_id]" onchange="recomputeAll()">
      <option value="">— None —</option>
      @foreach ($taxRates as $tr)
        <option value="{{ $tr->id }}" data-pct="{{ $tr->rate_percent }}"
                @selected((int) old('lines.'.$i.'.tax_rate_id', $it->tax_rate_id) === $tr->id)>
          {{ $tr->name }} ({{ $tr->rate_percent }}%)
        </option>
      @endforeach
    </select>
  </div>

  <div class="line-total" data-total style="padding-top:12px">0.000</div>

  <button type="button" class="del" onclick="this.closest('.line-row').remove();recomputeAll()" style="align-self:flex-start;margin-top:4px">×</button>
</div>


          @endforeach
        </div>
        <button type="button" class="btn ghost sm" style="margin-top:12px" onclick="addLine()">+ Add line</button>
      </div>
    </div>

    <div>
      <div class="panel">
        <div class="panel-h">Invoice settings</div>
        <div class="panel-b">
          <div class="fld"><label>Due date</label><input type="date" name="due_date" value="{{ old('due_date', $invoice->due_date?->format('Y-m-d') ?? now()->addDays(20)->format('Y-m-d')) }}" required></div>
          <div class="fld"><label>Legal invoice # (optional)</label><input name="legal_invoice_number" value="{{ old('legal_invoice_number', $invoice->legal_invoice_number) }}" placeholder="e.g. 2026/0892"></div>
          <div class="row2">
            <div class="fld"><label>Discount type</label>
              <select name="discount_type" onchange="recomputeAll()">
                <option value="none"    @selected(old('discount_type', $invoice->discount_type)==='none')>None</option>
                <option value="percent" @selected(old('discount_type', $invoice->discount_type)==='percent')>Percent</option>
                <option value="amount"  @selected(old('discount_type', $invoice->discount_type)==='amount')>Amount</option>
              </select>
            </div>
            <div class="fld"><label>Value</label><input name="discount_value" value="{{ old('discount_value', $invoice->discount_value) }}" style="text-align:right" oninput="recomputeAll()"></div>
          </div>
          <div class="fld"><label>Notes</label><textarea name="notes" maxlength="2000">{{ old('notes', $invoice->notes) }}</textarea></div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-h">Totals</div>
        <div class="tot-row sub"><span>Subtotal</span><span>AED <span id="tot-sub">0.000</span></span></div>
        <div class="tot-row"><span>Discount</span><span>— <span id="tot-disc">0.000</span></span></div>
        <div class="tot-row"><span>Tax</span><span>AED <span id="tot-tax">0.000</span></span></div>
        <div class="tot-row tot"><span>Total</span><span>AED <span id="tot-tot">0.000</span></span></div>
      </div>

      <div style="display:flex;flex-direction:column;gap:8px">
        <button type="submit" class="btn">Save draft</button>
        <button type="submit" class="btn dark" name="and_submit" value="1">Save &amp; submit for approval</button>
        <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn ghost">Cancel</a>
      </div>
    </div>
  </div>
</form>

<script>
let lineIdx = {{ max(1, $invoice->items->count()) }};
function addLine() {
  const taxOpts = @json($taxRates->map(fn($t) => ['id'=>$t->id,'name'=>$t->name,'pct'=>$t->rate_percent]));
  const html = `<div class="line-row">
    <input name="lines[${lineIdx}][description]" required>
    <input name="lines[${lineIdx}][quantity]" value="1" required style="text-align:right" oninput="recomputeAll()">
    <input name="lines[${lineIdx}][unit]">
    <input name="lines[${lineIdx}][unit_rate]" value="0" required style="text-align:right" oninput="recomputeAll()">
    <select name="lines[${lineIdx}][tax_rate_id]" onchange="recomputeAll()">
      <option value="">— None —</option>
      ${taxOpts.map(t => `<option value="${t.id}" data-pct="${t.pct}">${t.name} (${t.pct}%)</option>`).join('')}
    </select>
    <div class="line-total" data-total>0.000</div>
    <button type="button" class="del" onclick="this.closest('.line-row').remove();recomputeAll()">×</button>
  </div>`;
  document.getElementById('lines').insertAdjacentHTML('beforeend', html);
  lineIdx++;
}
function recomputeAll() {
  let sub = 0, tax = 0;
  document.querySelectorAll('.line-row').forEach(row => {
    const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
    const rate = parseFloat(row.querySelector('[name$="[unit_rate]"]').value) || 0;
    const sel = row.querySelector('select');
    const pct = parseFloat(sel.selectedOptions[0]?.dataset.pct) || 0;
    const line = qty * rate;
    const t = line * pct / 100;
    sub += line;
    tax += t;
    row.querySelector('[data-total]').textContent = line.toFixed(3);
  });
  const dt = document.querySelector('[name="discount_type"]').value;
  const dv = parseFloat(document.querySelector('[name="discount_value"]').value) || 0;
  let disc = 0;
  if (dt === 'percent') disc = sub * dv / 100;
  if (dt === 'amount')  disc = dv;
  document.getElementById('tot-sub').textContent = sub.toFixed(3);
  document.getElementById('tot-disc').textContent = disc.toFixed(3);
  document.getElementById('tot-tax').textContent = tax.toFixed(3);
  document.getElementById('tot-tot').textContent = Math.max(0, sub + tax - disc).toFixed(3);
}
recomputeAll();
</script>
@endsection