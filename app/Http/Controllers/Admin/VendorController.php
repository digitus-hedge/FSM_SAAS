<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VendorRequest;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    private array $perPageOptions = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, $this->perPageOptions)) $perPage = 10;

        $status = $request->input('status');          // null | active | suspended
        $search = trim((string) $request->input('q', ''));

        $query = Vendor::query()->orderBy('name');

        if ($status === 'active')    $query->active();
        if ($status === 'suspended') $query->suspended();
        if ($search !== '')          $query->search($search);

        $vendors = $query->paginate($perPage)->withQueryString();

        return view('admin.vendors.index', [
            'vendors'        => $vendors,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
        ]);
    }

    public function create()
    {
        return view('admin.vendors.form', [
            'vendor' => new Vendor(['status' => 'active']),
            'mode'   => 'create',
        ]);
    }

    public function store(VendorRequest $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = auth()->user()->tenant_id;

        Vendor::create($data);

        return redirect()->route('admin.vendors.index')
            ->with('success', 'Vendor created.');
    }

    public function edit(Vendor $vendor)
    {
        return view('admin.vendors.form', [
            'vendor' => $vendor,
            'mode'   => 'edit',
        ]);
    }

    public function update(VendorRequest $request, Vendor $vendor)
    {
        $vendor->update($request->validated());

        return redirect()->route('admin.vendors.index')
            ->with('success', 'Vendor updated.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();

        return redirect()->route('admin.vendors.index')
            ->with('success', 'Vendor deleted.');
    }

    public function suspend(Vendor $vendor)
    {
        $vendor->update(['status' => 'suspended']);

        return redirect()->route('admin.vendors.index')
            ->with('success', 'Vendor suspended.');
    }

    public function reactivate(Vendor $vendor)
    {
        $vendor->update(['status' => 'active']);

        return redirect()->route('admin.vendors.index')
            ->with('success', 'Vendor reactivated.');
    }
}