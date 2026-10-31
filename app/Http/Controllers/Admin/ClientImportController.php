<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientImportController extends Controller
{
    public function show()
    {
        return view('admin.clients.import');
    }

    public function sample()
    {
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="clients-sample.csv"'];

        $csv  = "client_code,name,contact_person,email,phone,tax_id,payment_terms_days,billing_address,notes,is_active\n";
        $csv .= "CL-1001,Acme Corporation,John Smith,john@acme.com,+971501234567,100123456700003,30,\"Office 12, Business Bay, Dubai\",VIP client,1\n";
        $csv .= ",Northgate Foods,Sara Khan,sara@northgate.ae,+971502223344,100987654300003,45,\"Outlet 7, Riverside, Dubai\",,1\n";

        return response($csv, 200, $headers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv'           => 'required|file|mimes:csv,txt|max:2048',
            'update_existing' => 'sometimes|boolean',
        ]);

        $file = $request->file('csv');
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) return back()->with('error', 'Could not read the file.');

        // First row is header
        $header = fgetcsv($handle);
        if (! $header) return back()->with('error', 'The file is empty or not a valid CSV.');
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Required at minimum: name
        if (! in_array('name', $header)) {
            fclose($handle);
            return back()->with('error', 'The CSV must have a "name" column.');
        }

        $tenantId      = Tenant::current()?->id ?? auth()->user()?->tenant_id;
        $updateExisting = (bool) $request->input('update_existing', false);

        $created = $updated = $skipped = 0;
        $errors  = [];
        $rowNum  = 1;

        DB::beginTransaction();
        try {
            while (($raw = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count(array_filter($raw)) === 0) continue; // skip blank lines

                $row = array_combine($header, array_pad($raw, count($header), null));

                $name = trim($row['name'] ?? '');
                if ($name === '') {
                    $errors[] = "Row {$rowNum}: name is required — skipped.";
                    $skipped++;
                    continue;
                }

                $code = trim($row['client_code'] ?? '');
                $code = $code !== '' ? strtoupper($code) : null;

                // Dedup check — by client_code first, then by name
                $existing = null;
                if ($code) {
                    $existing = Client::where('tenant_id', $tenantId)->where('client_code', $code)->first();
                }
                if (! $existing) {
                    $existing = Client::where('tenant_id', $tenantId)->where('name', $name)->first();
                }

                $payload = [
                    'tenant_id'               => $tenantId,
                    'client_code'             => $code ?: $this->nextClientCode($tenantId),
                    'name'                    => $name,
                    'contact_person'          => trim($row['contact_person'] ?? '') ?: null,
                    'email'                   => trim($row['email'] ?? '') ?: null,
                    'phone'                   => trim($row['phone'] ?? '') ?: null,
                    'tax_id'                  => trim($row['tax_id'] ?? '') ?: null,
                    'payment_terms_days'      => (int) ($row['payment_terms_days'] ?? 30) ?: 30,
                    'billing_address'         => trim($row['billing_address'] ?? '') ?: null,
                    'notes'                   => trim($row['notes'] ?? '') ?: null,
                    'is_active'               => isset($row['is_active']) ? (bool) $row['is_active'] : true,
                    'asset_visibility_enabled'=> false,
                ];

                if ($existing) {
                    if (! $updateExisting) { $skipped++; continue; }
                    $existing->update($payload);
                    $updated++;
                } else {
                    Client::create($payload);
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

        return redirect()->route('admin.clients.index')
            ->with('success', $msg)
            ->with('import_errors', $errors);
    }

    private function nextClientCode(?int $tenantId): string
    {
        $last = Client::withTrashed()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('client_code', 'like', 'CL-%')
            ->orderByDesc('id')
            ->value('client_code');

        $next = ($last && preg_match('/CL-(\d+)/', $last, $m)) ? ((int) $m[1]) + 1 : 1;
        return 'CL-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}