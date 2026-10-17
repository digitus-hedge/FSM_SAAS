<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::orderByDesc('created_at')->get();

        $stats = [
            'total'     => $tenants->count(),
            'active'    => $tenants->where('status', 'active')->count(),
            'suspended' => $tenants->where('status', 'suspended')->count(),
        ];

        return view('admin.dashboard', compact('tenants', 'stats'));
    }

    public function create()
    {
        return view('admin.tenants-create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'slug'          => ['required', 'string', 'max:50', 'alpha_dash', 'unique:tenants,slug'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'primary_color' => ['nullable', 'string', 'size:7'],
        ]);

        $tenant = Tenant::create([
            'name'          => $data['name'],
            'slug'          => Str::slug($data['slug']),
            'contact_email' => $data['contact_email'] ?? null,
            'primary_color' => $data['primary_color'] ?: '#FFC61A',
            'status'        => 'active',
            'activated_at'  => now(),
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', "Workspace '{$tenant->name}' created.");
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $tenant->update([
            'status'           => 'suspended',
            'suspended_at'     => now(),
            'suspended_reason' => $request->input('reason'),
        ]);

        return back()->with('success', "{$tenant->name} suspended.");
    }

    public function reactivate(Tenant $tenant)
    {
        $tenant->update([
            'status'           => 'active',
            'suspended_at'     => null,
            'suspended_reason' => null,
            'activated_at'     => $tenant->activated_at ?? now(),
        ]);

        return back()->with('success', "{$tenant->name} reactivated.");
    }
}