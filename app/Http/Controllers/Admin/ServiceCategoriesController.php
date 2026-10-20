<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceCategoryRequest;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceCategoriesController extends Controller
{
    protected array $perPageOptions = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, $this->perPageOptions, true)) $perPage = 25;

        $categories = ServiceCategory::query()
            ->search($request->input('q'))
            ->when($request->filled('status'), fn($q) =>
            $q->where('is_active', $request->input('status') === 'active'))
            ->when($request->filled('domain'), fn($q) =>
            $q->where('service_domain', $request->input('domain')))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $domains = ServiceCategory::query()
            ->whereNotNull('service_domain')
            ->distinct()->orderBy('service_domain')
            ->pluck('service_domain');

        return view('admin.service-categories.index', [
            'categories'     => $categories,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
            'domains'        => $domains,
        ]);
    }

    public function create()
    {
        return view('admin.service-categories.form', [
            'category'      => new ServiceCategory([
                'approval_mode'           => 'manual',
                'require_before_photo'    => true,
                'require_after_photo'     => true,
                'require_signature'       => true,
                'allow_multi_asset_batch' => true,
                'is_active'               => true,
            ]),
            'approvalModes' => ServiceCategory::APPROVAL_MODES,
            'mode'          => 'create',
        ]);
    }

    public function store(ServiceCategoryRequest $request)
    {
        $category = ServiceCategory::create($request->validated());

        return redirect()->route('admin.service-categories.index')
            ->with('success', "Category {$category->name} created.");
    }

    public function edit(ServiceCategory $category)
    {
        return view('admin.service-categories.form', [
            'category'      => $category,
            'approvalModes' => ServiceCategory::APPROVAL_MODES,
            'mode'          => 'edit',
        ]);
    }

    public function update(ServiceCategoryRequest $request, ServiceCategory $category)
    {
        $category->update($request->validated());

        return redirect()->route('admin.service-categories.index')
            ->with('success', "Category {$category->name} updated.");
    }

    public function suspend(ServiceCategory $category)
    {
        $category->update(['is_active' => false]);
        return back()->with('success', "{$category->name} deactivated.");
    }

    public function reactivate(ServiceCategory $category)
    {
        $category->update(['is_active' => true]);
        return back()->with('success', "{$category->name} reactivated.");
    }

    public function destroy(ServiceCategory $category)
    {
        $name = $category->name;
        $category->delete();   // soft delete (uses SoftDeletes trait)

        return redirect()->route('admin.service-categories.index')
            ->with('success', "Category {$name} deleted.");
    }
}
