<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantBusinessHoursRequest;
use App\Models\TenantBusinessHour;
use Illuminate\Support\Facades\DB;

class TenantBusinessHourController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenant_id;
        $hours    = TenantBusinessHour::ensureForTenant($tenantId);

        return view('admin.tenant-business-hours.index', [
            'hours'    => $hours,
            'weekdays' => TenantBusinessHour::WEEKDAYS,
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