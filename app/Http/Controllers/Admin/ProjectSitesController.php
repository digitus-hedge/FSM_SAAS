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
        $site = ProjectSite::create($request->validated());

        return redirect()->route('admin.sites.index')
            ->with('success', "Site {$site->name} created.");
    }

    public function edit(ProjectSite $site)
    {
        return view('admin.sites.form', [
            'site'    => $site,
            'clients' => Client::active()->orderBy('name')->get(['id', 'name', 'client_code']),
            'mode'    => 'edit',
        ]);
    }

    public function update(ProjectSiteRequest $request, ProjectSite $site)
    {
        $site->update($request->validated());

        return redirect()->route('admin.sites.index')
            ->with('success', "Site {$site->name} updated.");
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
