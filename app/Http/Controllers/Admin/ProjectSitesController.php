<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProjectSiteRequest;
use App\Models\Client;
use App\Models\ProjectSite;
use Illuminate\Http\Request;

class ProjectSitesController extends Controller
{
    protected array $perPageOptions = [10, 25, 50, 100];

   public function index(Request $request)
{
    $perPage = (int) $request->input('per_page', 10);
    if (!in_array($perPage, $this->perPageOptions, true)) $perPage = 10;

    $sites = ProjectSite::query()
        ->with('client:id,name,client_code')
        ->when($request->filled('q'), fn($q) =>
        $q->where(function ($w) use ($request) {
            $term = $request->input('q');
            $w->where('name', 'like', "%{$term}%")
                ->orWhere('site_code', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%");
        }))
        ->when($request->filled('type'), fn($q) =>
        $q->where('site_type', $request->input('type')))
        ->when($request->filled('client_id'), fn($q) =>
        $q->where('client_id', $request->input('client_id')))
        ->when($request->filled('status'), fn($q) =>
        $q->where('is_active', $request->input('status') === 'active'))
        ->when($request->filled('location'), function ($q) use ($request) {
            if ($request->input('location') === 'set') {
                $q->whereNotNull('lat')->whereNotNull('lng');
            } elseif ($request->input('location') === 'unset') {
                $q->where(function ($w) {
                    $w->whereNull('lat')->orWhereNull('lng');
                });
            }
        })
        ->orderByDesc('id')
        ->paginate($perPage)
        ->withQueryString();

    return view('admin.sites.index', [
        'sites'          => $sites,
        'perPage'        => $perPage,
        'perPageOptions' => $this->perPageOptions,
        'clients'        => Client::active()->orderBy('name')->get(['id', 'name', 'client_code']),
    ]);
}

    public function create()
    {
        return view('admin.sites.form', [
            'site'    => new ProjectSite(['site_type' => 'client_site', 'is_active' => true]),
            'clients' => Client::active()->orderBy('name')->get(['id', 'name', 'client_code']),
            'mode'    => 'create',
        ]);
    }

   public function store(ProjectSiteRequest $request)
{
    $data = $request->validated();
    unset($data['site_code']); // always system-generated, never user input

    $site = $this->createWithUniqueCode($data);

    return redirect()->route('admin.sites.index')
        ->with('success', "Site {$site->name} created.");
}

protected function createWithUniqueCode(array $data, int $attempts = 3): ProjectSite
{
    for ($i = 0; $i < $attempts; $i++) {
        try {
            return \DB::transaction(function () use ($data) {
                $data['site_code'] = $this->nextSiteCode();
                return ProjectSite::create($data);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() !== '23000' || $i === $attempts - 1) {
                throw $e;
            }
            // duplicate site_code — loop again and generate a fresh one
        }
    }
}

protected function nextSiteCode(): string
{
    $tenantId = \App\Models\Tenant::current()?->id;

    $last = ProjectSite::withTrashed()
        ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
        ->where('site_code', 'like', 'ST-%')
        ->orderByDesc('id')
        ->lockForUpdate()
        ->value('site_code');

    $next = $last && preg_match('/ST-(\d+)/', $last, $m)
        ? ((int) $m[1]) + 1
        : 1;

    return 'ST-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

public function update(ProjectSiteRequest $request, ProjectSite $site)
{
    $data = $request->validated();
    unset($data['site_code']); // immutable after creation — never changes on edit

    $site->update($data);

    return redirect()->route('admin.sites.index')
        ->with('success', "Site {$site->name} updated.");
}

    public function edit(ProjectSite $site)
    {
        return view('admin.sites.form', [
            'site'    => $site,
            'clients' => Client::active()->orderBy('name')->get(['id', 'name', 'client_code']),
            'mode'    => 'edit',
        ]);
    }

    public function suspend(ProjectSite $site)
    {
        $site->update(['is_active' => false]);
        return back()->with('success', "{$site->name} deactivated.");
    }

    public function reactivate(ProjectSite $site)
    {
        $site->update(['is_active' => true]);
        return back()->with('success', "{$site->name} reactivated.");
    }

    public function destroy(ProjectSite $site)
    {
        $name = $site->name;
        $site->delete();   // soft delete (uses SoftDeletes trait)

        return redirect()->route('admin.sites.index')
            ->with('success', "Site {$name} deleted.");
    }
}
