<?php
// app/Http/Controllers/Platform/TenantController.php
namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateTenantRequest;
use App\Jobs\ProvisionCompany;
use App\Models\Tenant;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::with('plan')
            ->orderByDesc('created_at')
            ->paginate(25);
        return view('platform.companies.index', compact('tenants'));
    }

    public function create()
    {
        return view('platform.companies.create', [
            'plans' => \App\Models\Plan::where('is_active', true)->get(),
        ]);
    }

    public function store(CreateTenantRequest $request)
    {
        // Dispatch the queued set-up job (D20). It does the 9 steps of §6.2
        // in a single DB transaction, idempotent on checkout_reference.
        $job = ProvisionCompany::dispatch($request->validated() + [
            'source' => 'platform_admin',
            'checkout_reference' => null,
        ]);

        // The screen shows "Setting up the company" and polls job status.
        return redirect()->route('platform.companies.provisioning', ['job' => $job->getJobId()]);
    }
}