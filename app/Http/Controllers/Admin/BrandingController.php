<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandFont;
use App\Models\Tenant;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function index()
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        return view('admin.branding.index', [
            'tenant' => $tenant,
            'fonts'  => BrandFont::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $data = $request->validate([
            'primary_color' => ['required', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_key'      => ['required', 'string', 'exists:brand_fonts,key'],
        ]);

        $tenant->update($data);

        return back()->with('success', 'Branding updated.');
    }
}