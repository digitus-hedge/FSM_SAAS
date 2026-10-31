<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientRequest;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ClientsController extends Controller
{
    /** Allowed per-page sizes */
    protected array $perPageOptions = [10, 25, 50, 100];

 public function index(Request $request)
{
    $perPage = (int) $request->input('per_page', 10);
    if (!in_array($perPage, $this->perPageOptions, true)) $perPage = 10;

    $status = $request->input('status', 'active');

    $clients = Client::query()
        ->withCount([
            'sites',
            'serviceRequests as open_requests_count' => fn ($q) =>
                $q->whereNotIn('status', ['completed', 'cancelled']),
        ])
        ->search($request->input('q'))
        ->when($status !== 'all', fn ($q) =>
            $q->where('is_active', $status === 'active'))
        ->orderByDesc('id')
        ->paginate($perPage)
        ->withQueryString();

    return view('admin.clients.index', [
        'clients'        => $clients,
        'perPage'        => $perPage,
        'perPageOptions' => $this->perPageOptions,
    ]);
}
    public function create()
    {
        return view('admin.clients.form', [
            'client' => new Client(),
            'mode'   => 'create',
        ]);
    }

    public function store(ClientRequest $request)
{
    $data = $request->validated();
    unset($data['client_code']); // code is always system-generated, never user input

    $client = $this->createWithUniqueCode($data);

    return redirect()->route('admin.clients.index')
        ->with('success', "Client {$client->name} created.");
}

/**
 * Create a client with a guaranteed-unique client_code.
 * Locks the last-code row inside a transaction (closes the common race),
 * and retries on a duplicate-key error as a fallback (closes the edge case
 * where this is the tenant's very first client and no row exists to lock yet).
 */
protected function createWithUniqueCode(array $data, int $attempts = 3): Client
{
    for ($i = 0; $i < $attempts; $i++) {
        try {
            return \DB::transaction(function () use ($data) {
                $data['client_code'] = $this->nextClientCode();
                return Client::create($data);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() !== '23000' || $i === $attempts - 1) {
                throw $e;
            }
            // duplicate client_code — loop again and generate a fresh one
        }
    }
}

protected function nextClientCode(): string
{
    $tenantId = Tenant::current()?->id;

    $last = Client::withTrashed()
        ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
        ->where('client_code', 'like', 'CL-%')
        ->orderByDesc('id')
        ->lockForUpdate()
        ->value('client_code');

    $next = $last && preg_match('/CL-(\d+)/', $last, $m)
        ? ((int) $m[1]) + 1
        : 1;

    return 'CL-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

    public function edit(Client $client)
    {
        return view('admin.clients.form', [
            'client' => $client,
            'mode'   => 'edit',
        ]);
    }

    public function update(ClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        return redirect()->route('admin.clients.index')
            ->with('success', "Client {$client->name} updated.");
    }

    public function suspend(Client $client)
    {
        $client->update(['is_active' => false]);
        return back()->with('success', "{$client->name} suspended.");
    }

    public function reactivate(Client $client)
    {
        $client->update(['is_active' => true]);
        return back()->with('success', "{$client->name} reactivated.");
    }

    public function destroy(Client $client)
    {
        $name = $client->name;
        $client->delete();   // soft delete (uses SoftDeletes trait)

        return redirect()->route('admin.clients.index')
            ->with('success', "Client {$name} deleted.");
    }
}
