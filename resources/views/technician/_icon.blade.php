@php
$paths = [
 'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
 'home'    => '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
 'list'    => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
 'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M22 20a7 7 0 0 0-4-6.3"/>',
 'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
 'refresh' => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
 'bell'    => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
 'right'   => '<path d="M9 6l6 6-6 6"/>',
 'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 'send'    => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
 'out'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
 'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
 'wifioff' => '<path d="M2 2l20 20M8.5 16.4a5 5 0 0 1 7 0M5 12.9a10 10 0 0 1 5.2-2.7M2 8.8a15 15 0 0 1 4.2-2.5M19 12.9A10 10 0 0 0 16.5 11M12 20h.01"/>',
 'plus'    => '<path d="M12 5v14M5 12h14"/>',
 'check'   => '<path d="M20 6L9 17l-5-5"/>',
 'x'       => '<path d="M18 6L6 18M6 6l12 12"/>',
];
$size = $size ?? 20;
$w = $w ?? 1.9;
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $w }}" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px" aria-hidden="true">{!! $paths[$name] ?? '' !!}</svg>