@extends('layouts.technician')
@section('title', 'Log expense')
@section('back', route('technician.jobs.expenses', $serviceRequest))
@section('content')

<form method="POST" action="{{ route('technician.jobs.expenses.store', $serviceRequest) }}" enctype="multipart/form-data" class="card" style="padding:18px">
    @csrf

    <div class="field">
        <label>Category</label>
        <select name="expense_category_id" class="sel" required id="cat-select">
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" data-rate="{{ $c->default_unit_rate }}" data-receipt="{{ $c->requires_receipt ? 1 : 0 }}">{{ $c->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label>Description</label>
        <input type="text" name="description" class="inp" required maxlength="255" placeholder="e.g. Taxi to site">
    </div>

    <div class="row2">
        <div class="field">
            <label>Quantity</label>
            <input type="number" name="quantity" class="inp" step="0.01" min="0.01" value="1" required id="qty-input">
        </div>
        <div class="field">
            <label>Unit rate</label>
            <input type="number" name="unit_rate" class="inp" step="0.01" min="0" id="rate-input">
        </div>
    </div>

    <div style="background:var(--soft);border-radius:14px;display:flex;justify-content:space-between;align-items:center;padding:12px 14px;margin-bottom:16px">
        <span class="muted" style="font-size:13.5px;font-weight:700">Total</span>
        <span id="total-preview" style="font-family:var(--font-display);font-weight:700;font-size:19px">0.00</span>
    </div>

    <div class="field" id="receipt-field">
        <label>Receipt <span id="receipt-req" class="chip sm" style="--c:var(--amber);display:none;margin-left:4px">Required</span></label>
        <input type="file" name="receipt" class="inp" style="padding:10px 14px" accept="image/*" capture="environment">
    </div>

    <button type="submit" class="btn dark block">Save expense</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('cat-select');
    const rate = document.getElementById('rate-input');
    const qty = document.getElementById('qty-input');
    const receiptField = document.getElementById('receipt-field');
    const receiptReq = document.getElementById('receipt-req');
    const total = document.getElementById('total-preview');

    function syncCategory() {
        const opt = sel.options[sel.selectedIndex];
        if (opt.dataset.rate) rate.value = opt.dataset.rate;
        const required = opt.dataset.receipt === '1';
        receiptField.querySelector('input').required = required;
        receiptReq.style.display = required ? 'inline-flex' : 'none';
        syncTotal();
    }

    function syncTotal() {
        const q = parseFloat(qty.value) || 0;
        const r = parseFloat(rate.value) || 0;
        total.textContent = (q * r).toFixed(2);
    }

    sel.addEventListener('change', syncCategory);
    qty.addEventListener('input', syncTotal);
    rate.addEventListener('input', syncTotal);
    syncCategory();
});
</script>
@endsection