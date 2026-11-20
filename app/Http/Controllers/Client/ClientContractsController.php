<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Tenant;

/**
 * The client's own contracts, read only (Contracts spec 5.7, D16, CC-117 and CC-118).
 * Client Admin only, on Professional and above with the contracts feature. A client sees its own company's contracts and nothing else:
 * never the value, the payment terms, the notes or the documents. Drafts and cancelled contracts are not shown.
 */
class ClientContractsController extends Controller
{
    private const VISIBLE = ['active', 'expiring_soon', 'expired', 'renewed'];

    private function guard(): Tenant
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);
        abort_unless(in_array($tenant->plan?->code, ['professional', 'enterprise'], true) && $tenant->hasFeature('contracts'), 403);
        abort_unless(auth()->user()?->role?->code === 'client_admin', 403);   // a Client Viewer does not see the page (CC-118)

        return $tenant;
    }

    private function mine()
    {
        return Contract::query()->where('client_id', auth()->user()->client_id ?? 0)->whereIn('status', self::VISIBLE);
    }

    public function index()
    {
        $this->guard();

        return view('client.contracts.index', [
            'contracts' => $this->mine()->orderByRaw("FIELD(status, 'active', 'expiring_soon', 'expired', 'renewed')")->orderBy('end_date')->get(),
        ]);
    }

    /** The parameter is not called {contract}: that name is bound to the office page of a contract. */
    public function show(int $clientContract)
    {
        $this->guard();
        $contract = $this->mine()->with(['sites:id,name', 'categories:id,name'])->whereKey($clientContract)->firstOrFail();

        return view('client.contracts.show', compact('contract'));
    }
}
