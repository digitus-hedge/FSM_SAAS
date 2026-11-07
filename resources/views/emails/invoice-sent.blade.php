<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;color:#1B1A24;max-width:560px;margin:0 auto;padding:24px">
    <h2 style="margin:0 0 12px">Invoice {{ $invoice->invoice_number }}</h2>
    <p>Hello,</p>
    <p>An invoice for your service request <strong>{{ $invoice->serviceRequest?->sr_number }}</strong> is ready.</p>
    <p>
        <strong>Total:</strong> {{ $invoice->currency_code }} {{ number_format($invoice->total, 2) }}<br>
        <strong>Due date:</strong> {{ $invoice->due_date?->format('d M Y') }}
    </p>
    <p style="margin:24px 0">
        <a href="{{ $link }}" style="background:#8A5A00;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold">View invoice</a>
    </p>
    <p style="color:#5F5D6E;font-size:13px">If the button doesn't work, copy this link into your browser:<br>{{ $link }}</p>
</body>
</html>