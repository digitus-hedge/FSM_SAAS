<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;color:#1B1A24;max-width:560px;margin:0 auto;padding:24px">
    <p>Hello {{ $name }},</p>
    <p>The visit for {{ $number }} ({{ $title }}){{ $site ? ' at ' . $site : '' }} has been moved.</p>
    @if ($beforeText)
        <p style="margin:0">Before: <s>{{ $beforeText }}</s></p>
    @endif
    <p style="margin:0 0 16px"><strong>Now: {{ $afterText }}</strong></p>
    @if ($reason)
        <p>Reason: {{ $reason }}</p>
    @endif
    <p style="color:#5F5D6E;font-size:13px">Sorry for any inconvenience. If this time does not suit you, please contact {{ $company }}.</p>
</body>
</html>
