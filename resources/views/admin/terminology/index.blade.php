@extends('layouts.admin')
@section('title', 'Terminology')

@section('content')
<style>
  .page-head{margin-bottom:22px}
  .page-head h1{font-family:var(--font-display);font-size:34px;font-weight:800;line-height:1.08;letter-spacing:-.02em;margin:0;color:var(--ink)}
  .page-head p{color:var(--muted);font-size:14px;margin-top:6px}

  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:var(--font-body);font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:all .12s;white-space:nowrap}
  .btn-primary{background:var(--yellow);color:var(--ink)}
  .btn-primary:hover{background:color-mix(in srgb, var(--yellow) 85%, black 15%)}
  .btn-ghost{background:#fff;color:var(--ink);border-color:var(--line)}
  .btn-ghost:hover{border-color:var(--ink)}
  .btn-mini{padding:8px 16px;font-size:12.5px;font-weight:700;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink);cursor:pointer;font-family:var(--font-body)}
  .btn-mini:hover{border-color:var(--ink)}

  .card{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden}
  table{width:100%;border-collapse:collapse;font-size:14px;min-width:860px}
  thead th{text-align:left;padding:14px 20px;font-weight:600;font-size:13px;color:var(--muted);background:var(--soft);border-bottom:1px solid var(--line);white-space:nowrap}
  tbody td{padding:14px 20px;border-bottom:1px solid var(--line);vertical-align:middle;color:var(--ink)}
  tbody tr:last-child td{border-bottom:0}
  .default-cell{font-weight:600;color:var(--ink)}
  .inp{width:100%;font-family:var(--font-body);font-size:14px;padding:11px 14px;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink);outline:none;transition:border .15s, box-shadow .15s;box-sizing:border-box}
  .inp:focus{border-color:var(--amber);box-shadow:0 0 0 3px color-mix(in srgb, var(--amber) 15%, transparent)}

  .tblwrap{overflow-x:auto}
  .sample{margin:20px 0;padding:14px 20px;background:var(--soft);border:1px solid var(--line);border-radius:12px;font-size:14px;color:var(--ink)}
  .sample strong{color:var(--amber);font-weight:700}
  .proto{color:var(--muted);font-size:12.5px;margin-top:16px}
</style>

<div class="page-head">
  <h1>Terminology</h1>
  <p>Rename the words your company uses. Roles, statuses and audit names are not renamed.</p>
</div>

<form method="POST" action="{{ route('admin.terminology.update') }}" id="term-form">
  @csrf @method('PUT')

  <div class="card">
    <div class="tblwrap">
      <table>
        <thead>
          <tr>
            <th style="width:200px">Default word</th>
            <th>Your singular word</th>
            <th>Your plural word</th>
            <th style="width:100px;text-align:right"></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($rows as $r)
            <tr data-key="{{ $r['key'] }}"
                data-default-singular="{{ $r['default_singular'] }}"
                data-default-plural="{{ $r['default_plural'] }}">
              <td class="default-cell">{{ $r['default_singular'] }}</td>
              <td>
                <input type="text" name="terms[{{ $r['key'] }}][singular]"
                       value="{{ old('terms.'.$r['key'].'.singular', $r['singular']) }}"
                       class="inp inp-sing" maxlength="80" required>
              </td>
              <td>
                <input type="text" name="terms[{{ $r['key'] }}][plural]"
                       value="{{ old('terms.'.$r['key'].'.plural', $r['plural']) }}"
                       class="inp inp-plur" maxlength="80" required>
              </td>
              <td style="text-align:right">
                <button type="button" class="btn-mini" onclick="resetRow(this)">Reset</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Live preview sample --}}
  <div class="sample">
    Sample: Create a <strong id="sample-sing">service request</strong>.
    Showing 12 <strong id="sample-plur">service requests</strong>.
  </div>

  <div style="display:flex;gap:10px;margin-top:8px">
    <button type="submit" class="btn btn-primary">Save</button>
  </div>

  <div class="proto">Design prototype. Names and numbers are examples.</div>
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
<script>
function resetRow(btn) {
  const tr = btn.closest('tr');
  tr.querySelector('.inp-sing').value = tr.dataset.defaultSingular;
  tr.querySelector('.inp-plur').value = tr.dataset.defaultPlural;
  updateSample();
}

function updateSample() {
  // Keep the sample sentence in sync with the "service_request" row (the first row)
  const sr = document.querySelector('tr[data-key="service_request"]');
  if (!sr) return;
  document.getElementById('sample-sing').textContent =
    sr.querySelector('.inp-sing').value.toLowerCase();
  document.getElementById('sample-plur').textContent =
    sr.querySelector('.inp-plur').value.toLowerCase();
}

document.querySelectorAll('.inp').forEach(i => i.addEventListener('input', updateSample));
updateSample();

@if (session('success'))
  Swal.fire({toast:true,position:'top-end',icon:'success',title:@json(session('success')),showConfirmButton:false,timer:2500});
@endif

@if ($errors->any())
  Swal.fire({icon:'error',title:'Could not save',text:'Please check the highlighted rows.'});
@endif
</script>
@endsection