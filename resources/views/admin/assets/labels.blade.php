<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Asset labels</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  @page{size:A4;margin:10mm}
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:16px;color:#0F0F17;background:#F5F4F8}
  .bar{max-width:210mm;margin:0 auto 14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;font-size:14px}
  .bar button,.bar a{font:inherit;font-weight:700;padding:10px 18px;border-radius:10px;border:1px solid #D1CFDA;background:#fff;color:#0F0F17;cursor:pointer;text-decoration:none}
  .bar .primary{background:#0F0F17;color:#fff;border-color:#0F0F17}
  .warn{max-width:210mm;margin:0 auto 12px;background:#FEF3C7;color:#92400E;padding:10px 14px;border-radius:10px;font-size:13.5px}
  .sheet{max-width:210mm;margin:0 auto;display:flex;flex-wrap:wrap;gap:0;background:#fff;padding:0}
  .label{width:50mm;height:30mm;padding:2mm;display:flex;gap:2mm;align-items:center;border:.2mm dashed #C9C7D3;overflow:hidden;page-break-inside:avoid;break-inside:avoid}
  .qr{width:24mm;height:24mm;flex:none}
  .qr svg{width:100%;height:100%;display:block}
  .txt{min-width:0;flex:1;line-height:1.15}
  .code{font-weight:800;font-size:10pt;letter-spacing:.02em}
  .name{font-size:7.5pt;margin-top:1mm;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;word-break:break-word}
  .site{font-size:6.5pt;color:#444;margin-top:1mm;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .co{font-size:6pt;color:#666;margin-top:1mm}
  @media print{
    body{background:#fff;padding:0}
    .bar,.warn{display:none}
    .sheet{max-width:none}
    .label{border-color:#E5E4EC}
  }
</style>
</head>
<body>
  <div class="bar">
    <button class="primary" onclick="window.print()">Print {{ $labels->count() }} {{ $labels->count() === 1 ? 'label' : 'labels' }}</button>
    <a href="javascript:history.back()">Back</a>
    <span>Labels are 50 × 30 mm. In the print dialog set scale to 100%.</span>
  </div>

  @if ($overLimit)
    <div class="warn">{{ number_format($overLimit) }} assets match. Only the first {{ $max }} are shown. Narrow the filters to print the rest.</div>
  @endif

  @if ($labels->isEmpty())
    <div class="warn">No assets to print. Decommissioned assets are left out.</div>
  @endif

  <div class="sheet">
    @foreach ($labels as $l)
      <div class="label">
        <div class="qr">{!! $l['svg'] !!}</div>
        <div class="txt">
          <div class="code">{{ $l['code'] }}</div>
          <div class="name">{{ $l['name'] }}</div>
          @if ($l['site'])<div class="site">{{ $l['site'] }}</div>@endif
          @if ($company)<div class="co">{{ $company }}</div>@endif
        </div>
      </div>
    @endforeach
  </div>
</body>
</html>