<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HoldReasonRequest;
use App\Models\HoldReason;
use Illuminate\Http\Request;

class HoldReasonController extends Controller
{
    private array $perPageOptions = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, $this->perPageOptions)) $perPage = 10;

        $status = $request->input('status');
        $search = trim((string) $request->input('q', ''));

        $query = HoldReason::query()->orderBy('label');

        if ($status === 'active')   $query->active();
        if ($status === 'inactive') $query->inactive();
        if ($search !== '')         $query->search($search);

        $reasons = $query->paginate($perPage)->withQueryString();

        return view('admin.hold-reasons.index', [
            'reasons'        => $reasons,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
        ]);
    }

    public function create()
    {
        return view('admin.hold-reasons.form', [
            'reason' => new HoldReason([
                'is_active'     => true,
                'pauses_sla'    => true,
                'requires_note' => true,
            ]),
            'mode' => 'create',
        ]);
    }

    public function store(HoldReasonRequest $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = auth()->user()->tenant_id;

        HoldReason::create($data);

        return redirect()->route('admin.hold-reasons.index')
            ->with('success', 'Hold reason created.');
    }

    public function edit(HoldReason $hold_reason)
    {
        return view('admin.hold-reasons.form', [
            'reason' => $hold_reason,
            'mode'   => 'edit',
        ]);
    }

    public function update(HoldReasonRequest $request, HoldReason $hold_reason)
    {
        $hold_reason->update($request->validated());

        return redirect()->route('admin.hold-reasons.index')
            ->with('success', 'Hold reason updated.');
    }

    public function destroy(HoldReason $hold_reason)
    {
        $hold_reason->delete();

        return redirect()->route('admin.hold-reasons.index')
            ->with('success', 'Hold reason deleted.');
    }

    public function suspend(HoldReason $hold_reason)
    {
        $hold_reason->update(['is_active' => false]);

        return redirect()->route('admin.hold-reasons.index')
            ->with('success', 'Hold reason deactivated.');
    }

    public function reactivate(HoldReason $hold_reason)
    {
        $hold_reason->update(['is_active' => true]);

        return redirect()->route('admin.hold-reasons.index')
            ->with('success', 'Hold reason reactivated.');
    }
}