@extends('layouts.technician')
@section('title', 'Log expense')
@section('back', route('technician.jobs.expenses', $serviceRequest))
@section('content')
<form method="POST" action="{{ route('technician.jobs.expenses.store', $serviceRequest) }}" enctype="multipart/form-data" class="card">
    @csrf
    <label class="field">Category
        <select name="expense_category_id" required id="cat-select">
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" data-rate="{{ $c->default_unit_rate }}" data-receipt="{{ $c->requires_receipt ? 1 : 0 }}">{{ $c->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="field">Description<input type="text" name="description" required maxlength="255"></label>
    <label class="field">Quantity<input type="number" name="quantity" step="0.01" min="0.01" value="1" required></label>
    <label class="field">Unit rate<input type="number" name="unit_rate" step="0.01" min="0" id="rate-input"></label>
    <label class="field" id="receipt-field">Receipt<input type="file" name="receipt" accept="image/*" capture="environment"></label>
    <button type="submit" class="btn block">Save expense</button>
</form>
<script>
const sel = document.getElementById('cat-select');
const rate = document.getElementById('rate-input');
const receiptField = document.getElementById('receipt-field');
function sync() {
    const opt = sel.options[sel.selectedIndex];
    if (opt.dataset.rate) rate.value = opt.dataset.rate;
    receiptField.querySelector('input').required = opt.dataset.receipt === '1';
}
sel.addEventListener('change', sync);
sync();
</script>
@endsection