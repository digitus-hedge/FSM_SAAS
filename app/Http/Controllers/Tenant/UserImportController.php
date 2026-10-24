<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Users\UserCsvImporter;
use Illuminate\Http\Request;

class UserImportController extends Controller
{
    public function __construct(private UserCsvImporter $importer) {}

    public function show()
    {
        abort_unless(Tenant::current(), 404);
        return view('tenant.users.import', ['step' => 1]);
    }

    public function preview(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Select a CSV file to upload.',
            'file.mimes'    => 'The file must be a CSV.',
            'file.max'      => 'The file is too large (max 2 MB).',
        ]);

        try {
            $parsed    = $this->importer->parse($request->file('file')->getRealPath());
            $validated = $this->importer->validate($parsed, $tenant);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        session(['user_import_rows' => $validated]);

        $stats = [
            'total'  => count($validated),
            'ready'  => count(array_filter($validated, fn($r) => empty($r['errors']))),
            'errors' => count(array_filter($validated, fn($r) => ! empty($r['errors']))),
        ];

        return view('tenant.users.import', ['step' => 2, 'rows' => $validated, 'stats' => $stats]);
    }

    public function commit(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $rows = session('user_import_rows');
        if (! $rows) {
            return redirect()->route('tenant.users.import.show')
                ->with('warning', 'Upload the CSV again — your preview expired.');
        }

        $result = $this->importer->import(
            $rows, $tenant, $request->user(), $request->boolean('skip_errors', true)
        );

        session()->forget('user_import_rows');

        return view('tenant.users.import', ['step' => 3, 'result' => $result]);
    }

    public function sample()
    {
        $csv  = "name,email,phone,role,employee_code,team_lead_email\n";
        $csv .= "Rahul Nair,rahul@example.com,+919876543210,operations_manager,OPS-001,\n";
        $csv .= "Priya Menon,priya@example.com,,team_lead,TL-002,\n";
        $csv .= "Arun Thomas,arun@example.com,+919812345678,field_technician,FT-003,priya@example.com\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="user-import-sample.csv"',
        ]);
    }
}