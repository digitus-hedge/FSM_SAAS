<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SlaPolicyRequest;
use App\Models\ServiceCategory;
use App\Models\SlaPolicy;
use Illuminate\Http\Request;

class SlaPoliciesController extends Controller
{
    protected array $perPageOptions = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, $this->perPageOptions, true)) $perPage = 10;

        $policies = SlaPolicy::query()
            ->with('category:id,name,code')
            ->when($request->filled('category_id'), function ($q) use ($request) {
                if ($request->input('category_id') === 'default') {
                    $q->whereNull('service_category_id');
                } else {
                    $q->where('service_category_id', $request->input('category_id'));
                }
            })
            ->when($request->filled('priority'), fn ($q) =>
                $q->where('priority', $request->input('priority')))
            ->orderedByPriority()
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.sla-policies.index', [
            'policies'       => $policies,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
            'categories'     => ServiceCategory::active()->orderBy('name')->get(['id','name','code']),
            'priorities'     => SlaPolicy::PRIORITY_LABELS,
        ]);
    }

    public function create()
    {
        return view('admin.sla-policies.form', [
            'policy'     => new SlaPolicy([
                'response_minutes'    => 60,
                'resolution_minutes'  => 240,
                'uses_business_hours' => true,
            ]),
            'categories' => ServiceCategory::active()->orderBy('name')->get(['id','name','code']),
            'priorities' => SlaPolicy::PRIORITY_LABELS,
            'mode'       => 'create',
        ]);
    }

    public function store(SlaPolicyRequest $request)
    {
        $policy = SlaPolicy::create($request->validated());

        return redirect()->route('admin.sla-policies.index')
            ->with('success', 'SLA policy created.');
    }

    public function edit(SlaPolicy $policy)
    {
        return view('admin.sla-policies.form', [
            'policy'     => $policy,
            'categories' => ServiceCategory::active()->orderBy('name')->get(['id','name','code']),
            'priorities' => SlaPolicy::PRIORITY_LABELS,
            'mode'       => 'edit',
        ]);
    }

    public function update(SlaPolicyRequest $request, SlaPolicy $policy)
    {
        $policy->update($request->validated());

        return redirect()->route('admin.sla-policies.index')
            ->with('success', 'SLA policy updated.');
    }

    public function destroy(SlaPolicy $policy)
    {
        $policy->delete();
        return back()->with('success', 'SLA policy removed.');
    }
}