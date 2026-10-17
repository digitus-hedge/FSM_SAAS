<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /** Platform home — list all tenants. */
    public function index()
    {
        $tenants = Tenant::orderByDesc('created_at')->get();
        return view('platform.home', compact('tenants'));
    }

    /** Platform — show the "create a tenant" form. */
    public function create()
    {
        return view('platform.tenants-create');
    }

    /** Platform — persist a new tenant. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'slug'          => ['required', 'string', 'max:50', 'alpha_dash', 'unique:tenants,slug'],
            'contact_email' => ['nullable', 'email'],
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

        return redirect()->route('home')->with('success', "Workspace '{$tenant->name}' created.");
    }
}