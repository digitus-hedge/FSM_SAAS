<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;color:#1B1A24;max-width:560px;margin:0 auto;padding:24px">
    <p>Invoice {{ $number }} for {{ $amount }} was due on {{ $due }} and is still unpaid. Please arrange payment or contact us.</p>
    <p style="margin:24px 0">
        <a href="{{ $link }}" style="background:#8A5A00;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold">View invoice</a>
    </p>
    <p style="color:#5F5D6E;font-size:13px">View invoice: {{ $link }}</p>
</body>
</html>
