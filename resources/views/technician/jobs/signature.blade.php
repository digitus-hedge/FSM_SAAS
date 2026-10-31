@extends('layouts.technician')
@section('title', 'Signature')
@section('back', route('technician.jobs.show', $serviceRequest))
@section('content')
<div class="card">
    <label class="field">Customer name<input type="text" id="signer_name" required></label>
    <label class="field">Customer phone (optional)<input type="text" id="signer_phone"></label>
    <div style="font-weight:700;font-size:13px;margin-bottom:6px">Sign below</div>
    <canvas id="pad" width="400" height="180" style="width:100%;height:180px;border:1px solid var(--line);border-radius:10px;background:#fff;touch-action:none"></canvas>
    <div style="display:flex;gap:10px;margin-top:10px">
        <button type="button" id="clear-btn" class="btn secondary" style="flex:1">Clear</button>
        <button type="button" id="save-btn" class="btn" style="flex:1">Save signature</button>
    </div>
</div>
<form method="POST" action="{{ route('technician.jobs.signature.store', $serviceRequest) }}" id="sig-form">
    @csrf
    <input type="hidden" name="signature_png" id="signature_png">
    <input type="hidden" name="signer_name" id="signer_name_hidden">
    <input type="hidden" name="signer_phone" id="signer_phone_hidden">
    <input type="hidden" name="gps_lat" id="gps_lat">
    <input type="hidden" name="gps_lng" id="gps_lng">
</form>
<script>
const canvas = document.getElementById('pad');
const ctx = canvas.getContext('2d');
ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#1B1A24';
let drawing = false, lastX = 0, lastY = 0;
function pos(e) {
    const r = canvas.getBoundingClientRect();
    const sx = canvas.width / r.width, sy = canvas.height / r.height;
    const p = e.touches ? e.touches[0] : e;
    return [(p.clientX - r.left) * sx, (p.clientY - r.top) * sy];
}
function start(e) { drawing = true; [lastX, lastY] = pos(e); }
function move(e) {
    if (!drawing) return; e.preventDefault();
    const [x, y] = pos(e);
    ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(x, y); ctx.stroke();
    [lastX, lastY] = [x, y];
}
function end() { drawing = false; }
canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); canvas.addEventListener('mouseup', end);
canvas.addEventListener('touchstart', start); canvas.addEventListener('touchmove', move); canvas.addEventListener('touchend', end);
document.getElementById('clear-btn').addEventListener('click', () => ctx.clearRect(0, 0, canvas.width, canvas.height));
document.getElementById('save-btn').addEventListener('click', () => {
    if (! document.getElementById('signer_name').value.trim()) { alert('Enter the customer name first.'); return; }
    document.getElementById('signature_png').value = canvas.toDataURL('image/png');
    document.getElementById('signer_name_hidden').value = document.getElementById('signer_name').value;
    document.getElementById('signer_phone_hidden').value = document.getElementById('signer_phone').value;
    const form = document.getElementById('sig-form');
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (p) => { document.getElementById('gps_lat').value = p.coords.latitude; document.getElementById('gps_lng').value = p.coords.longitude; form.submit(); },
            () => form.submit(), { timeout: 5000 }
        );
    } else { form.submit(); }
});
</script>
@endsection