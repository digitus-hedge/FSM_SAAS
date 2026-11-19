{{-- The fields of one checklist version. Expects: $version, $answers (saved), $editable, $draftKey --}}
@php($schema = $version->schema_json ?? ['sections' => []])
<input type="hidden" name="client_event_uuid" class="uuid-field" value="{{ $eventUuid ?? '' }}">
<input type="hidden" name="recorded_at" value="{{ now()->toIso8601String() }}">
<input type="hidden" name="version_id" value="{{ $version->id }}">

@foreach (($schema['sections'] ?? []) as $section)
    <div class="sec">{{ $section['title'] ?: 'Checks' }}</div>
    @foreach (($section['fields'] ?? []) as $f)
        @php($key = $f['key'])
        @php($val = old('answers.' . $key, $answers[$key] ?? null))
        <div class="card chk-field" style="padding:14px;margin-bottom:10px"
             data-key="{{ $key }}"
             @if (!empty($f['show_if'])) data-showif='@json($f['show_if'])' @endif>
            <label style="display:block;font-weight:700;margin-bottom:6px">
                {{ $f['label'] }} @if (!empty($f['required']))<span style="color:var(--red)">*</span>@endif
                @if (!empty($f['unit']))<span class="muted" style="font-weight:400">({{ $f['unit'] }})</span>@endif
            </label>
            @if (!empty($f['help']))<div class="sub" style="margin-bottom:8px">{{ $f['help'] }}</div>@endif

            @switch($f['type'])
                @case('text')
                    <textarea name="answers[{{ $key }}]" class="txt" rows="2" maxlength="2000" @disabled(!$editable)>{{ $val }}</textarea>
                    @break
                @case('number')
                    <input type="number" step="any" name="answers[{{ $key }}]" class="inp" value="{{ $val }}" @disabled(!$editable)
                           @if (isset($f['min'])) min="{{ $f['min'] }}" @endif @if (isset($f['max'])) max="{{ $f['max'] }}" @endif>
                    @if (isset($f['min']) || isset($f['max']))
                        <div class="sub">Expected {{ $f['min'] ?? '' }}–{{ $f['max'] ?? '' }}</div>
                    @endif
                    @break
                @case('dropdown')
                    <select name="answers[{{ $key }}]" class="sel" @disabled(!$editable)>
                        <option value="">Choose…</option>
                        @foreach (($f['options'] ?? []) as $o)<option value="{{ $o }}" @selected((string) $val === (string) $o)>{{ $o }}</option>@endforeach
                    </select>
                    @break
                @case('checkbox')
                    <input type="hidden" name="answers[{{ $key }}]" value="0">
                    <label><input type="checkbox" name="answers[{{ $key }}]" value="1" @checked($val) @disabled(!$editable)> Yes</label>
                    @break
                @case('yes_no')
                    <label style="margin-right:16px"><input type="radio" name="answers[{{ $key }}]" value="yes" @checked($val === 'yes') @disabled(!$editable)> Yes</label>
                    <label><input type="radio" name="answers[{{ $key }}]" value="no" @checked($val === 'no') @disabled(!$editable)> No</label>
                    @break
                @case('date')
                    <input type="date" name="answers[{{ $key }}]" class="inp" value="{{ $val }}" @disabled(!$editable)>
                    @break
                @case('photo')
                    @if (!empty($val))<div class="sub" style="margin-bottom:6px">{{ count((array) $val) }} photo(s) saved</div>@endif
                    @if ($editable)<input type="file" name="files[{{ $key }}][]" accept="image/*" capture="environment" multiple>@endif
                    @break
                @case('signature')
                    @if (!empty($val))<div class="sub" style="margin-bottom:6px">Signature saved</div>@endif
                    @if ($editable)
                        <canvas class="sig-pad" width="320" height="120" style="border:1px solid var(--line,#ddd);border-radius:8px;touch-action:none;background:#fff"></canvas>
                        <input type="hidden" name="answers[{{ $key }}]" class="sig-data">
                        <button type="button" class="btn sig-clear" style="margin-top:6px">Clear</button>
                    @endif
                    @break
            @endswitch
        </div>
    @endforeach
@endforeach

@if ($editable)
<script>
(function () {
    var form = document.currentScript.closest('form');
    if (!form) return;
    var draftKey = @json($draftKey);

    // Show-if rules: hide a field whose condition is not met (the server drops its answer too).
    function value(key) {
        var els = form.querySelectorAll('[name="answers[' + key + ']"]');
        var v = null;
        els.forEach(function (e) { if (e.type === 'radio' || e.type === 'checkbox') { if (e.checked) v = e.value; } else if (e.type !== 'hidden' || !v) { v = e.value; } });
        return v;
    }
    function refresh() {
        form.querySelectorAll('.chk-field[data-showif]').forEach(function (box) {
            var c = JSON.parse(box.getAttribute('data-showif')), v = value(c.field), ok = true;
            if (c.op === 'equals') ok = String(v) === String(c.value);
            else if (c.op === 'not_equals') ok = String(v) !== String(c.value);
            else if (c.op === 'is_yes') ok = v === 'yes';
            else if (c.op === 'is_no') ok = v === 'no';
            else if (c.op === 'gt') ok = parseFloat(v) > parseFloat(c.value);
            else if (c.op === 'lt') ok = parseFloat(v) < parseFloat(c.value);
            box.style.display = ok ? '' : 'none';
        });
    }
    form.addEventListener('input', refresh); form.addEventListener('change', refresh); refresh();

    // Offline draft: answers stay on the device until the save goes through.
    try {
        var draft = JSON.parse(localStorage.getItem(draftKey) || 'null');
        if (draft && draft.v === {{ $version->id }}) {
            Object.keys(draft.a).forEach(function (n) {
                var el = form.querySelector('[name="' + n + '"]:not([type=hidden]):not([type=radio]):not([type=checkbox]):not([type=file])');
                if (el && !el.value) el.value = draft.a[n];
            });
            refresh();
        }
        form.addEventListener('input', function () {
            var a = {};
            form.querySelectorAll('[name^="answers["]').forEach(function (e) {
                if (e.type === 'radio' || e.type === 'checkbox' || e.type === 'hidden') return;
                if (e.value) a[e.name] = e.value;
            });
            localStorage.setItem(draftKey, JSON.stringify({ v: {{ $version->id }}, a: a }));
        });
        form.addEventListener('submit', function () { localStorage.removeItem(draftKey); });
    } catch (e) {}

    // Signature pads
    form.querySelectorAll('.sig-pad').forEach(function (cv) {
        var ctx = cv.getContext('2d'), down = false, data = cv.parentNode.querySelector('.sig-data');
        ctx.lineWidth = 2; ctx.lineCap = 'round';
        function pos(e) { var r = cv.getBoundingClientRect(); var p = e.touches ? e.touches[0] : e; return [(p.clientX - r.left) * cv.width / r.width, (p.clientY - r.top) * cv.height / r.height]; }
        function start(e) { down = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); e.preventDefault(); }
        function move(e) { if (!down) return; var p = pos(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); data.value = cv.toDataURL('image/png'); e.preventDefault(); }
        function end() { down = false; }
        cv.addEventListener('mousedown', start); cv.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
        cv.addEventListener('touchstart', start); cv.addEventListener('touchmove', move); cv.addEventListener('touchend', end);
        cv.parentNode.querySelector('.sig-clear').addEventListener('click', function () { ctx.clearRect(0, 0, cv.width, cv.height); data.value = ''; });
    });
})();
</script>
@endif
