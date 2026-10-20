<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantHolidayRequest;
use App\Models\TenantHoliday;
use Illuminate\Http\Request;

class TenantHolidayController extends Controller
{
    private array $perPageOptions = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, $this->perPageOptions)) $perPage = 10;

        $scope  = $request->input('scope');          // null | upcoming | past
        $year   = $request->input('year');           // optional int
        $search = trim((string) $request->input('q', ''));

        $query = TenantHoliday::query()->orderBy('holiday_date');

        if ($scope === 'upcoming') $query->upcoming();
        if ($scope === 'past')     $query->past();
        if ($year)                 $query->inYear((int) $year);
        if ($search !== '')        $query->search($search);

        $holidays = $query->paginate($perPage)->withQueryString();

        // Year filter options — all distinct years we have holidays in, plus this year + next year
        $years = TenantHoliday::query()
            ->selectRaw('DISTINCT YEAR(holiday_date) as y')
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->push((int) now()->year)
            ->push((int) now()->addYear()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return view('admin.tenant-holidays.index', [
            'holidays'       => $holidays,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
            'years'          => $years,
        ]);
    }

    public function create()
    {
        return view('admin.tenant-holidays.form', [
            'holiday' => new TenantHoliday(),
            'mode'    => 'create',
        ]);
    }

    public function store(TenantHolidayRequest $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = auth()->user()->tenant_id;

        TenantHoliday::create($data);

        return redirect()->route('admin.tenant-holidays.index')
            ->with('success', 'Holiday added.');
    }

    public function edit(TenantHoliday $tenant_holiday)
    {
        return view('admin.tenant-holidays.form', [
            'holiday' => $tenant_holiday,
            'mode'    => 'edit',
        ]);
    }

    public function update(TenantHolidayRequest $request, TenantHoliday $tenant_holiday)
    {
        $tenant_holiday->update($request->validated());

        return redirect()->route('admin.tenant-holidays.index')
            ->with('success', 'Holiday updated.');
    }

    public function destroy(TenantHoliday $tenant_holiday)
    {
        $tenant_holiday->delete();

        return redirect()->route('admin.tenant-holidays.index')
            ->with('success', 'Holiday deleted.');
    }
}