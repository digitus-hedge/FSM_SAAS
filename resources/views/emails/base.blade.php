{{-- The one shared email layout. Input: $m, built by App\Services\Notifications\EmailContent (company, heading, intro, rows, code, button, note). --}}
@php
    $c = $m['company'];
    // the company's own Branding colour and font (EmailContent::brand); never a fixed yellow
    $accent  = $c['accent'] ?? '#5E8A3C';
    $ink     = $c['ink'] ?? '#FFFFFF';
    $fBody   = $c['font_body'] ?? "'DM Sans'";
    $fHead   = $c['font_display'] ?? "'Figtree'";
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $m['heading'] }}</title>
  @if (! empty($c['font_param']))<link href="https://fonts.googleapis.com/css2?family={{ $c['font_param'] }}&display=swap" rel="stylesheet">@endif
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:{!! $fBody !!},Arial,Helvetica,sans-serif;color:#1B1A24;font-size:14px;line-height:1.5">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff">
    <tr><td align="center" style="padding:24px 12px">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;border:1px solid #E5E3EE;border-radius:12px">
        <tr><td style="padding:20px 24px;border-bottom:3px solid {{ $accent }}">
          @if (! empty($c['logo']))
            <img src="{{ $c['logo'] }}" alt="{{ $c['name'] }}" style="max-height:40px;max-width:180px;vertical-align:middle;margin-right:10px">
          @endif
          <span style="font-size:16px;font-weight:700;vertical-align:middle">{{ $c['name'] }}</span>
        </td></tr>
        <tr><td style="padding:24px">
          <h1 style="font-family:{!! $fHead !!},{!! $fBody !!},Arial,sans-serif;font-size:20px;line-height:1.3;margin:0 0 10px">{{ $m['heading'] }}</h1>
          @if (! empty($m['intro']))
            <p style="margin:0 0 18px">{{ $m['intro'] }}</p>
          @endif

          @if (! empty($m['code']))
            <p style="margin:0 0 18px;text-align:center">
              <span style="display:inline-block;font-size:30px;letter-spacing:8px;font-weight:700;padding:12px 20px;border:1px dashed #8B89A0;border-radius:8px">{{ $m['code'] }}</span>
            </p>
          @endif

          @if (! empty($m['rows']))
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E5E3EE;border-radius:8px;margin:0 0 20px">
              @foreach ($m['rows'] as $label => $value)
                <tr>
                  <td style="padding:8px 12px;width:38%;color:#5F5D6E;border-top:{{ $loop->first ? '0' : '1px solid #E5E3EE' }};vertical-align:top">{{ $label }}</td>
                  <td style="padding:8px 12px;font-weight:600;border-top:{{ $loop->first ? '0' : '1px solid #E5E3EE' }};vertical-align:top">{!! nl2br(e($value)) !!}</td>
                </tr>
              @endforeach
            </table>
          @endif

          @if (! empty($m['button']))
            <p style="margin:0 0 6px">
              <a href="{{ $m['button']['url'] }}" style="background:{{ $accent }};color:{{ $ink }};text-decoration:none;font-weight:700;padding:12px 24px;border-radius:999px;display:inline-block">{{ $m['button']['label'] }}</a>
            </p>
            <p style="margin:0 0 4px;color:#8B89A0;font-size:12px;word-break:break-all">If the button does not work, copy this link into your browser:<br>{{ $m['button']['url'] }}</p>
          @endif

          @if (! empty($m['note']))
            <p style="margin:16px 0 0;color:#5F5D6E;font-size:13px">{{ $m['note'] }}</p>
          @endif
        </td></tr>
        <tr><td style="padding:16px 24px;border-top:1px solid #E5E3EE;color:#8B89A0;font-size:12px">
          <strong style="color:#5F5D6E">{{ $c['name'] }}</strong>
          @if (! empty($c['email']) || ! empty($c['phone']))
            <br>{{ collect([$c['email'] ?? null, $c['phone'] ?? null])->filter()->implode(' · ') }}
          @endif
          <br>This is an automated message.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
