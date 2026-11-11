@extends('layouts.technician')
@section('title', 'Scan asset')
@section('content')

<a href="{{ route('technician.jobs.index') }}" class="sub" style="display:inline-block;margin-bottom:12px;text-decoration:none">&larr; Back</a>

@if (! $job)
<div class="card center" style="padding:32px 20px">
    <h3 style="margin-bottom:6px">Punch in first</h3>
    <p class="sub" style="margin:0 0 16px">You can raise a request from a scan only while you are punched in on a job.</p>
    <a href="{{ route('technician.jobs.index') }}" class="btn dark">Go to my jobs</a>
</div>
@else
<div class="card" style="padding:14px;margin-bottom:12px">
    <div class="sub">Punched in on</div>
    <div style="font-weight:700">{{ $job->sr_number }} &middot; {{ $job->site?->name }}</div>
</div>

<div class="card" style="padding:10px;margin-bottom:12px">
    <div style="position:relative;border-radius:12px;overflow:hidden;background:#000;aspect-ratio:1/1">
        <video id="scan-video" playsinline muted style="width:100%;height:100%;object-fit:cover"></video>
    </div>
    <canvas id="scan-canvas" style="display:none"></canvas>
    <div id="scan-msg" class="sub" style="text-align:center;margin-top:10px">Starting camera…</div>
</div>

<form id="scan-form" method="GET" action="{{ route('technician.scan.resolve') }}" style="display:none">
    <input type="hidden" name="hash" id="scan-hash">
</form>

<div class="card" style="padding:14px">
    <div class="sub" style="margin-bottom:8px">Camera not working? Type the code printed under the QR.</div>
    <form method="GET" action="{{ route('technician.scan.resolve') }}" style="display:flex;gap:8px">
        <input type="text" name="hash" class="inp" placeholder="QR code" required style="flex:1">
        <button type="submit" class="btn dark">Go</button>
    </form>
</div>

<script src="{{ asset('js/jsQR.js') }}"></script>
<script>
(function () {
    const video = document.getElementById('scan-video');
    const canvas = document.getElementById('scan-canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const msg = document.getElementById('scan-msg');
    let done = false, stream = null;

    // QR holds https://{slug}.aiywah.com/q/{hash} — take what follows /q/.
    function hashFrom(text) {
        const m = String(text).match(/\/q\/([^\/?#\s]+)/);
        return m ? m[1] : null;
    }

    function tick() {
        if (done) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
            if (code && code.data) {
                const h = hashFrom(code.data);
                if (h) {
                    done = true;
                    if (stream) stream.getTracks().forEach(t => t.stop());
                    msg.textContent = 'Found it…';
                    document.getElementById('scan-hash').value = h;
                    document.getElementById('scan-form').submit();
                    return;
                }
                msg.textContent = 'That QR code is not an asset label. Keep scanning.';
            }
        }
        requestAnimationFrame(tick);
    }

    if (! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
        msg.textContent = 'This browser cannot open the camera. Type the code below.';
        return;
    }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
        .then(function (s) {
            stream = s; video.srcObject = s; video.play();
            msg.textContent = 'Point the camera at the asset QR label.';
            requestAnimationFrame(tick);
        })
        .catch(function () { msg.textContent = 'Camera permission was refused. Allow it, or type the code below.'; });
})();
</script>
@endif
@endsection