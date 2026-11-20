@if (isset($impersonation))
<div id="impersonation-banner" role="alert" style="position:sticky;top:0;z-index:9999;background:#B42318;color:#fff;padding:9px 16px;display:flex;gap:14px;align-items:center;flex-wrap:wrap;font:600 13px system-ui,sans-serif">
    <span>Support session: {{ $impersonation['by'] }} is signed in to your company. Reason: {{ $impersonation['reason'] }}</span>
    <span style="font-weight:400">Ends at {{ $impersonation['ends_at'] }} ({{ $impersonation['minutes_left'] }} min left)</span>
    <form method="POST" action="{{ route('impersonation.end') }}" style="margin-left:auto">
        @csrf
        <button type="submit" style="background:#fff;color:#B42318;border:0;border-radius:999px;padding:6px 16px;font-weight:700;cursor:pointer">End session</button>
    </form>
</div>
@endif
