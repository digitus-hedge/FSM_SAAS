<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantBusinessHoursRequest;
use App\Models\TenantBusinessHour;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
class TenantBusinessHourController extends Controller
{
   public function index(Request $request)
{
    $tenantId = auth()->user()->tenant_id;
    $hours    = TenantBusinessHour::ensureForTenant($tenantId);

    $year = (int) $request->input('year', now()->year);

    $holidays = \App\Models\TenantHoliday::inYear($year)
        ->orderBy('holiday_date')
        ->get();

    // Year dropdown — existing years + current + next
    $years = \App\Models\TenantHoliday::query()
        ->selectRaw('DISTINCT YEAR(holiday_date) as y')
        ->orderByDesc('y')
        ->pluck('y')->map(fn ($y) => (int) $y)
        ->push((int) now()->year)
        ->push((int) now()->addYear()->year)
        ->unique()->sortDesc()->values();

    return view('admin.tenant-business-hours.index', [
        'hours'    => $hours,
        'weekdays' => TenantBusinessHour::WEEKDAYS,
        'holidays' => $holidays,
        'years'    => $years,
        'year'     => $year,
    ]);
}

    public function update(TenantBusinessHoursRequest $request)
    {
        $tenantId = auth()->user()->tenant_id;

        DB::transaction(function () use ($request, $tenantId) {
            foreach ($request->validated()['hours'] as $row) {
                TenantBusinessHour::updateOrCreate(
                    ['tenant_id' => $tenantId, 'weekday' => $row['weekday']],
                    [
                        'is_open'   => $row['is_open'],
                        'opens_at'  => $row['opens_at'],
                        'closes_at' => $row['closes_at'],
                    ]
                );
            }
        });

        return redirect()->route('admin.tenant-business-hours.index')
            ->with('success', 'Business hours updated.');
    }
}