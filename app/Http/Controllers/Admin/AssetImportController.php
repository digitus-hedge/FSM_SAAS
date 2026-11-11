<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ImportJob;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AssetImportController extends Controller
{
    private const MAX_ROWS = 2000;
    private const STATUSES = ['active', 'under_repair', 'inactive'];
    private const REQUIRED = ['name', 'client_code', 'site_code', 'category'];

    private function allowed(): bool
    {
        return (bool) auth()->user()?->canDo('asset.import');
    }

    private function tenantId(): int
    {
        return (int) (Tenant::current()?->id ?? auth()->user()?->tenant_id);
    }

    private function job(ImportJob $job): ImportJob
    {
        abort_unless($job->entity === 'assets' && (int) $job->tenant_id === $this->tenantId(), 404);
        return $job;
    }

    public function show()
    {
        abort_unless($this->allowed(), 403);
        return view('admin.assets.import');
    }

    public function sample()
    {
        abort_unless($this->allowed(), 403);

        $csv  = "asset_code,name,client_code,site_code,category,parent_asset_code,manufacturer,model,serial_number,location_within_site,installation_date,warranty_expiry_date,status,notes\n";
        $csv .= "AS-1001,Chiller Unit 1,CL-1001,ST-1001,HVAC,,Carrier,30XA,SN-778812,Roof,2024-03-01,2027-03-01,active,\n";
        $csv .= ",Chiller Pump A,CL-1001,ST-1001,HVAC,AS-1001,Grundfos,CR 32,SN-100245,Plant room,2024-03-01,2026-03-01,active,\"Needs quarterly check\"\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="assets-sample.csv"',
        ]);
    }

    /** Step 1: upload and validate. Nothing is created yet. */
    public function upload(Request $request)
    {
        abort_unless($this->allowed(), 403);

        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:2048']);

        $file = $request->file('csv');
        $tid  = $this->tenantId();
        $path = $file->storeAs("imports/{$tid}", 'assets-' . now()->format('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.csv', 'local');

        $parsed = $this->parse(Storage::disk('local')->path($path));
        if (isset($parsed['fatal'])) {
            Storage::disk('local')->delete($path);
            return back()->with('error', $parsed['fatal']);
        }

        $job = ImportJob::create([
            'tenant_id'          => $tid,
            'entity'             => 'assets',
            'file_path'          => $path,
            'original_name'      => $file->getClientOriginalName(),
            'status'             => 'validated',
            'created_by_user_id' => auth()->id(),
        ] + $this->counts($parsed['rows']));

        return redirect()->route('admin.assets.import.preview', $job);
    }

    public function preview(ImportJob $job)
    {
        abort_unless($this->allowed(), 403);
        $this->job($job);

        $path = Storage::disk('local')->path((string) $job->file_path);
        if ($job->status === 'committed' || ! is_file($path)) {
            return view('admin.assets.import-preview', ['job' => $job, 'rows' => [], 'shown' => 0, 'fileGone' => ! is_file($path)]);
        }

        $parsed = $this->parse($path);
        if (isset($parsed['fatal'])) {
            return redirect()->route('admin.assets.import')->with('error', $parsed['fatal']);
        }

        // Keep the stored counts honest if the data changed since upload.
        $job->update($this->counts($parsed['rows']));

        $limit = 300;
        return view('admin.assets.import-preview', [
            'job'      => $job->fresh(),
            'rows'     => array_slice($parsed['rows'], 0, $limit),
            'shown'    => min($limit, count($parsed['rows'])),
            'fileGone' => false,
        ]);
    }

    /** Step 2: create the valid rows. Rows with errors are skipped. */
    public function commit(ImportJob $job)
    {
        abort_unless($this->allowed(), 403);
        $this->job($job);

        if ($job->status === 'committed') {
            return redirect()->route('admin.assets.index')->with('success', 'This import was already committed.');
        }

        $path = Storage::disk('local')->path((string) $job->file_path);
        if (! is_file($path)) {
            return redirect()->route('admin.assets.import')->with('error', 'The uploaded file is no longer available. Please upload it again.');
        }

        // Validate again at commit time so the data is checked as it is now.
        $parsed = $this->parse($path);
        if (isset($parsed['fatal'])) {
            return redirect()->route('admin.assets.import')->with('error', $parsed['fatal']);
        }

        $tid       = $this->tenantId();
        $idByCode  = Asset::withTrashed()->where('tenant_id', $tid)->pluck('id', 'asset_code')
            ->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
        $created   = 0;
        $failed    = [];

        try {
            DB::transaction(function () use ($parsed, $tid, &$idByCode, &$created, &$failed) {
                foreach ($parsed['rows'] as $r) {
                    if ($r['status'] !== 'ok') continue;
                    $p = $r['payload'];

                    $parentId = null;
                    if ($p['parent_code']) {
                        $parentId = $idByCode[$p['parent_code']] ?? null;
                        if (! $parentId) { $failed[] = "Row {$r['n']}: parent not found."; continue; }
                    }

                    try {
                        $asset = new Asset();
                        $asset->forceFill($p['fields'] + ['tenant_id' => $tid, 'parent_asset_id' => $parentId])->save();
                        $created++;
                        if ($p['code']) $idByCode[$p['code']] = $asset->id;
                    } catch (ValidationException $e) {
                        $failed[] = "Row {$r['n']}: " . collect($e->errors())->flatten()->first();
                    }
                }
            });
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed']);
            return redirect()->route('admin.assets.import.preview', $job)->with('error', 'Import failed: ' . $e->getMessage());
        }

        $c = $this->counts($parsed['rows']);
        $job->update([
            'status'       => 'committed',
            'valid_rows'   => $created,
            'error_rows'   => $c['error_rows'] + count($failed),
            'skipped_rows' => $c['skipped_rows'],
            'committed_at' => now(),
        ]);

        $msg = "Imported {$created} asset(s). {$c['skipped_rows']} skipped (already exist), " . ($c['error_rows'] + count($failed)) . ' with errors.';
        return redirect()->route('admin.assets.index')->with('success', $msg);
    }

    /** CSV of the rows that have errors or were skipped, for fixing and re-uploading. */
    public function errors(ImportJob $job)
    {
        abort_unless($this->allowed(), 403);
        $this->job($job);

        $path = Storage::disk('local')->path((string) $job->file_path);
        abort_unless(is_file($path), 404);

        $parsed = $this->parse($path);
        abort_if(isset($parsed['fatal']), 404);

        return response()->streamDownload(function () use ($parsed) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['row', 'result', 'message']);
            foreach ($parsed['rows'] as $r) {
                if ($r['status'] === 'ok') continue;
                fputcsv($out, [$r['n'], $r['status'], implode(' | ', $r['messages'])]);
            }
            fclose($out);
        }, 'asset-import-issues.csv', ['Content-Type' => 'text/csv']);
    }

    private function counts(array $rows): array
    {
        $c = ['total_rows' => count($rows), 'valid_rows' => 0, 'error_rows' => 0, 'skipped_rows' => 0];
        foreach ($rows as $r) {
            if ($r['status'] === 'ok') $c['valid_rows']++;
            elseif ($r['status'] === 'skip') $c['skipped_rows']++;
            else $c['error_rows']++;
        }
        return $c;
    }

    /**
     * Reads and validates the CSV. Creates nothing.
     * Returns ['rows' => [...]] or ['fatal' => message].
     * Each row: n (file line), data, status ok|skip|error, messages[], payload.
     */
    private function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) return ['fatal' => 'Could not read the file.'];

        $header = fgetcsv($handle);
        if (! $header) { fclose($handle); return ['fatal' => 'The file is empty or not a valid CSV.']; }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]); // strip BOM
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $missing = array_diff(self::REQUIRED, $header);
        if ($missing) {
            fclose($handle);
            return ['fatal' => 'Missing required column(s): ' . implode(', ', $missing) . '.'];
        }

        $tid = $this->tenantId();

        $clients = Client::where('tenant_id', $tid)->pluck('id', 'client_code')
            ->mapWithKeys(fn ($id, $c) => [strtoupper($c) => $id])->all();
        $sites = ProjectSite::where('tenant_id', $tid)->get(['id', 'site_code', 'client_id', 'site_type'])
            ->mapWithKeys(fn ($s) => [strtoupper((string) $s->site_code) => $s])->all();
        $catsByCode = $catsByName = [];
        foreach (ServiceCategory::where('tenant_id', $tid)->where('is_active', true)->get(['id', 'code', 'name']) as $c) {
            $catsByCode[strtoupper($c->code)] = $c->id;
            $catsByName[mb_strtolower($c->name)] = $c->id;
        }
        $existing = Asset::withTrashed()->where('tenant_id', $tid)->get(['id', 'asset_code', 'project_site_id'])
            ->mapWithKeys(fn ($a) => [strtoupper($a->asset_code) => $a])->all();

        $rows = [];
        $fileCodes = []; // asset_code => site id, for earlier valid rows in this file
        $n = 1;

        while (($raw = fgetcsv($handle)) !== false) {
            $n++;
            if (count(array_filter($raw, fn ($v) => trim((string) $v) !== '')) === 0) continue;
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);
                return ['fatal' => 'The file has more than ' . self::MAX_ROWS . ' rows. Please split it.'];
            }

            $d = array_combine($header, array_slice(array_pad($raw, count($header), null), 0, count($header)));
            $g = fn ($k) => trim((string) ($d[$k] ?? ''));
            $msgs = [];

            $name = $g('name');
            if ($name === '') $msgs[] = 'name is required';
            elseif (mb_strlen($name) > 180) $msgs[] = 'name is longer than 180 characters';

            $clientCode = strtoupper($g('client_code'));
            $clientId = $clients[$clientCode] ?? null;
            if ($clientCode === '') $msgs[] = 'client_code is required';
            elseif (! $clientId) $msgs[] = "client {$clientCode} not found";

            $siteCode = strtoupper($g('site_code'));
            $site = $sites[$siteCode] ?? null;
            if ($siteCode === '') $msgs[] = 'site_code is required';
            elseif (! $site) $msgs[] = "site {$siteCode} not found";
            elseif ($clientId && (int) $site->client_id !== (int) $clientId) $msgs[] = "site {$siteCode} does not belong to client {$clientCode}";

            $cat = $g('category');
            $catId = $catsByCode[strtoupper($cat)] ?? $catsByName[mb_strtolower($cat)] ?? null;
            if ($cat === '') $msgs[] = 'category is required';
            elseif (! $catId) $msgs[] = "category \"{$cat}\" not found or inactive";

            $status = strtolower($g('status')) ?: 'active';
            if (! in_array($status, self::STATUSES, true)) $msgs[] = 'status must be active, under_repair or inactive';

            $install = $this->date($g('installation_date'), $msgs, 'installation_date');
            $warranty = $this->date($g('warranty_expiry_date'), $msgs, 'warranty_expiry_date');
            if ($install && $warranty && $warranty < $install) $msgs[] = 'warranty_expiry_date is before installation_date';

            foreach (['manufacturer' => 120, 'model' => 120, 'serial_number' => 120, 'location_within_site' => 255] as $f => $max) {
                if (mb_strlen($g($f)) > $max) $msgs[] = "{$f} is longer than {$max} characters";
            }

            $code = strtoupper($g('asset_code')) ?: null;
            if ($code && mb_strlen($code) > 80) $msgs[] = 'asset_code is longer than 80 characters';

            // Existing code: skip, unless the row is otherwise broken.
            $skip = false;
            if ($code && isset($existing[$code])) $skip = true;
            elseif ($code && array_key_exists($code, $fileCodes)) $msgs[] = "asset_code {$code} appears more than once in the file";

            // Parent: an existing asset, or an earlier valid row in this file.
            $parentCode = strtoupper($g('parent_asset_code')) ?: null;
            if ($parentCode && $site) {
                if ($parentCode === $code) {
                    $msgs[] = 'an asset cannot be its own parent';
                } elseif (isset($existing[$parentCode])) {
                    if ((int) $existing[$parentCode]->project_site_id !== (int) $site->id) $msgs[] = "parent {$parentCode} is at a different site";
                } elseif (array_key_exists($parentCode, $fileCodes)) {
                    if ((int) $fileCodes[$parentCode] !== (int) $site->id) $msgs[] = "parent {$parentCode} is at a different site";
                } else {
                    $msgs[] = "parent {$parentCode} not found (a parent must exist already or appear earlier in the file)";
                }
            }

            $rowStatus = $msgs ? 'error' : ($skip ? 'skip' : 'ok');
            if ($skip && ! $msgs) $msgs[] = "asset_code {$code} already exists — skipped";

            $payload = null;
            if ($rowStatus === 'ok') {
                $payload = [
                    'code'        => $code,
                    'parent_code' => $parentCode,
                    'fields'      => [
                        'asset_code'           => $code,
                        'name'                 => $name,
                        'client_id'            => $clientId,
                        'project_site_id'      => $site->id,
                        'service_category_id'  => $catId,
                        'manufacturer'         => $g('manufacturer') ?: null,
                        'model'                => $g('model') ?: null,
                        'serial_number'        => $g('serial_number') ?: null,
                        'location_within_site' => $g('location_within_site') ?: null,
                        'installation_date'    => $install,
                        'warranty_expiry_date' => $warranty,
                        'status'               => $status,
                        'notes'                => $g('notes') ?: null,
                    ],
                ];
                if ($code) $fileCodes[$code] = $site->id;
            }

            $rows[] = [
                'n'        => $n,
                'data'     => ['code' => $code, 'name' => $name, 'client' => $clientCode, 'site' => $siteCode, 'category' => $cat, 'parent' => $parentCode, 'status' => $status],
                'status'   => $rowStatus,
                'messages' => $msgs,
                'payload'  => $payload,
            ];
        }
        fclose($handle);

        if (! $rows) return ['fatal' => 'The file has no data rows.'];

        return ['rows' => $rows];
    }

    private function date(string $v, array &$msgs, string $field): ?string
    {
        if ($v === '') return null;
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        if (! $d || $d->format('Y-m-d') !== $v) {
            $msgs[] = "{$field} must be a date like 2026-03-31";
            return null;
        }
        return $v;
    }
}