{{-- Read-only answers of a checklist. Expects $version, $answers, $clientOnly (only the fields marked visible to the client) --}}
<table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:13px">
  @foreach (($version->schema_json['sections'] ?? []) as $section)
    @foreach (($section['fields'] ?? []) as $f)
      @continue($clientOnly && empty($f['visible_to_client']))
      @continue(! array_key_exists($f['key'], $answers))
      @php($v = $answers[$f['key']])
      <tr style="border-top:1px solid var(--line)">
        <td style="padding:6px 8px 6px 0;width:45%;color:var(--muted)">{{ $f['label'] }}</td>
        <td style="padding:6px 0">
          @if (in_array($f['type'], ['photo', 'signature'], true))
            {{ $f['type'] === 'photo' ? count((array) $v) . ' photo(s)' : 'Signed' }}
          @elseif ($f['type'] === 'checkbox')
            {{ $v ? 'Yes' : 'No' }}
          @elseif ($f['type'] === 'yes_no')
            {{ $v === 'yes' ? 'Yes' : 'No' }}
          @else
            {{ is_array($v) ? implode(', ', $v) : $v }}{{ ! empty($f['unit']) ? ' ' . $f['unit'] : '' }}
          @endif
        </td>
      </tr>
    @endforeach
  @endforeach
</table>
