@php
  $dspIn = 'width:100%;font-size:13px;padding:11px 14px;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink)';
@endphp
<div id="dispatch-fields" style="margin-top:18px">
  <div style="font-size:12px;font-weight:600;margin-bottom:4px">Dispatch (technicians and team leads)</div>
  <div style="font-size:12px;color:var(--muted);margin-bottom:10px">Used to suggest the right technician for a request. Leave the home base empty if you do not want distance counted from home.</div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
    <div>
      <label for="home_base_lat" style="font-size:12px;display:block;margin-bottom:4px">Home base latitude</label>
      <input type="text" inputmode="decimal" id="home_base_lat" name="home_base_lat" style="{{ $dspIn }}"
             value="{{ old('home_base_lat', $user->home_base_lat ?? '') }}" placeholder="e.g. 25.2048">
      @error('home_base_lat')<div style="color:var(--red);font-size:11px;font-weight:600;margin-top:4px">{{ $message }}</div>@enderror
    </div>
    <div>
      <label for="home_base_lng" style="font-size:12px;display:block;margin-bottom:4px">Home base longitude</label>
      <input type="text" inputmode="decimal" id="home_base_lng" name="home_base_lng" style="{{ $dspIn }}"
             value="{{ old('home_base_lng', $user->home_base_lng ?? '') }}" placeholder="e.g. 55.2708">
      @error('home_base_lng')<div style="color:var(--red);font-size:11px;font-weight:600;margin-top:4px">{{ $message }}</div>@enderror
    </div>
    <div>
      <label for="daily_capacity_minutes" style="font-size:12px;display:block;margin-bottom:4px">Daily capacity (minutes)</label>
      <input type="number" min="60" max="1440" id="daily_capacity_minutes" name="daily_capacity_minutes" style="{{ $dspIn }}"
             value="{{ old('daily_capacity_minutes', $user->daily_capacity_minutes ?? 480) }}">
      @error('daily_capacity_minutes')<div style="color:var(--red);font-size:11px;font-weight:600;margin-top:4px">{{ $message }}</div>@enderror
    </div>
  </div>
</div>