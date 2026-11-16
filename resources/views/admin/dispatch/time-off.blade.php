@extends('layouts.admin')
@section('title', 'Time off')

@section('content')
<style>
  .to-wrap{max-width:900px}
  .to-wrap h1{margin:0 0 4px;font-size:22px}
  .to-wrap > p{margin:0 0 16px;color:var(--muted);font-size:13px}
  .to-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:18px}
  .to-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
  .to-grid .full{grid-column:1 / -1}
  .to-lbl{font-size:12px;font-weight:600;display:block;margin-bottom:4px}
  .to-in{width:100%;font-size:13px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink)}
  .to-err{color:var(--red);font-size:11px;font-weight:600;margin-top:4px}
  .to-btn{background:var(--amber);color:#fff;border:0;border-radius:10px;padding:10px 20px;font-weight:600;font-size:13px;cursor:pointer}
  .to-table{width:100%;border-collapse:collapse;font-size:13px}
  .to-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:8px 10px;border-bottom:1px solid var(--line)}
  .to-table td{padding:10px;border-bottom:1px solid var(--line)}
  .to-table tr:last-child td{border-bottom:0}
  .to-del{background:none;border:0;color:var(--red);font-weight:600;font-size:12px;cursor:pointer}
  .to-empty{color:var(--muted);font-size:13px;padding:6px 0}
  .to-type{font-size:11px;font-weight:600;padding:2px 10px;border-radius:999px;background:var(--soft);color:var(--muted)}
</style>

<div class="to-wrap">
  <h1>Time off</h1>
  <p>Leave, training and other time a technician cannot be booked. It lowers their availability score and blocks drag-and-drop moves onto those times.</p>

  <form method="POST" action="{{ route('dispatch.time-off.store') }}" class="to-card">
    @csrf
    <div class="to-grid">
      <div>
        <label class="to-lbl" for="user_id">Technician</label>
        <select class="to-in" id="user_id" name="user_id" required>
          <option value="">Choose</option>
          @foreach ($techs as $t)
            <option value="{{ $t->id }}" @selected((string) old('user_id') === (string) $t->id)>{{ $t->name }}</option>
          @endforeach
        </select>
        @error('user_id')<div class="to-err">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="to-lbl" for="type">Type</label>
        <select class="to-in" id="type" name="type">
          @foreach ($types as $k => $label)
            <option value="{{ $k }}" @selected(old('type', 'leave') === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="to-lbl" for="starts_at">From</label>
        <input class="to-in" type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at') }}" required>
        @error('starts_at')<div class="to-err">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="to-lbl" for="ends_at">Until</label>
        <input class="to-in" type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at') }}" required>
        @error('ends_at')<div class="to-err">{{ $message }}</div>@enderror
      </div>
      <div class="full">
        <label class="to-lbl" for="note">Note <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
        <input class="to-in" type="text" id="note" name="note" maxlength="255" value="{{ old('note') }}">
      </div>
    </div>
    <button class="to-btn" type="submit" style="margin-top:14px">Save time off</button>
  </form>

  <div class="to-card">
    <div style="font-size:13px;font-weight:700;margin-bottom:8px">Current and upcoming</div>
    @if ($entries->isEmpty())
      <div class="to-empty">No time off booked.</div>
    @else
      <table class="to-table">
        <thead><tr><th>Technician</th><th>Type</th><th>From</th><th>Until</th><th>Note</th><th></th></tr></thead>
        <tbody>
        @foreach ($entries as $e)
          <tr>
            <td><strong>{{ $e->user->name ?? '—' }}</strong></td>
            <td><span class="to-type">{{ $types[$e->type] ?? $e->type }}</span></td>
            <td>{{ $e->starts_at->format('D d M, H:i') }}</td>
            <td>{{ $e->ends_at->format('D d M, H:i') }}</td>
            <td>{{ $e->note }}</td>
            <td style="text-align:right">
              <form method="POST" action="{{ route('dispatch.time-off.destroy', $e->id) }}" class="needs-confirm"
                    data-confirm-title="Remove this time off?" data-confirm-text="The technician becomes bookable again for these times."
                    data-confirm-ok="Yes, remove" data-confirm-ok-color="red">
                @csrf @method('DELETE')
                <button class="to-del" type="submit">Remove</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
@endsection
