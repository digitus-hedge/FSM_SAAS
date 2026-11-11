@extends('layouts.admin')
@section('title', 'Import preview')

@section('content')
@php $plural = $plural ?? 'Assets'; @endphp
<style>
  .crumb{font-size:13px;color:#5F5D6E;margin-bottom:12px}
  .crumb a{color:#0F0F17;text-decoration:underline}
  .page-h1{font-family:var(--font-display);font-size:28px;font-weight:800;margin-bottom:6px}
  .page-sub{color:#5F5D6E;font-size:14px;margin-bottom:22px}
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
  .stat{background:#fff;border:1px solid #E5E4EC;border-radius:14px;padding:16px 18px}
  .stat .v{font-family:var(--font-display);font-size:26px;font-weight:800}
  .stat .l{font-size:12.5px;color:#5F5D6E;margin-top:2px}
  .card{background:#fff;border:1px solid #E5E4EC;border-radius:16px;overflow:hidden;margin-bottom:14px}
  .tblwrap{overflow-x:auto}
  table{width:100%;border-collapse:collapse;min-width:900px;font-size:13.5px}
  thead th{text-align:left;padding:12px 16px;font-weight:600;font-size:12.5px;color:#5F5D6E;background:#FAFAFC;border-bottom:1px solid #EEEDF3;white-space:nowrap}
  tbody td{padding:12px 16px;border-bottom:1px solid #F2F1F6;vertical-align:top}
  tbody tr:last-child td{border-bottom:0}
  .pill{display:inline-block;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:999px}
  .p-ok{background:#E3F6EF;color:#00866F}.p-skip{background:#FFF4D6;color:#8A6100}.p-err{background:#FDECE7;color:#C2381B}
  .msgs{color:#C2381B;font-size:12.5px}.msgs.skip{color:#8A6100}
  .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:11px 20px;font-family:var(--font-body);font-size:14px;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none}
  .btn-primary{background:var(--amber);color:#fff}
  .btn-primary:disabled{background:#B4B4B4;cursor:not-allowed}
  .btn-ghost{background:#fff;color:#0F0F17;border-color:#E5E4EC}
  .formact{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:16px 0}
  .alert-err{background:#FDECE7;border:1px solid #F2C2C2;color:#C2381B;padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
  .alert-info{background:#F0F4FE;border:1px solid #D7E0FA;color:var(--amber);padding:12px 14px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
  @media(max-width:700px){.stats{grid-template-columns:repeat(2,1fr)}}
</style>

<div class="crumb"><a href="{{ route('admin.assets.index') }}">{{ $plural }}</a> / <a href="{{ route('admin.assets.import') }}">Import</a> / Preview</div>
<h1 class="page-h1">Import preview</h1>
<p class="page-sub">{{ $job->original_name }}</p>

@if (session('error'))<div class="alert-err">{{ session('error') }}</div>@endif

@if ($job->status === 'committed')
  <div class="alert-info">This import was committed on {{ $job->committed_at?->format('d M Y H:i') }}: {{ $job->valid_rows }} created, {{ $job->skipped_rows }} skipped, {{ $job->error_rows }} with errors.</div>
  <a class="btn btn-ghost" href="{{ route('admin.assets.index') }}">Back to {{ strtolower($plural) }}</a>
@elseif ($fileGone)
  <div class="alert-err">The uploaded file is no longer available. Please upload it again.</div>
  <a class="btn btn-primary" href="{{ route('admin.assets.import') }}">Upload again</a>
@else
  <div class="stats">
    <div class="stat"><div class="v">{{ $job->total_rows }}</div><div class="l">Rows in file</div></div>
    <div class="stat"><div class="v" style="color:#00866F">{{ $job->valid_rows }}</div><div class="l">Ready to create</div></div>
    <div class="stat"><div class="v" style="color:#8A6100">{{ $job->skipped_rows }}</div><div class="l">Skipped (already exist)</div></div>
    <div class="stat"><div class="v" style="color:#C2381B">{{ $job->error_rows }}</div><div class="l">With errors</div></div>
  </div>

  <div class="formact">
    <form method="POST" action="{{ route('admin.assets.import.commit', $job) }}" id="commitForm">
      @csrf
      <button class="btn btn-primary" type="submit" id="commitBtn" @disabled($job->valid_rows < 1)>
        Create {{ $job->valid_rows }} valid {{ $job->valid_rows === 1 ? 'row' : 'rows' }}
      </button>
    </form>
    @if ($job->error_rows + $job->skipped_rows > 0)
      <a class="btn btn-ghost" href="{{ route('admin.assets.import.errors', $job) }}">Download issues CSV</a>
    @endif
    <a class="btn btn-ghost" href="{{ route('admin.assets.import') }}">Upload a different file</a>
  </div>
  @if ($job->error_rows > 0)
    <div class="alert-info">Rows with errors will not be created. Fix them in your file and upload again, or create the valid rows now and import the rest later.</div>
  @endif

  <div class="card"><div class="tblwrap">
    <table>
      <thead><tr><th>Row</th><th>Result</th><th>Code</th><th>Name</th><th>Client</th><th>Site</th><th>Category</th><th>Parent</th><th>Notes</th></tr></thead>
      <tbody>
      @foreach ($rows as $r)
        <tr>
          <td>{{ $r['n'] }}</td>
          <td>
            @if ($r['status'] === 'ok')<span class="pill p-ok">Ready</span>
            @elseif ($r['status'] === 'skip')<span class="pill p-skip">Skipped</span>
            @else<span class="pill p-err">Error</span>@endif
          </td>
          <td>{{ $r['data']['code'] ?: 'auto' }}</td>
          <td>{{ $r['data']['name'] }}</td>
          <td>{{ $r['data']['client'] }}</td>
          <td>{{ $r['data']['site'] }}</td>
          <td>{{ $r['data']['category'] }}</td>
          <td>{{ $r['data']['parent'] }}</td>
          <td>@foreach ($r['messages'] as $m)<div class="msgs {{ $r['status'] === 'skip' ? 'skip' : '' }}">{{ $m }}</div>@endforeach</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div></div>
  @if ($job->total_rows > $shown)
    <p class="page-sub">Showing the first {{ $shown }} of {{ $job->total_rows }} rows. All rows are checked and created on commit; the issues CSV lists every problem.</p>
  @endif

  <script>
    document.getElementById('commitForm')?.addEventListener('submit', function () {
      const b = document.getElementById('commitBtn'); b.disabled = true; b.textContent = 'Creating…';
    });
  </script>
@endif
@endsection