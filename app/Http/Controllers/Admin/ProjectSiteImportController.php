<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ProjectSite;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectSiteImportController extends Controller
{
    public function show()
    {
        return view('admin.sites.import');
    }

    public function sample()
    {
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="sites-sample.csv"'];

        $csv  = "site_code,name,site_type,client_code,address,city,lat,lng,geofence_radius_meters,always_enforce_geofence,contact_name,contact_phone,access_instructions,is_active\n";
        $csv .= "ST-1001,Airport Road Cafe,client_site,CL-1001,\"Shop 12, Airport Road\",Dubai,25.2532,55.3657,250,1,John Smith,+971501234567,\"Service entrance at gate 3\",1\n";
        $csv .= ",Harbour Kitchen,client_site,CL-1001,\"Marina Walk\",Dubai,25.0821,55.1399,300,0,Sara Khan,+971502223344,,1\n";
        $csv .= ",Main Workshop,tenant_workshop,,\"Al Quoz Industrial 3\",Dubai,25.1388,55.2224,500,1,,,,1\n";

        return response($csv, 200, $headers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv'             => 'required|file|mimes:csv,txt|max:2048',
            'update_existing' => 'sometimes|boolean',
        ]);

        $file = $request->file('csv');
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) return back()->with('error', 'Could not read the file.');

        $header = fgetcsv($handle);
        if (! $header) return back()->with('error', 'The file is empty or not a valid CSV.');
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        if (! in_array('name', $header)) {
            fclose($handle);
            return back()->with('error', 'The CSV must have a "name" column.');
        }

        $tenantId       = Tenant::current()?->id ?? auth()->user()?->tenant_id;
        $updateExisting = (bool) $request->input('update_existing', false);

        // Cache clients by code so we don't hit the DB per row
        $clientsByCode = Client::where('tenant_id', $tenantId)
            ->pluck('id', 'client_code')
            ->mapWithKeys(fn($id, $code) => [strtoupper($code) => $id]);

        $created = $updated = $skipped = 0;
        $errors  = [];
        $rowNum  = 1;

        DB::beginTransaction();
        try {
            while (($raw = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count(array_filter($raw)) === 0) continue;

                $row = array_combine($header, array_pad($raw, count($header), null));

                $name = trim($row['name'] ?? '');
                if ($name === '') {
                    $errors[] = "Row {$rowNum}: name is required — skipped.";
                    $skipped++;
                    continue;
                }

                $siteType = strtolower(trim($row['site_type'] ?? 'client_site'));
                if (! in_array($siteType, ['client_site','tenant_workshop'])) {
                    $siteType = 'client_site';
                }

                // Resolve client_id from client_code (only for client_site)
                $clientId = null;
                $clientCode = strtoupper(trim($row['client_code'] ?? ''));
                if ($siteType === 'client_site') {
                    if (! $clientCode) {
                        $errors[] = "Row {$rowNum} ({$name}): client_code is required for a client_site — skipped.";
                        $skipped++;
                        continue;
                    }
                    $clientId = $clientsByCode[$clientCode] ?? null;
                    if (! $clientId) {
                        $errors[] = "Row {$rowNum} ({$name}): client with code {$clientCode} not found — skipped.";
                        $skipped++;
                        continue;
                    }
                }

                $code = trim($row['site_code'] ?? '');
                $code = $code !== '' ? strtoupper($code) : null;

                // Dedup check — by site_code first, then by (client_id + name)
                $existing = null;
                if ($code) {
                    $existing = ProjectSite::where('tenant_id', $tenantId)->where('site_code', $code)->first();
                }
                if (! $existing && $clientId) {
                    $existing = ProjectSite::where('tenant_id', $tenantId)
                        ->where('client_id', $clientId)
                        ->where('name', $name)
                        ->first();
                }

                $payload = [
                    'tenant_id'               => $tenantId,
                    'site_code'               => $code,   // null → model's booted() auto-generates
                    'name'                    => $name,
                    'site_type'               => $siteType,
                    'client_id'               => $clientId,
                    'address'                 => trim($row['address'] ?? '') ?: null,
                    'city'                    => trim($row['city'] ?? '') ?: null,
                    'lat'                     => $this->floatOrNull($row['lat'] ?? null),
                    'lng'                     => $this->floatOrNull($row['lng'] ?? null),
                    'geofence_radius_meters'  => (int) ($row['geofence_radius_meters'] ?? 250) ?: 250,
                    'always_enforce_geofence' => (bool) ($row['always_enforce_geofence'] ?? 0),
                    'contact_name'            => trim($row['contact_name'] ?? '') ?: null,
                    'contact_phone'           => trim($row['contact_phone'] ?? '') ?: null,
                    'access_instructions'     => trim($row['access_instructions'] ?? '') ?: null,
                    'is_active'               => isset($row['is_active']) ? (bool) $row['is_active'] : true,
                ];

                if ($existing) {
                    if (! $updateExisting) { $skipped++; continue; }
                    // Don't overwrite the existing code with null
                    if (! $code) unset($payload['site_code']);
                    $existing->update($payload);
                    $updated++;
                } else {
                    ProjectSite::create($payload);
                    $created++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        fclose($handle);

        $msg = "Imported: {$created} new, {$updated} updated, {$skipped} skipped.";
        if (! empty($errors)) $msg .= ' Some rows had issues — see details.';

        return redirect()->route('admin.sites.index')
            ->with('success', $msg)
            ->with('import_errors', $errors);
    }

    private function floatOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        $f = filter_var(trim($v), FILTER_VALIDATE_FLOAT);
        return $f === false ? null : $f;
    }
}