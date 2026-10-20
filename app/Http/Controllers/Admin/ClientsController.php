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
        // Clamp per_page to one of the allowed values, default 25
        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, $this->perPageOptions, true)) {
            $perPage = 10;
        }

        $clients = Client::query()
            ->when($request->filled('q'), fn($q) =>
            $q->where(function ($w) use ($request) {
                $term = $request->input('q');
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('client_code', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            }))
            ->when($request->filled('status'), fn($q) =>
            $q->where('is_active', $request->input('status') === 'active'))
            ->orderBy('name')
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
        $client = Client::create($request->validated());

        return redirect()->route('admin.clients.index')
            ->with('success', "Client {$client->name} created.");
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
