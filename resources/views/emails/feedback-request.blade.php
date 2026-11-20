<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;color:#1B1A24;max-width:560px;margin:0 auto;padding:24px">
    <p>Hello {{ $name }},</p>
    <p>{{ $number }} ({{ $title }}) has been completed. We would like to know how it went. It takes a minute: a rating, a score and, if you like, a comment.</p>
    <p style="margin:24px 0">
        <a href="{{ $link }}" style="background:#8A5A00;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold">Give your feedback</a>
    </p>
    <p style="color:#5F5D6E;font-size:13px">This link works once and is valid until {{ $until }}. {{ $company }}</p>
    <p style="color:#5F5D6E;font-size:13px">{{ $link }}</p>
</body>
</html>
