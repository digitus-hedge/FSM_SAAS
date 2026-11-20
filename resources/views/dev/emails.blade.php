<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email preview (local only)</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 24px 16px; max-width: 960px; margin-inline: auto; color: #222; background: #fff; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    h2 { font-size: 15px; margin: 24px 0 6px; }
    p.hint { color: #666; margin: 0 0 16px; font-size: 13px; }
    ul { padding-left: 18px; margin: 0; }
    li { margin: 4px 0; }
    a { color: #0b57d0; }
    iframe { width: 100%; height: 640px; border: 1px solid #ddd; border-radius: 8px; }
    pre { background: #f6f6f6; padding: 12px; border-radius: 8px; white-space: pre-wrap; font-size: 13px; }
  </style>
</head>
<body>
  <h1>Email preview</h1>
  <p class="hint">Local only. Sample data, nothing is sent or saved. The company is the first one in your database, so its name, logo and contact details show.</p>

  @if ($key === null)
    @foreach ($groups as $group => $items)
      <h2>{{ $group }}</h2>
      <ul>
        @foreach ($items as $k => $label)
          <li><a href="{{ route('dev.emails.show', $k) }}">{{ $label }}</a></li>
        @endforeach
      </ul>
    @endforeach
  @else
    <p><a href="{{ route('dev.emails.index') }}">All emails</a></p>
    <h2>{{ $label }}</h2>
    <p><strong>Subject:</strong> {{ $frame['heading'] }}</p>
    <iframe src="{{ route('dev.emails.html', $key) }}" title="{{ $label }}"></iframe>
    <h2>Plain-text version</h2>
    <pre>{{ $text }}</pre>
  @endif
</body>
</html>
