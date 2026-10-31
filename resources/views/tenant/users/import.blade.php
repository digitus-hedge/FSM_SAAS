@extends('layouts.admin')
@section('title', 'Import users')
@section('content')
<style>
.imp-wrap{max-width:960px}
.imp-wrap h1{font:700 26px var(--font-display);letter-spacing:-.4px;margin-bottom:4px}
.lede{color:var(--muted);margin-bottom:24px}
.stepper{display:flex;align-items:center;gap:10px;margin-bottom:24px;flex-wrap:wrap}
.step{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:13px;font-weight:600}
.step .n{width:26px;height:26px;border-radius:50%;background:var(--soft);color:var(--muted);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
.step.done .n{background:var(--green);color:#fff}
.step.active{color:var(--ink)}
.step.active .n{background:var(--ink);color:var(--yellow)}
.step .arr{color:var(--subtle)}
.panel{background:#fff;border:1px solid var(--line);border-radius:18px;padding:32px}
.panel h2{font:700 18px var(--font-display);margin-bottom:6px}
.drop{display:block;border:2px dashed var(--line);border-radius:14px;padding:48px 24px;text-align:center;background:var(--soft);cursor:pointer}
.drop:hover{border-color:var(--yellow);background:var(--yellow-soft)}
.drop svg{width:40px;height:40px;color:var(--muted);margin-bottom:12px}
.drop .title{font-weight:700;font-size:15px;margin-bottom:4px}
.drop .hint{color:var(--muted);font-size:13px;margin-bottom:14px}
.drop input{display:none}
.help{margin-top:20px;background:var(--soft);border-radius:12px;padding:14px 18px}
.help summary{cursor:pointer;font-weight:600;font-size:13px;list-style:none;display:flex;align-items:center;gap:8px}
.help summary::-webkit-details-marker{display:none}
.help .chev{width:12px;height:12px;transition:transform .15s;color:var(--muted)}
.help[open] .chev{transform:rotate(90deg)}
.help .fields{margin-top:12px;font-size:13px;color:var(--muted);display:grid;grid-template-columns:150px 1fr;gap:6px 16px}
.help .fields b{color:var(--ink);font-family:'JetBrains Mono',Consolas,monospace;font-size:12px}
.help .sample{color:var(--amber);text-decoration:underline;font-size:12.5px;margin-left:auto}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.stat{background:var(--soft);border-radius:12px;padding:14px 18px}
.stat .lbl{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em}
.stat .num{font:700 26px var(--font-display);margin-top:4px;line-height:1}
.stat.ok .num{color:var(--green)}
.stat.err .num{color:var(--red)}
.tbl{width:100%;border-collapse:collapse;font-size:13px;border:1px solid var(--line);border-radius:12px;overflow:hidden}
.tbl thead th{text-align:left;background:var(--soft);color:var(--muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;padding:10px 14px}
.tbl tbody td{padding:11px 14px;border-top:1px solid var(--line);vertical-align:top}
.tbl tr.err{background:var(--red-soft)}
.tbl tr.ok td:first-child::before{content:"● ";color:var(--green)}
.tbl tr.err td:first-child::before{content:"● ";color:var(--red)}
.err-msg{color:var(--red);font-size:11.5px;margin-top:3px;font-weight:600}
.role{font-family:'JetBrains Mono',Consolas,monospace;font-size:11.5px;background:var(--soft);padding:2px 8px;border-radius:5px;color:var(--amber);font-weight:600;display:inline-block}
.skip-toggle{display:flex;align-items:center;gap:10px;margin-top:16px;padding:12px 16px;background:var(--yellow-soft);border:1px solid var(--yellow);color:var(--amber);border-radius:10px;font-size:13px;font-weight:600}
.skip-toggle input{width:16px;height:16px}
.actions{display:flex;gap:10px;align-items:center;margin-top:24px;padding-top:20px;border-top:1px solid var(--line);justify-content:flex-end}
.btn-y{background:var(--yellow);color:var(--ink);font:700 13.5px var(--font-body);padding:11px 24px;border-radius:999px;border:0;cursor:pointer;text-decoration:none;display:inline-block}
.btn-y:hover{background:color-mix(in srgb, var(--yellow) 85%, black 15%)}
.btn-ghost{background:transparent;border:1px solid var(--line);color:var(--ink);font-weight:600;font-size:13px;padding:10px 18px;border-radius:999px;cursor:pointer;text-decoration:none;display:inline-block}
.btn-ghost:hover{background:var(--soft)}
.result-icon{width:72px;height:72px;border-radius:50%;background:var(--green-soft);color:var(--green);display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.result-icon svg{width:36px;height:36px}
.result h2{text-align:center;font-size:22px;margin-bottom:6px}
.result .lede{text-align:center;margin-bottom:24px}
.result-stats{display:flex;justify-content:center;gap:40px;margin-bottom:28px;flex-wrap:wrap}
.result-stat{text-align:center}
.result-stat .num{font:700 36px var(--font-display);line-height:1}
.result-stat .lbl{font-size:12px;color:var(--muted);margin-top:4px;font-weight:600}
</style>

<div class="imp-wrap">
<h1>Import users from CSV</h1>
<p class="lede">Bulk-create staff and client users. Each gets an invitation email with a one-time link to set their password.</p>

<div class="stepper">
    <div class="step {{ $step === 1 ? 'active' : ($step > 1 ? 'done' : '') }}"><span class="n">1</span>Upload</div>
    <span class="arr">▸</span>
    <div class="step {{ $step === 2 ? 'active' : ($step > 2 ? 'done' : '') }}"><span class="n">2</span>Preview &amp; fix</div>
    <span class="arr">▸</span>
    <div class="step {{ $step === 3 ? 'active' : '' }}"><span class="n">3</span>Done</div>
</div>

@if ($step === 1)
    <div class="panel">
        <h2>Upload a CSV file</h2>
        <p class="lede">Up to 500 rows at once. Larger files, split and upload in batches.</p>

        @if ($errors->any())
            <div style="background:var(--red-soft);border:1px solid var(--red);color:color-mix(in srgb, var(--red) 75%, black 25%);padding:12px 16px;border-radius:10px;margin-bottom:16px">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('tenant.users.import.preview') }}" enctype="multipart/form-data" id="upload-form">
            @csrf
            <label class="drop" for="file-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <div class="title">Click to choose a CSV</div>
                <div class="hint">or drag &amp; drop below</div>
                <input type="file" name="file" id="file-input" accept=".csv,text/csv" onchange="document.getElementById('upload-form').submit()">
            </label>
        </form>

        <details class="help">
            <summary>
                <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                What columns are required?
                <a class="sample" href="{{ route('tenant.users.import.sample') }}">Download sample CSV</a>
            </summary>
            <div class="fields">
                <b>name</b>            <span>Required. 2 to 150 characters.</span>
                <b>email</b>           <span>Required. Must be unique in this workspace.</span>
                <b>phone</b>           <span>Optional. International format (+91…) preferred.</span>
                <b>role</b>            <span>Required. One of: company_admin, operations_manager, team_lead, field_technician, front_desk, finance, client_user.</span>
                <b>employee_code</b>   <span>Optional. Internal staff ID.</span>
                <b>team_lead_email</b> <span>Optional. Email of an existing team lead for technicians.</span>
            </div>
        </details>
    </div>

@elseif ($step === 2)
    <div class="panel">
        <h2>Preview &amp; fix</h2>
        <p class="lede">Review every row before creating accounts. Fix issues or skip bad rows.</p>

        <div class="stats">
            <div class="stat"><div class="lbl">Total rows</div><div class="num">{{ $stats['total'] }}</div></div>
            <div class="stat ok"><div class="lbl">Ready</div><div class="num">{{ $stats['ready'] }}</div></div>
            <div class="stat err"><div class="lbl">With errors</div><div class="num">{{ $stats['errors'] }}</div></div>
        </div>

        <table class="tbl">
            <thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr class="{{ empty($r['errors']) ? 'ok' : 'err' }}">
                    <td>{{ $r['row_num'] }}</td>
                    <td>{{ $r['data']['name'] ?? '—' }}</td>
                    <td>
                        {{ $r['data']['email'] ?? '—' }}
                        @foreach ($r['errors'] as $e)
                            @if (str_contains(strtolower($e), 'email'))
                                <div class="err-msg">{{ $e }}</div>
                            @endif
                        @endforeach
                    </td>
                    <td>{{ $r['data']['phone'] ?: '—' }}</td>
                    <td>
                        @if (! empty($r['data']['role']))
                            <span class="role" style="{{ collect($r['errors'])->contains(fn($e) => str_contains($e, 'Role')) ? 'background:var(--red-soft);color:var(--red)' : '' }}">{{ $r['data']['role'] }}</span>
                        @else
                            <span class="err-msg">Missing role</span>
                        @endif
                        @foreach ($r['errors'] as $e)
                            @if (! str_contains(strtolower($e), 'email'))
                                <div class="err-msg">{{ $e }}</div>
                            @endif
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('tenant.users.import.commit') }}">
            @csrf
            @if ($stats['errors'] > 0)
                <label class="skip-toggle">
                    <input type="checkbox" name="skip_errors" value="1" checked>
                    Skip rows with errors ({{ $stats['errors'] }} row{{ $stats['errors'] === 1 ? '' : 's' }} will not be imported)
                </label>
            @endif

            <div class="actions">
                <a href="{{ route('tenant.users.import.show') }}" class="btn-ghost">← Back</a>
                <button type="submit" class="btn-y" @if ($stats['ready'] === 0) disabled @endif>
                    Create {{ $stats['ready'] }} user{{ $stats['ready'] === 1 ? '' : 's' }} &amp; send invitations
                </button>
            </div>
        </form>
    </div>

@elseif ($step === 3)
    <div class="panel result">
        <div class="result-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2>Users imported</h2>
        <p class="lede">Invitations have been emailed. Each link works once and expires in 7 days.</p>

        <div class="result-stats">
            <div class="result-stat"><div class="num" style="color:var(--green)">{{ $result['created'] }}</div><div class="lbl">Created</div></div>
            <div class="result-stat"><div class="num" style="color:var(--green)">{{ $result['invited'] }}</div><div class="lbl">Invitations sent</div></div>
            <div class="result-stat"><div class="num" style="color:var(--red)">{{ $result['skipped'] }}</div><div class="lbl">Skipped</div></div>
            @if (count($result['failed']))
                <div class="result-stat"><div class="num" style="color:var(--red)">{{ count($result['failed']) }}</div><div class="lbl">Failed</div></div>
            @endif
        </div>

        <div class="actions" style="justify-content:center;border-top:0;padding-top:0">
            <a href="{{ route('tenant.users.import.show') }}" class="btn-ghost">Import more</a>
            <a href="{{ route('tenant.users.index') }}" class="btn-y">View users</a>
        </div>
    </div>
@endif
</div>

<script>
// Drag-and-drop → trigger file input
const drop = document.querySelector('.drop');
if (drop) {
    const input = document.getElementById('file-input');
    ['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.style.borderColor='var(--yellow)'; drop.style.background='var(--yellow-soft)'; }));
    ['dragleave','drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.style.borderColor=''; drop.style.background=''; }));
    drop.addEventListener('drop', e => {
        e.preventDefault();
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            document.getElementById('upload-form').submit();
        }
    });
}
</script>
@endsection